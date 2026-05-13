<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XeroInvoiceService
{
    private const INVOICES_URL = 'https://api.xero.com/api.xro/2.0/Invoices';

    public function __construct(private readonly XeroTokenService $xeroTokenService) {}

    /**
     * Create a Sales Invoice in Xero for the given invoice.
     */
    public function createSalesInvoice(Invoice $invoice): string
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();
        
        $xeroContactId = $invoice->client->xero_contact_id;
        if (!$xeroContactId) {
             throw new RuntimeException("Client {$invoice->client->name} is not linked to Xero.");
        }

        $payload = [
            'Type' => 'ACCREC',
            'Contact' => [
                'ContactID' => $xeroContactId,
            ],
            'Date' => $invoice->created_at->format('Y-m-d'),
            'DueDate' => $invoice->created_at->addDays(30)->format('Y-m-d'), // Default 30 days
            'LineAmountTypes' => 'Exclusive',
            'Status' => 'AUTHORISED',
            'LineItems' => [
                [
                    'Description' => "Invoice for Project: {$invoice->project->name}",
                    'Quantity' => 1.0,
                    'UnitAmount' => (float) $invoice->total_amount,
                    'AccountCode' => '200', // Typical Sales account code
                    'Tracking' => [
                        [
                            'Name' => 'Project',
                            'Option' => $invoice->project->name,
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
            throw new RuntimeException('Failed to create sales invoice in Xero.');
        }

        return $xeroInvoiceId;
    }
}
