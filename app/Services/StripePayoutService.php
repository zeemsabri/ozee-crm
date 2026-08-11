<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StripeConfiguration;
use Illuminate\Support\Facades\Cache;
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

        $config = StripeConfiguration::latest()->first();
        return $config?->stripe_secret_key;
    }

    /**
     * Extract trace ID or statement descriptor from Airwallex description/reference.
     * E.g. "STRIPE-UOAC0SVHNQD" -> "UOAC0SVHNQD" or "STRIPE-UOAC0SVHNQD"
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
     * Fetch Payout details and customer payment breakdown from Stripe API.
     */
    public function getPayoutDetails(string $description, ?string $settledDate = null): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return [
                'found' => false,
                'message' => 'Stripe secret/restricted API key is not configured in .env (STRIPE_RESTRICTED_KEY) or admin configuration.',
            ];
        }

        $traceId = $this->extractTraceId($description);
        $cacheKey = 'stripe_payout_data_' . md5($description . '_' . ($settledDate ?? ''));

        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached['found'])) {
                return $cached;
            }
        }

        try {
            Stripe::setApiKey($apiKey);
            $payout = $this->findPayout($description, $traceId, $settledDate);

            if (!$payout) {
                return [
                    'found' => false,
                    'message' => 'No matching Stripe payout found for description: ' . $description,
                    'trace_id' => $traceId,
                ];
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

                        // Customer info
                        if (isset($sourceObj->customer) && is_object($sourceObj->customer)) {
                            $custName = $sourceObj->customer->name ?? null;
                            $custEmail = $sourceObj->customer->email ?? null;
                        }

                        if (!$custName && isset($sourceObj->billing_details)) {
                            $custName = $sourceObj->billing_details->name ?? null;
                            $custEmail = $sourceObj->billing_details->email ?? null;
                        }
                    }

                    // Look for potential matching outstanding system invoices
                    $matchingInvoices = $this->findMatchingInvoices($gross, $custEmail, $custName, $chargeDesc);

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
                        'matching_invoices' => $matchingInvoices,
                    ];
                }

                $result = [
                    'found' => true,
                    'payout' => [
                        'id' => $payout->id,
                        'amount' => round(($payout->amount ?? 0) / 100, 2),
                        'currency' => strtoupper($payout->currency ?? 'AUD'),
                        'status' => strtoupper($payout->status ?? 'PAID'),
                        'arrival_date' => date('Y-m-d', $payout->arrival_date ?? time()),
                        'trace_id' => $traceId,
                        'statement_descriptor' => $payout->statement_descriptor ?? $description,
                        'total_gross' => round($totalGross, 2),
                        'total_fees' => round($totalFees, 2),
                        'total_net' => round($totalNet, 2),
                    ],
                    'breakdown' => $charges,
                ];

                Cache::put($cacheKey, $result, now()->addDays(7));
                return $result;
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
     * Locate Stripe Payout using direct ID lookup or Payout::all() list matching.
     */
    private function findPayout(string $description, string $traceId, ?string $settledDate = null): ?Payout
    {
        // 1. Direct Payout ID check if description or traceId contains po_...
        if (preg_match('/po_[a-zA-Z0-9]+/', $description, $m) || preg_match('/po_[a-zA-Z0-9]+/', $traceId, $m)) {
            try {
                return Payout::retrieve($m[0]);
            } catch (Exception $e) {
                Log::info('Stripe direct Payout retrieve failed for ' . $m[0] . ': ' . $e->getMessage());
            }
        }

        // 2. Fetch Payouts list from Stripe API
        try {
            $payouts = [];

            // Try with date range if settledDate is provided
            if ($settledDate) {
                $timestamp = strtotime($settledDate);
                if ($timestamp > 0) {
                    try {
                        $list = Payout::all([
                            'limit' => 50,
                            'arrival_date' => [
                                'gte' => $timestamp - (86400 * 7), // -7 days
                                'lte' => $timestamp + (86400 * 7), // +7 days
                            ],
                        ]);
                        $payouts = $list->data ?? [];
                    } catch (Exception $ex) {
                        Log::info('Stripe date-filtered Payout list failed: ' . $ex->getMessage());
                    }
                }
            }

            // Fallback: list 50 recent payouts without date constraint
            if (empty($payouts)) {
                $list = Payout::all(['limit' => 50]);
                $payouts = $list->data ?? [];
            }

            foreach ($payouts as $p) {
                $pTrace = '';
                if (!empty($p->trace_id)) {
                    if (is_string($p->trace_id)) {
                        $pTrace = $p->trace_id;
                    } elseif (is_object($p->trace_id) && isset($p->trace_id->value)) {
                        $pTrace = (string)$p->trace_id->value;
                    }
                }

                $pDesc = (string)($p->statement_descriptor ?? $p->description ?? '');
                $pId = (string)($p->id ?? '');

                // Check for matches
                $isTraceMatch = $pTrace && (
                    strcasecmp($pTrace, $traceId) === 0 || 
                    strcasecmp($pTrace, $description) === 0 || 
                    stripos($description, $pTrace) !== false ||
                    stripos($pTrace, $traceId) !== false
                );

                $isDescMatch = $pDesc && (
                    stripos($pDesc, $traceId) !== false || 
                    stripos($description, $pDesc) !== false
                );

                $isIdMatch = $pId && (
                    stripos($description, $pId) !== false ||
                    stripos($traceId, $pId) !== false
                );

                if ($isTraceMatch || $isDescMatch || $isIdMatch) {
                    return $p;
                }
            }
        } catch (Exception $e) {
            Log::warning('Stripe Payout List lookup failed: ' . $e->getMessage());
        }

        return null;
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
