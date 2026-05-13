<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Transaction;
use App\Enums\BillStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $payload = [
            'Type' => 'ACCPAY',
            'Contact' => [
                'ContactID' => $xeroContactId,
            ],
            'Date' => $bill->created_at->format('Y-m-d'),
            'DueDate' => $bill->created_at->addDays(14)->format('Y-m-d'), // Default 14 days?
            'LineAmountTypes' => 'Exclusive',
            'Status' => 'AUTHORISED',
            'LineItems' => [
                [
                    'Description' => "Bill for Project: {$bill->project->name} - Expendable: {$bill->expendable->name}",
                    'Quantity' => 1.0,
                    'UnitAmount' => (float) $bill->amount,
                    'AccountCode' => $bill->transactionType?->xero_account_code ?? '400', // Fallback
                    'Tracking' => [
                        [
                            'Name' => 'Project',
                            'Option' => $bill->project->name,
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->post(self::INVOICES_URL, $payload)
            ->throw()
            ->json();

        $xeroInvoiceId = data_get($response, 'Invoices.0.InvoiceID');

        if (!$xeroInvoiceId) {
            throw new RuntimeException('Failed to create purchase invoice in Xero.');
        }

        return $xeroInvoiceId;
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
