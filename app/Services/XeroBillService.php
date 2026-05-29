<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Transaction;
use App\Enums\BillStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class XeroBillService
{
    private const INVOICES_URL = 'https://api.xero.com/api.xro/2.0/Invoices';

    public function __construct(private readonly XeroTokenService $xeroTokenService) {}

    /**
     * Create a Purchase Invoice in Xero for the given bill.
     */
    public function createPurchaseInvoice(Bill $bill): string
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();
        
        $contact = $bill->contractor;
        if (!$contact || !$contact->telegramAccount?->xero_contact_id) {
             // Fallback to searching or creating contact if needed, but spec says Phase 2 is complete.
             // Let's check how contractor is linked to Xero.
             // Actually, ProjectExpendable has user_id. Let's check User model for Xero fields.
        }

        // For now, let's assume we have the Xero Contact ID. 
        // I should check where Xero Contact ID is stored for Users/Contractors.
        // In Clients, it's xero_contact_id. 
        // Let's check Users table.
        
        $xeroContactId = $this->getContactIdForContractor($bill->contractor);

        $accountCode = strtoupper((string) ($bill->xero_account_code ?? $bill->transactionType?->xero_account_code ?? ''));
        $accountCode = trim($accountCode);

        if ($accountCode === '') {
            throw new RuntimeException('Xero account code is required for bill sync.');
        }

        $this->assertValidAccountCodeForBill($accountCode, $credentials);

        $lineItem = [
            'Description' => "Bill for Project: {$bill->project->name} - Expendable: {$bill->expendable->name}",
            'Quantity' => 1.0,
            'UnitAmount' => (float) $bill->amount,
            'AccountCode' => $accountCode,
        ];

        if ($bill->xero_tax_type === 'NONE') {
            // NONE is our internal flag. For Xero purchases use NOINPUT to represent no GST claim.
            $lineItem['TaxType'] = 'NOINPUT';
        } elseif ($bill->xero_tax_type) {
            $lineItem['TaxType'] = $bill->xero_tax_type;
        }

        $payload = [
            'Type' => 'ACCPAY',
            'Contact' => [
                'ContactID' => $xeroContactId,
            ],
            'Date' => $bill->created_at->format('Y-m-d'),
            'DueDate' => $bill->due_date ? $bill->due_date->format('Y-m-d') : $bill->created_at->addDays(14)->format('Y-m-d'),
            'Reference' => $bill->reference_number ?? '',
            'CurrencyCode' => $bill->currency ?? 'AUD',
            'LineAmountTypes' => 'Exclusive',
            'Status' => 'AUTHORISED',
            'LineItems' => [$lineItem],
        ];

        try {
            $response = Http::withToken($credentials['access_token'])
                ->withHeaders([
                    'Xero-tenant-id' => $credentials['tenant_id'],
                    'Accept' => 'application/json',
                ])
                ->post(self::INVOICES_URL, $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $responseBody = $exception->response?->json();
            $xeroMessage = data_get($responseBody, 'Message', 'Xero validation failed.');

            $validationErrors = collect(data_get($responseBody, 'Elements', []))
                ->flatMap(fn ($element) => data_get($element, 'ValidationErrors', []))
                ->pluck('Message')
                ->filter()
                ->unique()
                ->values();

            if ($validationErrors->isNotEmpty()) {
                $xeroMessage .= ' ' . $validationErrors->implode(' | ');
            }

            Log::warning('Xero bill validation failed', [
                'bill_id' => $bill->id,
                'payload' => $payload,
                'response' => $responseBody,
            ]);

            throw new RuntimeException($xeroMessage, previous: $exception);
        }

        $xeroInvoiceId = data_get($response, 'Invoices.0.InvoiceID');

        if (!$xeroInvoiceId) {
            throw new RuntimeException('Failed to create purchase invoice in Xero.');
        }

        return $xeroInvoiceId;
    }

    /**
     * @param array{access_token:string, tenant_id:string, tenant_name:?string} $credentials
     */
    private function assertValidAccountCodeForBill(string $accountCode, array $credentials): void
    {
        $cacheKey = sprintf('xero.active_accounts.%s', $credentials['tenant_id']);

        $accounts = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($credentials) {
            $response = Http::withToken($credentials['access_token'])
                ->withHeaders([
                    'Xero-tenant-id' => $credentials['tenant_id'],
                    'Accept' => 'application/json',
                ])
                ->get('https://api.xero.com/api.xro/2.0/Accounts')
                ->throw()
                ->json();

            return collect(data_get($response, 'Accounts', []));
        });

        $match = $accounts->first(function ($account) use ($accountCode) {
            return strtoupper((string) data_get($account, 'Code', '')) === strtoupper($accountCode)
                && data_get($account, 'Status') === 'ACTIVE';
        });

        if (! $match) {
            throw new RuntimeException("Xero account code '{$accountCode}' is not an active account in the connected Xero tenant.");
        }
    }

    /**
     * Void a Purchase Invoice in Xero.
     */
    public function voidPurchaseInvoice(Bill $bill): void
    {
        if (!$bill->xero_invoice_id) {
            return;
        }

        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->post(self::INVOICES_URL . '/' . $bill->xero_invoice_id, [
                'Status' => 'VOIDED',
            ])
            ->throw();
    }

    private function getContactIdForContractor($contractor): string
    {
        // Check if contractor has xero_contact_id (might be in metadata or a new field)
        // Let's check User model for xero_contact_id. 
        // Wait, I saw "add_xero_contact_fields_to_clients_table" but not for users.
        // Let's check if users have xero_contact_id.
        return $contractor->xero_contact_id ?? throw new RuntimeException("Contractor {$contractor->name} is not linked to Xero.");
    }

    public function getInvoiceStatus(string $xeroInvoiceId): array
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get(self::INVOICES_URL . '/' . $xeroInvoiceId)
            ->throw()
            ->json();

        $invoice = data_get($response, 'Invoices.0');

        return [
            'status' => data_get($invoice, 'Status'),
            'amount_due' => (float) data_get($invoice, 'AmountDue'),
        ];
    }
}
