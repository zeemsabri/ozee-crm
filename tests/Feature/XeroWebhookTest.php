<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Models\XeroConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XeroWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookSecret = 'test-webhook-secret';
        config()->set('xero.webhookKey', base64_encode($this->webhookSecret));
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'events' => [
                [
                    'resourceId' => 'abc-123',
                    'eventCategory' => 'INVOICE',
                    'tenantId' => 'tenant-1',
                ],
            ],
        ];

        $response = $this->postWebhook($payload, signature: 'invalid-signature');

        $response->assertStatus(401);
    }

    public function test_webhook_accepts_intent_to_receive_with_valid_signature(): void
    {
        $payload = [
            'events' => [
                [
                    'eventType' => 'IntentToReceive',
                    'resourceId' => 'intent-resource',
                    'eventCategory' => 'INVOICE',
                ],
            ],
        ];

        $response = $this->postWebhook($payload);

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'intent' => true,
            ]);
    }

    public function test_invoice_event_updates_local_invoice(): void
    {
        $connection = $this->createConnectedXero();

        $client = Client::create([
            'name' => 'Client A',
            'email' => 'client-a@example.com',
        ]);

        $project = Project::create([
            'name' => 'Project A',
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'total_amount' => 100.00,
            'status' => 'authorised',
            'xero_invoice_id' => 'xero-invoice-001',
        ]);

        Http::fake([
            'https://api.xero.com/api.xro/2.0/Invoices/xero-invoice-001' => Http::response([
                'Invoices' => [[
                    'InvoiceID' => 'xero-invoice-001',
                    'Type' => 'ACCREC',
                    'Status' => 'PAID',
                    'InvoiceNumber' => 'INV-1001',
                    'Total' => 250.50,
                ]],
            ], 200),
        ]);

        $payload = [
            'events' => [
                [
                    'resourceId' => 'xero-invoice-001',
                    'eventCategory' => 'INVOICE',
                    'tenantId' => $connection->selected_tenant_id,
                ],
            ],
        ];

        $response = $this->postWebhook($payload);

        $response->assertOk()->assertJsonPath('summary.processed', 1);

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame('INV-1001', $invoice->invoice_number);
        $this->assertSame('250.50', (string) $invoice->total_amount);
    }

    public function test_contact_event_updates_client_and_user(): void
    {
        $connection = $this->createConnectedXero();

        $client = Client::create([
            'name' => 'Old Client Name',
            'email' => 'old-client@example.com',
            'xero_contact_id' => 'xero-contact-001',
            'xero_sync_mode' => 'xero',
        ]);

        $user = User::factory()->create([
            'xero_contact_id' => 'xero-contact-001',
        ]);

        Http::fake([
            'https://api.xero.com/api.xro/2.0/Contacts/xero-contact-001' => Http::response([
                'Contacts' => [[
                    'ContactID' => 'xero-contact-001',
                    'Name' => 'Updated Contact Name',
                    'EmailAddress' => 'updated-contact@example.com',
                ]],
            ], 200),
        ]);

        $payload = [
            'events' => [
                [
                    'resourceId' => 'xero-contact-001',
                    'eventCategory' => 'CONTACT',
                    'tenantId' => $connection->selected_tenant_id,
                ],
            ],
        ];

        $response = $this->postWebhook($payload);

        $response->assertOk()->assertJsonPath('summary.processed', 1);

        $client->refresh();
        $user->refresh();

        $this->assertSame('Updated Contact Name', $client->name);
        $this->assertSame('updated-contact@example.com', $client->email);
        $this->assertSame('Updated Contact Name', $client->xero_contact_name);
        $this->assertSame('updated-contact@example.com', $client->xero_contact_email);

        $this->assertSame('Updated Contact Name', $user->xero_contact_name);
        $this->assertSame('updated-contact@example.com', $user->xero_contact_email);
    }

    public function test_credit_note_event_refreshes_related_contact_and_invoice(): void
    {
        $connection = $this->createConnectedXero();

        $client = Client::create([
            'name' => 'Before Credit Note',
            'email' => 'before@example.com',
            'xero_contact_id' => 'xero-contact-002',
            'xero_sync_mode' => 'xero',
        ]);

        $project = Project::create([
            'name' => 'Project B',
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'project_id' => $project->id,
            'total_amount' => 300.00,
            'status' => 'authorised',
            'xero_invoice_id' => 'xero-invoice-002',
        ]);

        Http::fake([
            'https://api.xero.com/api.xro/2.0/CreditNotes/xero-credit-note-001' => Http::response([
                'CreditNotes' => [[
                    'CreditNoteID' => 'xero-credit-note-001',
                    'Contact' => [
                        'ContactID' => 'xero-contact-002',
                    ],
                    'Invoice' => [
                        'InvoiceID' => 'xero-invoice-002',
                    ],
                ]],
            ], 200),
            'https://api.xero.com/api.xro/2.0/Contacts/xero-contact-002' => Http::response([
                'Contacts' => [[
                    'ContactID' => 'xero-contact-002',
                    'Name' => 'After Credit Note',
                    'EmailAddress' => 'after@example.com',
                ]],
            ], 200),
            'https://api.xero.com/api.xro/2.0/Invoices/xero-invoice-002' => Http::response([
                'Invoices' => [[
                    'InvoiceID' => 'xero-invoice-002',
                    'Type' => 'ACCREC',
                    'Status' => 'VOIDED',
                    'InvoiceNumber' => 'INV-VOID-002',
                    'Total' => 280.00,
                ]],
            ], 200),
        ]);

        $payload = [
            'events' => [
                [
                    'resourceId' => 'xero-credit-note-001',
                    'eventCategory' => 'CREDIT_NOTE',
                    'tenantId' => $connection->selected_tenant_id,
                ],
            ],
        ];

        $response = $this->postWebhook($payload);

        $response->assertOk()->assertJsonPath('summary.processed', 1);

        $client->refresh();
        $invoice->refresh();

        $this->assertSame('After Credit Note', $client->name);
        $this->assertSame('after@example.com', $client->email);
        $this->assertSame('voided', $invoice->status);
        $this->assertSame('INV-VOID-002', $invoice->invoice_number);
        $this->assertSame('280.00', (string) $invoice->total_amount);
    }

    private function createConnectedXero(): XeroConnection
    {
        return XeroConnection::create([
            'provider' => XeroConnection::PROVIDER,
            'status' => 'connected',
            'selected_tenant_id' => 'tenant-001',
            'selected_tenant_name' => 'Tenant One',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'access_token_expires_at' => now()->addMinutes(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'last_connected_at' => now(),
            'last_refreshed_at' => now(),
        ]);
    }

    private function postWebhook(array $payload, ?string $signature = null)
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $signature ??= base64_encode(hash_hmac('sha256', $json, $this->webhookSecret, true));

        return $this->call(
            'POST',
            '/api/xero/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_XERO_SIGNATURE' => $signature,
            ],
            $json
        );
    }
}
