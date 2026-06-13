<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class XeroInvoiceService
{
    private const INVOICES_URL = 'https://api.xero.com/api.xro/2.0/Invoices';

    private const ALLOWED_LINE_AMOUNT_TYPES = [
        'Exclusive',
        'Inclusive',
        'NoTax',
    ];

    public function __construct(
        private readonly XeroTokenService $xeroTokenService,
        private readonly XeroContactSyncService $xeroContactSyncService,
    ) {}

    /**
     * Create a Sales Invoice in Xero for the given invoice.
     * @return array{InvoiceID: string, InvoiceNumber: string|null}
     */
    public function createSalesInvoice(Invoice $invoice): array
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $invoice->loadMissing(['client', 'project', 'invoiceItems.projectService']);
        
           $xeroContactId = $this->resolveClientContactId($invoice->client);

        $lineItems = $invoice->invoiceItems->isNotEmpty()
            ? $this->buildLineItemsFromInvoiceItems($invoice, $credentials)
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
            'LineAmountTypes' => $this->normalizeLineAmountType($invoice->line_amount_type),
            'Status' => 'AUTHORISED',
            'LineItems' => $lineItems,
        ];

        if ($invoice->currency) {
            $payload['CurrencyCode'] = $invoice->currency;
        }

        if ($invoice->xero_branding_theme_id) {
            $payload['BrandingThemeID'] = $invoice->xero_branding_theme_id;
        }

        $paymentServiceIds = collect($invoice->xero_payment_service_ids ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values();

        if ($paymentServiceIds->isNotEmpty()) {
            $payload['PaymentServices'] = $paymentServiceIds
                ->map(fn (string $id) => ['PaymentServiceID' => $id])
                ->all();
        }

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->post(self::INVOICES_URL, $payload)
            ->throw()
            ->json();

        $xeroInvoiceId = data_get($response, 'Invoices.0.InvoiceID');
        $xeroInvoiceNumber = data_get($response, 'Invoices.0.InvoiceNumber');

        if (!$xeroInvoiceId) {
            throw new RuntimeException('Failed to create sales invoice in Xero.');
        }

        return [
            'InvoiceID' => $xeroInvoiceId,
            'InvoiceNumber' => $xeroInvoiceNumber,
        ];
    }

    private function resolveClientContactId(Client $client): string
    {
        $existingContactId = trim((string) $client->xero_contact_id);
        if ($this->isGuid($existingContactId)) {
            return $existingContactId;
        }

        $candidate = collect($this->xeroContactSyncService->getContactCandidatesForClient($client))
            ->first(function (array $contact): bool {
                return $this->isGuid((string) ($contact['contact_id'] ?? ''));
            });

        if (! $candidate) {
            $candidate = $this->xeroContactSyncService->createContactForClient($client);
        }

        $resolvedContactId = trim((string) ($candidate['contact_id'] ?? ''));
        if (! $this->isGuid($resolvedContactId)) {
            throw new RuntimeException("Client {$client->name} is not linked to a valid Xero contact.");
        }

        $client->forceFill([
            'xero_contact_id' => $resolvedContactId,
            'xero_contact_name' => (string) ($candidate['name'] ?? $client->xero_contact_name),
            'xero_contact_email' => (string) ($candidate['email'] ?? $client->xero_contact_email),
            'xero_synced_at' => now(),
        ])->save();

        return $resolvedContactId;
    }

    private function isGuid(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        return (bool) preg_match('/^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$/', $value);
    }

    public function sendSalesInvoiceEmail(string $xeroInvoiceId): void
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();
        $url = self::INVOICES_URL . '/' . $xeroInvoiceId . '/Email';

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->retry(
                3,
                fn (int $attempt): int => $attempt * 500,
                function ($exception): bool {
                    if (! $exception instanceof RequestException) {
                        return false;
                    }

                    $status = $exception->response?->status();
                    return in_array($status, [429, 500, 502, 503, 504], true);
                },
                false
            )
            ->post($url);

        if ($response->failed()) {
            $status = $response->status();
            $body = Str::limit((string) $response->body(), 400);
            throw new RuntimeException("Failed to send invoice email via Xero (HTTP {$status}): {$body}");
        }
    }

    protected function buildLineItemsFromInvoiceItems(Invoice $invoice, array $credentials): array
    {
        return $invoice->invoiceItems->map(function ($item) use ($invoice, $credentials) {
            $projectService = $item->projectService;
            $crmService = $projectService->crmService;
            $description = trim((string) ($item->description ?? ''));
            $serviceName = $crmService ? $crmService->name : 'Unknown Service';
            $accountCode = filled($projectService->xero_account_code ?? null)
                ? (string) $projectService->xero_account_code
                : '200';
            $taxType = $this->normalizeInvoiceTaxType($item->tax_type ?? null);
            $projectTrackingOption = trim((string) ($invoice->project->name ?? ''));
            if ($projectTrackingOption === '') {
                $projectTrackingOption = 'Project '.$invoice->project_id;
            }

            $lineItem = [
                'Description' => $description !== ''
                    ? $description
                    : "{$serviceName} - {$item->label}",
                'Quantity' => (float) $item->quantity,
                'UnitAmount' => (float) $item->unit_price,
                'AccountCode' => $accountCode,
                'TaxType' => $taxType,
                'Tracking' => [
                    [
                        'Name' => 'Project',
                        'Option' => $projectTrackingOption,
                    ],
                ],
            ];

            $itemCode = trim((string) ($crmService?->xero_item_code ?? ''));
            if ($itemCode !== '' && $this->isValidItemCodeForTenant($itemCode, $credentials)) {
                $lineItem['ItemCode'] = $itemCode;
            }

            return $lineItem;
        })->values()->all();
    }

    /**
     * @param  array{access_token:string, tenant_id:string, tenant_name:?string}  $credentials
     */
    private function isValidItemCodeForTenant(string $itemCode, array $credentials): bool
    {
        $cacheKey = sprintf('xero.items.codes.%s', $credentials['tenant_id']);

        $codes = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($credentials): array {
            $response = Http::withToken($credentials['access_token'])
                ->withHeaders([
                    'Xero-tenant-id' => $credentials['tenant_id'],
                    'Accept' => 'application/json',
                ])
                ->get('https://api.xero.com/api.xro/2.0/Items')
                ->throw()
                ->json();

            return collect(data_get($response, 'Items', []))
                ->pluck('Code')
                ->filter()
                ->map(fn ($code) => strtoupper((string) $code))
                ->values()
                ->all();
        });

        return in_array(strtoupper($itemCode), $codes, true);
    }

    public function getInvoiceHistory(string $xeroInvoiceId): array
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();
        $url = self::INVOICES_URL . '/' . $xeroInvoiceId . '/History';

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get($url)
            ->throw()
            ->json();

        return data_get($response, 'HistoryRecords', []);
    }

    public function addInvoiceNote(string $xeroInvoiceId, string $note): array
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();
        $url = self::INVOICES_URL . '/' . $xeroInvoiceId . '/History';

        $payload = [
            'HistoryRecords' => [
                [
                    'Details' => $note,
                ],
            ],
        ];

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->post($url, $payload)
            ->throw()
            ->json();

        return data_get($response, 'HistoryRecords', []);
    }

    public function getBrandingThemes(): array
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();
        $url = 'https://api.xero.com/api.xro/2.0/BrandingThemes';

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get($url)
            ->throw()
            ->json();

        return data_get($response, 'BrandingThemes', []);
    }

    public function syncLocalInvoiceFromXero(Invoice $invoice): Invoice
    {
        if (! $invoice->xero_invoice_id) {
            return $invoice->fresh(['client', 'invoiceItems.projectService', 'comments.user', 'files']) ?? $invoice;
        }

        $xeroInvoice = $this->getSalesInvoiceFromXero($invoice->xero_invoice_id);

        DB::transaction(function () use ($invoice, $xeroInvoice): void {
            $invoice->status = $this->mapSalesInvoiceStatus($xeroInvoice);
            $invoice->invoice_number = (string) data_get($xeroInvoice, 'InvoiceNumber', $invoice->invoice_number);
            $invoice->total_amount = (float) data_get($xeroInvoice, 'Total', $invoice->total_amount);
            $invoice->line_amount_type = $this->normalizeLineAmountType(data_get($xeroInvoice, 'LineAmountTypes', $invoice->line_amount_type));

            $currencyCode = data_get($xeroInvoice, 'CurrencyCode');
            if (is_string($currencyCode) && $currencyCode !== '') {
                $invoice->currency = $currencyCode;
            }

            $invoice->save();

            $xeroLineItems = collect(data_get($xeroInvoice, 'LineItems', []))
                ->filter(fn ($lineItem) => is_array($lineItem))
                ->values();

            if ($xeroLineItems->isEmpty()) {
                return;
            }

            $localItems = $invoice->invoiceItems()->orderBy('id')->get();

            foreach ($localItems as $index => $localItem) {
                $xeroLineItem = $xeroLineItems->get($index);
                if (! is_array($xeroLineItem)) {
                    break;
                }

                $quantity = data_get($xeroLineItem, 'Quantity');
                if (is_numeric($quantity)) {
                    $localItem->quantity = (float) $quantity;
                }

                $unitAmount = data_get($xeroLineItem, 'UnitAmount');
                if (is_numeric($unitAmount)) {
                    $localItem->unit_price = (float) $unitAmount;
                }

                $taxType = data_get($xeroLineItem, 'TaxType');
                if (is_string($taxType) && $taxType !== '') {
                    $localItem->tax_type = $this->normalizeInvoiceTaxType($taxType);
                }

                $description = data_get($xeroLineItem, 'Description');
                if (is_string($description) && $description !== '') {
                    $localItem->description = $description;
                }

                if ($localItem->isDirty()) {
                    $localItem->save();
                }
            }
        });

        return $invoice->fresh(['client', 'invoiceItems.projectService', 'comments.user', 'files']) ?? $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSalesInvoiceFromXero(string $xeroInvoiceId): array
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
        if (! is_array($invoice)) {
            throw new RuntimeException('Failed to fetch invoice from Xero.');
        }

        if (strtoupper((string) data_get($invoice, 'Type', '')) !== 'ACCREC') {
            throw new RuntimeException('Xero invoice type is not a sales invoice.');
        }

        return $invoice;
    }

    /**
     * @param array<string, mixed> $xeroInvoice
     */
    private function mapSalesInvoiceStatus(array $xeroInvoice): string
    {
        $status = strtoupper((string) data_get($xeroInvoice, 'Status', ''));

        $amountDue = data_get($xeroInvoice, 'AmountDue');
        $amountPaid = data_get($xeroInvoice, 'AmountPaid');
        if (is_numeric($amountDue) && is_numeric($amountPaid)) {
            if ((float) $amountDue <= 0.00001 && (float) $amountPaid > 0) {
                return 'paid';
            }
        }

        return match ($status) {
            'DRAFT' => 'draft',
            'SUBMITTED' => 'pending_approval',
            'AUTHORISED' => 'authorised',
            'PAID' => 'paid',
            'VOIDED', 'DELETED' => 'voided',
            default => strtolower($status),
        };
    }

    private function normalizeLineAmountType(?string $lineAmountType): string
    {
        $normalized = ucfirst(strtolower(trim((string) $lineAmountType)));

        if (in_array($normalized, self::ALLOWED_LINE_AMOUNT_TYPES, true)) {
            return $normalized;
        }

        if (strtoupper(trim((string) $lineAmountType)) === 'NOTAX') {
            return 'NoTax';
        }

        return 'Exclusive';
    }

    private function normalizeInvoiceTaxType(?string $taxType): string
    {
        $normalized = strtoupper(trim((string) $taxType));

        return match ($normalized) {
            'EXEMPTOUTPUT' => 'EXEMPTOUTPUT',
            'BASEXCLUDED', 'NONE' => 'BASEXCLUDED',
            default => 'OUTPUT',
        };
    }
}
