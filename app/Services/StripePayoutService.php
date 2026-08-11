<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StripeConfiguration;
use App\Models\StripePayout;
use Illuminate\Support\Facades\Log;
use Stripe\BalanceTransaction;
use Stripe\Payout;
use Stripe\Stripe;
use Exception;

class StripePayoutService
{
    /**
     * Get the active Stripe secret/restricted key from config or DB.
     */
    public function getApiKey(): ?string
    {
        $key = config('services.stripe.secret');
        if (!empty($key)) {
            return $key;
        }

        $key = env('STRIPE_RESTRICTED_KEY') ?: env('STRIPE_SECRET_KEY') ?: env('STRIPE_KEY');
        if (!empty($key)) {
            return $key;
        }

        $config = StripeConfiguration::latest()->first();
        return $config?->stripe_secret_key;
    }

    /**
     * Extract trace ID or statement descriptor from Airwallex description/reference.
     * E.g. "STRIPE-UOAC0SVHNQD" -> "UOAC0SVHNQD"
     */
    public function extractTraceId(string $description): string
    {
        $clean = trim($description);
        if (preg_match('/STRIPE[-_ ]([A-Z0-9]+)/i', $clean, $matches)) {
            return $matches[1];
        }
        return $clean;
    }

    /**
     * Fetch Payout details and customer payment breakdown from local DB or Stripe API.
     */
    public function getPayoutDetails(string $description, ?string $settledDate = null): array
    {
        $traceId = $this->extractTraceId($description);

        // 1. Check local DB table 'stripe_payouts' first
        $dbPayout = StripePayout::where('trace_id', $traceId)
            ->orWhere('statement_descriptor', $description)
            ->orWhere('statement_descriptor', 'LIKE', "%{$traceId}%")
            ->orWhere('id', $traceId)
            ->first();

        if ($dbPayout) {
            $breakdown = $dbPayout->breakdown ?? [];
            foreach ($breakdown as &$charge) {
                $charge['matching_invoices'] = $this->findMatchingInvoices(
                    (float)($charge['gross'] ?? 0),
                    $charge['customer_email'] ?? null,
                    $charge['customer_name'] ?? null,
                    $charge['description'] ?? null
                );
            }

            return [
                'found' => true,
                'from_db' => true,
                'payout' => [
                    'id' => $dbPayout->id,
                    'amount' => (float)$dbPayout->amount,
                    'currency' => strtoupper($dbPayout->currency),
                    'status' => strtoupper($dbPayout->status),
                    'arrival_date' => $dbPayout->arrival_date ? $dbPayout->arrival_date->format('Y-m-d') : date('Y-m-d'),
                    'trace_id' => $dbPayout->trace_id ?: $traceId,
                    'statement_descriptor' => $dbPayout->statement_descriptor ?: $description,
                    'total_gross' => (float)$dbPayout->total_gross,
                    'total_fees' => (float)$dbPayout->total_fees,
                    'total_net' => (float)$dbPayout->total_net,
                ],
                'breakdown' => $breakdown,
            ];
        }

        // 2. Fetch from Stripe API if API key is configured
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return [
                'found' => false,
                'message' => 'Stripe secret/restricted API key is not configured in .env (STRIPE_RESTRICTED_KEY) or admin configuration.',
                'trace_id' => $traceId,
            ];
        }

