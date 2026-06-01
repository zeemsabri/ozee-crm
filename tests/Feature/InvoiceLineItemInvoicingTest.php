<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectService;
use App\Models\Role;
use App\Models\User;
use App\Services\XeroTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class InvoiceLineItemInvoicingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_store_creates_invoice_items_and_approve_uses_service_line_items(): void
    {
        $user = $this->createSuperAdminUser();
        Sanctum::actingAs($user);

        $client = Client::create([
            'name' => 'Acme Client',
            'email' => 'billing@example.com',
            'xero_contact_id' => 'xero-contact-123',
            'xero_contact_name' => 'Acme Client',
            'xero_contact_email' => 'billing@example.com',
        ]);

        $project = Project::create([
            'name' => 'Acme Website',
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'one_off',
            'services' => ['Website Design', 'SEO'],
            'service_details' => [],
            'total_amount' => 0,
        ]);

        $serviceOne = ProjectService::create([
            'project_id' => $project->id,
            'enquiry_id' => 'service-one-enquiry',
            'service_id' => 'Website Design',
            'description' => 'Landing page build',
            'amount' => 1000,
            'currency' => 'AUD',
            'frequency' => 'one_off',
            'payment_breakdown' => [
                ['label' => 'Payment 1', 'percentage' => 30, 'due_date' => null],
                ['label' => 'Payment 2', 'percentage' => 70, 'due_date' => null],
            ],
            'status' => 'active',
            'service_tracking_type' => 'operational_service',
            'show_on_leads_board' => false,
            'xero_account_code' => '200',
        ]);

        $serviceTwo = ProjectService::create([
            'project_id' => $project->id,
            'enquiry_id' => 'service-two-enquiry',
            'service_id' => 'SEO',
            'description' => 'Monthly optimisation',
            'amount' => 500,
            'currency' => 'AUD',
            'frequency' => 'monthly',
            'payment_breakdown' => [
                ['label' => 'Payment 1', 'percentage' => 100, 'due_date' => null],
            ],
            'status' => 'active',
            'service_tracking_type' => 'operational_service',
            'show_on_leads_board' => false,
            'xero_account_code' => '300',
        ]);

        $response = $this->postJson("/api/projects/{$project->id}/invoices", [
            'client_id' => $client->id,
            'xero_payment_service_ids' => ['ps-stripe-1', 'ps-paypal-1'],
            'line_items' => [
                [
                    'project_service_id' => $serviceOne->id,
                    'milestone_key' => 'service-one-payment-1',
                    'label' => 'Payment 1 - 30%',
                    'quantity' => 1,
                    'unit_price' => 300,
                    'milestone_percentage' => 30,
                    'tax_type' => 'OUTPUT',
                ],
                [
                    'project_service_id' => $serviceTwo->id,
                    'milestone_key' => 'service-two-payment-1',
                    'label' => 'Payment 1 - 100%',
                    'quantity' => 1,
                    'unit_price' => 500,
                    'milestone_percentage' => 100,
                    'tax_type' => 'OUTPUT',
                ],
            ],
        ]);

        $response->assertCreated();
        $invoiceId = $response->json('id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'project_id' => $project->id,
            'client_id' => $client->id,
            'total_amount' => '800.00',
            'status' => 'pending_approval',
        ]);

        $storedInvoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertSame(['ps-stripe-1', 'ps-paypal-1'], $storedInvoice->xero_payment_service_ids);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'project_service_id' => $serviceOne->id,
            'milestone_key' => 'service-one-payment-1',
            'label' => 'Payment 1 - 30%',
            'unit_price' => '300.00',
            'tax_type' => 'OUTPUT',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoiceId,
            'project_service_id' => $serviceTwo->id,
            'milestone_key' => 'service-two-payment-1',
            'label' => 'Payment 1 - 100%',
            'unit_price' => '500.00',
            'tax_type' => 'OUTPUT',
        ]);

        $tokenService = Mockery::mock(XeroTokenService::class);
        $tokenService->shouldReceive('getRuntimeCredentials')->andReturn([
            'access_token' => 'xero-access-token',
            'tenant_id' => 'tenant-123',
            'tenant_name' => 'Demo Tenant',
        ]);
        $this->app->instance(XeroTokenService::class, $tokenService);

        Http::fake([
            'https://api.xero.com/api.xro/2.0/Invoices' => Http::response([
                'Invoices' => [
                    ['InvoiceID' => 'xero-invoice-123'],
                ],
            ], 200),
        ]);

        $approveResponse = $this->postJson("/api/projects/{$project->id}/invoices/{$invoiceId}/approve");
        $approveResponse->assertOk();
        $approveResponse->assertJsonPath('xero_invoice_id', 'xero-invoice-123');

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://api.xero.com/api.xro/2.0/Invoices'
                && $payload['Type'] === 'ACCREC'
                && count($payload['LineItems']) === 2
                && count($payload['PaymentServices']) === 2
                && $payload['PaymentServices'][0]['PaymentServiceID'] === 'ps-stripe-1'
                && $payload['PaymentServices'][1]['PaymentServiceID'] === 'ps-paypal-1'
                && $payload['LineItems'][0]['Description'] === 'Website Design - Payment 1 - 30%'
                && $payload['LineItems'][0]['UnitAmount'] === 300.0
                && $payload['LineItems'][0]['AccountCode'] === '200'
                && $payload['LineItems'][0]['TaxType'] === 'OUTPUT'
                && $payload['LineItems'][1]['Description'] === 'SEO - Payment 1 - 100%'
                && $payload['LineItems'][1]['UnitAmount'] === 500.0
                && $payload['LineItems'][1]['AccountCode'] === '300';
        });

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'authorised',
            'xero_invoice_id' => 'xero-invoice-123',
        ]);
    }

    private function createSuperAdminUser(): User
    {
        $role = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Super admin role for invoice tests',
            'type' => 'system',
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
