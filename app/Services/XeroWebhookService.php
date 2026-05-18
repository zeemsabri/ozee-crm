<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XeroWebhookService
{
    private const CONTACTS_URL = 'https://api.xero.com/api.xro/2.0/Contacts';

    private const INVOICES_URL = 'https://api.xero.com/api.xro/2.0/Invoices';

    private const CREDIT_NOTES_URL = 'https://api.xero.com/api.xro/2.0/CreditNotes';

    public function __construct(private readonly XeroTokenService $xeroTokenService) {}

    public function hasValidSignature(string $payload, ?string $signature): bool
    {
        if ($signature === null || trim($signature) === '') {
            return false;
        }

        $key = (string) config('xero.webhookKey', '');
        if ($key === '') {
            return false;
        }

        $expectedWithDecodedKey = base64_encode(hash_hmac('sha256', $payload, base64_decode($key, true) ?: $key, true));
        if (hash_equals($expectedWithDecodedKey, trim($signature))) {
            return true;
        }

        $expectedWithRawKey = base64_encode(hash_hmac('sha256', $payload, $key, true));

        return hash_equals($expectedWithRawKey, trim($signature));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isIntentToReceive(array $payload): bool
    {
        $firstEventType = strtolower((string) data_get($payload, 'events.0.eventType', ''));

        return in_array($firstEventType, ['intenttoreceive', 'intent_to_receive'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{processed:int, ignored:int, errors:int}
     */
    public function process(array $payload): array
    {
        $events = data_get($payload, 'events', []);
        if (! is_array($events)) {
            return ['processed' => 0, 'ignored' => 0, 'errors' => 0];
        }

        $processed = 0;
        $ignored = 0;
        $errors = 0;

        foreach ($events as $event) {
            if (! is_array($event)) {
                $ignored++;
                continue;
            }

            try {
                if ($this->processEvent($event)) {
                    $processed++;
                } else {
                    $ignored++;
                }
            } catch (\Throwable $throwable) {
                $errors++;
                Log::error('Failed processing Xero webhook event.', [
                    'event' => $event,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        return [
            'processed' => $processed,
            'ignored' => $ignored,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function processEvent(array $event): bool
    {
        $resourceId = (string) ($event['resourceId'] ?? '');
        if ($resourceId === '') {
            return false;
        }

        $eventCategory = strtoupper((string) ($event['eventCategory'] ?? ''));
        if ($eventCategory === '') {
            $resourceUrl = strtoupper((string) ($event['resourceUrl'] ?? ''));
            if (str_contains($resourceUrl, '/INVOICES/')) {
                $eventCategory = 'INVOICE';
            } elseif (str_contains($resourceUrl, '/CONTACTS/')) {
                $eventCategory = 'CONTACT';
            } elseif (str_contains($resourceUrl, '/CREDITNOTES/')) {
                $eventCategory = 'CREDIT_NOTE';
            }
        }

        $tenantId = (string) ($event['tenantId'] ?? '');
        if ($tenantId !== '' && ! $this->isCurrentTenant($tenantId)) {
            return false;
        }

        if ($eventCategory === 'INVOICE') {
            return $this->syncInvoiceResource($resourceId);
        }

        if ($eventCategory === 'CONTACT') {
            return $this->syncContactResource($resourceId);
        }

        if ($eventCategory === 'CREDIT_NOTE' || $eventCategory === 'CREDITNOTE') {
            return $this->syncCreditNoteResource($resourceId);
        }

        return false;
    }

    private function isCurrentTenant(string $tenantId): bool
    {
        try {
            $credentials = $this->xeroTokenService->getRuntimeCredentials();

            return $tenantId === $credentials['tenant_id'];
        } catch (\Throwable) {
            return false;
        }
    }

    private function syncInvoiceResource(string $xeroInvoiceId): bool
    {
        $invoiceData = $this->getInvoiceFromXero($xeroInvoiceId);
        if (! is_array($invoiceData)) {
            return false;
        }

        $xeroType = strtoupper((string) data_get($invoiceData, 'Type', ''));

        if ($xeroType === 'ACCREC') {
            $invoice = Invoice::query()->where('xero_invoice_id', $xeroInvoiceId)->first();
            if (! $invoice) {
                return false;
            }

            $invoice->status = $this->mapSalesInvoiceStatus((string) data_get($invoiceData, 'Status', ''));
            $invoice->invoice_number = (string) data_get($invoiceData, 'InvoiceNumber', $invoice->invoice_number);
            $invoice->total_amount = (float) data_get($invoiceData, 'Total', $invoice->total_amount);
            $invoice->save();

            return true;
        }

        if ($xeroType === 'ACCPAY') {
            $bill = Bill::query()->where('xero_invoice_id', $xeroInvoiceId)->first();
            if (! $bill) {
                return false;
            }

            $billStatus = $this->mapBillStatus((string) data_get($invoiceData, 'Status', ''));
            if ($billStatus !== null) {
                $bill->status = $billStatus;
            }
            $bill->amount = (float) data_get($invoiceData, 'Total', $bill->amount);
            $bill->save();

            return true;
        }

        return false;
    }

    private function syncContactResource(string $xeroContactId): bool
    {
        $contactData = $this->getContactFromXero($xeroContactId);
        if (! is_array($contactData)) {
            return false;
        }

        $name = (string) data_get($contactData, 'Name', '');
        $email = (string) data_get($contactData, 'EmailAddress', '');

        $clientUpdated = false;
        $userUpdated = false;

        $clients = Client::query()->where('xero_contact_id', $xeroContactId)->get();
        foreach ($clients as $client) {
            $client->xero_contact_name = $name !== '' ? $name : $client->xero_contact_name;
            $client->xero_contact_email = $email !== '' ? $email : $client->xero_contact_email;
            $client->xero_synced_at = now();

            if ($client->xero_sync_mode === 'xero') {
                if ($name !== '') {
                    $client->name = $name;
                }
                if ($email !== '') {
                    $client->email = $email;
                }
            }

            $client->save();
            $clientUpdated = true;
        }

        $users = User::query()->where('xero_contact_id', $xeroContactId)->get();
        foreach ($users as $user) {
            if ($name !== '') {
                $user->xero_contact_name = $name;
            }
            if ($email !== '') {
                $user->xero_contact_email = $email;
            }
            $user->xero_synced_at = now();
            $user->save();
            $userUpdated = true;
        }

        return $clientUpdated || $userUpdated;
    }

    private function syncCreditNoteResource(string $xeroCreditNoteId): bool
    {
        $creditNote = $this->getCreditNoteFromXero($xeroCreditNoteId);
        if (! is_array($creditNote)) {
            return false;
        }

        $didUpdate = false;

        $contactId = (string) data_get($creditNote, 'Contact.ContactID', '');
        if ($contactId !== '') {
            $didUpdate = $this->syncContactResource($contactId) || $didUpdate;
        }

        $linkedInvoiceId = (string) data_get($creditNote, 'Invoice.InvoiceID', '');
        if ($linkedInvoiceId !== '') {
            $didUpdate = $this->syncInvoiceResource($linkedInvoiceId) || $didUpdate;
        }

        $allocations = data_get($creditNote, 'Allocations', []);
        if (is_array($allocations)) {
            foreach ($allocations as $allocation) {
                $allocatedInvoiceId = (string) data_get($allocation, 'Invoice.InvoiceID', '');
                if ($allocatedInvoiceId !== '') {
                    $didUpdate = $this->syncInvoiceResource($allocatedInvoiceId) || $didUpdate;
                }
            }
        }

        return $didUpdate;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getInvoiceFromXero(string $xeroInvoiceId): ?array
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

        return is_array($invoice) ? $invoice : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getContactFromXero(string $xeroContactId): ?array
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get(self::CONTACTS_URL . '/' . $xeroContactId)
            ->throw()
            ->json();

        $contact = data_get($response, 'Contacts.0');

        return is_array($contact) ? $contact : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getCreditNoteFromXero(string $xeroCreditNoteId): ?array
    {
        $credentials = $this->xeroTokenService->getRuntimeCredentials();

        $response = Http::withToken($credentials['access_token'])
            ->withHeaders([
                'Xero-tenant-id' => $credentials['tenant_id'],
                'Accept' => 'application/json',
            ])
            ->get(self::CREDIT_NOTES_URL . '/' . $xeroCreditNoteId)
            ->throw()
            ->json();

        $creditNote = data_get($response, 'CreditNotes.0');

        return is_array($creditNote) ? $creditNote : null;
    }

    private function mapSalesInvoiceStatus(string $xeroStatus): string
    {
        return match (strtoupper($xeroStatus)) {
            'DRAFT' => 'draft',
            'SUBMITTED' => 'pending_approval',
            'AUTHORISED' => 'authorised',
            'PAID' => 'paid',
            'VOIDED', 'DELETED' => 'voided',
            default => strtolower($xeroStatus),
        };
    }

    private function mapBillStatus(string $xeroStatus): ?BillStatus
    {
        return match (strtoupper($xeroStatus)) {
            'DRAFT' => BillStatus::PendingApproval,
            'SUBMITTED' => BillStatus::PendingApproval,
            'AUTHORISED' => BillStatus::Approved,
            'PAID' => BillStatus::Paid,
            'VOIDED', 'DELETED' => BillStatus::Void,
            default => null,
        };
    }
}