        try {
            Stripe::setApiKey($apiKey);
            $payout = $this->findAndSyncPayout($description, $traceId, $settledDate);

            if (!$payout) {
                return [
                    'found' => false,
                    'message' => "No matching Stripe payout found for trace ID: '{$traceId}' ({$description}). List was scanned and single payout objects were retrieved.",
                    'trace_id' => $traceId,
                ];
            }

            // Return freshly synced DB record
            return $this->getPayoutDetails($description, $settledDate);
        } catch (Exception $e) {
            Log::error('Error in StripePayoutService getPayoutDetails', [
                'description' => $description,
                'error' => $e->getMessage(),
            ]);

            return [
                'found' => false,
                'message' => 'Stripe API Error: ' . $e->getMessage(),
                'trace_id' => $traceId,
            ];
        }
    }

    /**
     * Find Payout from Stripe API (fetching single Payout GET requests for full trace_id hash)
     * and persist to local DB table 'stripe_payouts'.
     */
    private function findAndSyncPayout(string $description, string $traceId, ?string $settledDate = null): ?StripePayout
    {
        // Direct ID retrieve if description or traceId contains po_...
        if (preg_match('/po_[a-zA-Z0-9]+/', $description, $m) || preg_match('/po_[a-zA-Z0-9]+/', $traceId, $m)) {
            try {
                $p = Payout::retrieve($m[0]);
                return $this->syncPayoutToDb($p);
            } catch (Exception $e) {
                Log::info('Stripe direct Payout retrieve failed for ' . $m[0] . ': ' . $e->getMessage());
            }
        }

        // List payouts from Stripe API
        $payoutList = [];
        try {
            $params = ['limit' => 50];
            if ($settledDate) {
                $ts = strtotime($settledDate);
                if ($ts > 0) {
                    $params['arrival_date'] = [
                        'gte' => $ts - (86400 * 14), // -14 days
                        'lte' => $ts + (86400 * 14), // +14 days
                    ];
                }
            }
            $list = Payout::all($params);
            $payoutList = $list->data ?? [];
        } catch (Exception $e) {
            Log::warning('Failed listing payouts from Stripe: ' . $e->getMessage());
        }

        if (empty($payoutList)) {
            try {
                $list = Payout::all(['limit' => 50]);
                $payoutList = $list->data ?? [];
            } catch (Exception $e) {
                Log::warning('Fallback payout list failed: ' . $e->getMessage());
            }
        }

        // For each payout in the list, fetch single Payout object via Payout::retrieve($id)
        // to retrieve the full trace_id hash and store in DB!
        $matchedRecord = null;
        foreach ($payoutList as $summaryPayout) {
            try {
                $fullPayout = Payout::retrieve($summaryPayout->id);
                $savedRecord = $this->syncPayoutToDb($fullPayout);

                if ($savedRecord && !$matchedRecord) {
                    $dbTrace = (string)($savedRecord->trace_id ?? '');
                    $dbDesc = (string)($savedRecord->statement_descriptor ?? '');
                    $dbId = (string)($savedRecord->id ?? '');

                    $isMatch = ($dbTrace && (strcasecmp($dbTrace, $traceId) === 0 || stripos($description, $dbTrace) !== false || stripos($dbTrace, $traceId) !== false))
                        || ($dbDesc && (stripos($dbDesc, $traceId) !== false || stripos($description, $dbDesc) !== false))
                        || ($dbId && (stripos($description, $dbId) !== false || strcasecmp($dbId, $traceId) === 0));

                    if ($isMatch) {
                        $matchedRecord = $savedRecord;
                    }
                }
            } catch (Exception $ex) {
                Log::warning("Error retrieving single payout {$summaryPayout->id}: " . $ex->getMessage());
            }
        }

        return $matchedRecord;
    }

    /**
     * Fetch BalanceTransactions for a Payout and save/update record in stripe_payouts DB table.
     */
    private function syncPayoutToDb(Payout $payout): StripePayout
    {
        // Extract trace_id string from single Payout object
        $traceIdVal = null;
        if (!empty($payout->trace_id)) {
            if (is_string($payout->trace_id)) {
                $traceIdVal = $payout->trace_id;
            } elseif (is_object($payout->trace_id) && isset($payout->trace_id->value)) {
                $traceIdVal = (string)$payout->trace_id->value;
            } elseif (is_array($payout->trace_id) && isset($payout->trace_id['value'])) {
                $traceIdVal = (string)$payout->trace_id['value'];
            }
        }

        // Fetch Balance Transactions associated with this Payout
        $balanceTxs = BalanceTransaction::all([
            'payout' => $payout->id,
            'limit' => 100,
            'expand' => ['data.source', 'data.source.customer'],
        ]);

        $charges = [];
        $totalGross = 0;
        $totalFees = 0;
        $totalNet = 0;

        foreach ($balanceTxs->data as $bt) {
            $gross = round(($bt->amount ?? 0) / 100, 2);
            $fee = round(($bt->fee ?? 0) / 100, 2);
            $net = round(($bt->net ?? 0) / 100, 2);

            $totalGross += $gross;
            $totalFees += $fee;
            $totalNet += $net;

            $custName = null;
            $custEmail = null;
            $chargeDesc = $bt->description ?? '';
            $sourceId = null;

            if (isset($bt->source) && is_object($bt->source)) {
                $sourceObj = $bt->source;
                $sourceId = $sourceObj->id ?? null;

                if (!empty($sourceObj->description)) {
                    $chargeDesc = $sourceObj->description;
                }

                if (isset($sourceObj->customer) && is_object($sourceObj->customer)) {
                    $custName = $sourceObj->customer->name ?? null;
                    $custEmail = $sourceObj->customer->email ?? null;
                }

                if (!$custName && isset($sourceObj->billing_details)) {
                    $custName = $sourceObj->billing_details->name ?? null;
                    $custEmail = $sourceObj->billing_details->email ?? null;
                }
            }

            $charges[] = [
                'id' => $bt->id,
                'type' => $bt->type,
                'gross' => $gross,
                'fee' => $fee,
                'net' => $net,
                'currency' => strtoupper($bt->currency ?? 'AUD'),
                'customer_name' => $custName ?? 'Customer',
                'customer_email' => $custEmail,
                'description' => $chargeDesc ?: ($bt->type === 'stripe_fee' ? 'Stripe Processing Fee' : 'Payment'),
                'source_id' => $sourceId,
                'created_at' => date('Y-m-d H:i:s', $bt->created),
            ];
        }

        return StripePayout::updateOrCreate(
            ['id' => $payout->id],
            [
                'trace_id' => $traceIdVal,
                'statement_descriptor' => $payout->statement_descriptor ?? $payout->description ?? null,
                'amount' => round(($payout->amount ?? 0) / 100, 2),
                'currency' => strtoupper($payout->currency ?? 'AUD'),
                'status' => strtoupper($payout->status ?? 'PAID'),
                'arrival_date' => date('Y-m-d', $payout->arrival_date ?? time()),
                'total_gross' => round($totalGross, 2),
                'total_fees' => round($totalFees, 2),
                'total_net' => round($totalNet, 2),
                'breakdown' => $charges,
            ]
        );
    }

    /**
     * Find outstanding invoices matching amount, email, or invoice ref.
     */
    private function findMatchingInvoices(float $amount, ?string $email, ?string $name, ?string $desc): array
    {
        $query = Invoice::query()
            ->with(['client', 'project'])
            ->whereIn('status', ['authorised', 'sent', 'partial_paid']);

        $invoices = $query->get();
        $matches = [];

        foreach ($invoices as $inv) {
            $remaining = $inv->remaining_amount !== null ? (float)$inv->remaining_amount : (float)$inv->total_amount;
            $invNum = $inv->invoice_number ?? ('OZI' . $inv->id);
            $clientEmail = $inv->client?->email ?? '';
            $clientName = $inv->client?->name ?? '';

            $isAmountMatch = abs($remaining - $amount) < 0.05;
            $isEmailMatch = $email && $clientEmail && strcasecmp($email, $clientEmail) === 0;
            $isNumMatch = $desc && (stripos($desc, (string)$inv->id) !== false || stripos($desc, $invNum) !== false);

            if ($isAmountMatch || $isEmailMatch || $isNumMatch) {
                $matches[] = [
                    'id' => $inv->id,
                    'invoice_number' => $invNum,
                    'client_name' => $clientName ?: 'Unknown Client',
                    'total_amount' => (float)$inv->total_amount,
                    'remaining_amount' => $remaining,
                    'currency' => $inv->currency ?? 'AUD',
                    'project_name' => $inv->project?->name ?? 'N/A',
                    'is_exact_match' => ($isAmountMatch && ($isEmailMatch || $isNumMatch)),
                ];
            }
        }

        return array_slice($matches, 0, 5);
    }
}
