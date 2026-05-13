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

        $invoice->loadMissing(['client', 'project', 'invoiceItems.projectService']);
        
        $xeroContactId = $invoice->client->xero_contact_id;
        if (!$xeroContactId) {
             throw new RuntimeException("Client {$invoice->client->name} is not linked to Xero.");
        }

        $lineItems = $invoice->invoiceItems->isNotEmpty()
            ? $this->buildLineItemsFromInvoiceItems($invoice)
            : [[
                'Description' => "Invoice for Project: {$invoice->project->name}",
                'Quantity' => 1.0,
                'UnitAmount' => (float) $invoice->total_amount,
                'AccountCode' => '200',
                'Tracking' => [[
                    'Name' => 'Project',
                    'Option' => $invoice->project->name,
                ]],
            ]];

        $payload = [
            'Type' => 'ACCREC',
            'Contact' => [
                'ContactID' => $xeroContactId,
            ],
            'Date' => $invoice->created_at->format('Y-m-d'),
            'DueDate' => $invoice->created_at->copy()->addDays(30)->format('Y-m-d'),
            'LineAmountTypes' => 'Exclusive',
            'Status' => 'AUTHORISED',
            'LineItems' => $lineItems,
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

    protected function buildLineItemsFromInvoiceItems(Invoice $invoice): array
    {
        return $invoice->invoiceItems->map(function ($item) use ($invoice) {
            $projectService = $item->projectService;
            $description = trim((string) ($item->description ?? ''));

            return [
                'Description' => $description !== ''
                    ? $description
                    : "{$projectService->service_id} - {$item->label}",
                'Quantity' => (float) $item->quantity,
                'UnitAmount' => (float) $item->unit_price,
                'AccountCode' => $projectService->xero_account_code ?? '200',
                'TaxType' => $item->tax_type ?? 'OUTPUT',
                'Tracking' => [
                    [
                        'Name' => 'Project',
                        'Option' => $invoice->project->name,
                    ],
                ],
            ];
        })->values()->all();
    }
}
