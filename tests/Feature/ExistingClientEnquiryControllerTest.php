<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExistingClientEnquiryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_returns_enquiry_tracked_services(): void
    {
        $user = $this->createManagerUser();
        Sanctum::actingAs($user);

        $projectId = $this->createProjectWithServices([
            [
                'enquiry_id' => (string) Str::uuid(),
                'service_id' => 'SEO Retainer',
                'description' => 'Existing operational service',
                'service_tracking_type' => 'operational_service',
                'show_on_leads_board' => false,
                'enquiry_status' => null,
            ],
            [
                'enquiry_id' => 'enquiry-visible-1',
                'service_id' => 'Flyer Design',
                'description' => 'Client asked for a quote',
                'service_tracking_type' => 'client_enquiry',
                'show_on_leads_board' => true,
                'enquiry_status' => 'pending_quote',
                'enquiry_created_at' => now()->toDateTimeString(),
                'enquiry_updated_at' => now()->toDateTimeString(),
            ],
        ]);

        $response = $this->getJson('/api/existing-client-enquiries?per_page=1000&page=1');

        $response->assertOk();
        $response->assertJsonFragment([
            'enquiry_id' => 'enquiry-visible-1',
            'service_name' => 'Flyer Design',
            'project_id' => $projectId,
        ]);
        $response->assertJsonMissing(['service_name' => 'SEO Retainer']);
    }

    public function test_store_creates_client_enquiry_in_project_services_table(): void
    {
        $user = $this->createManagerUser();
        Sanctum::actingAs($user);

        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Acme Client',
            'email' => 'acme@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Existing Website',
            'client_id' => $clientId,
            'status' => 'active',
            'payment_type' => 'one_off',
            'services' => json_encode([]),
            'service_details' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/existing-client-enquiries', [
            'project_id' => $projectId,
            'client_id' => $clientId,
            'service_id' => 'Brochure Design',
            'description' => 'Need a new brochure for existing brand campaign.',
            'enquiry_status' => 'pending_quote',
            'show_on_leads_board' => true,
            'amount' => 250,
            'frequency' => 'one_off',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.card_type', 'existing_client_enquiry');
        $response->assertJsonPath('data.service_name', 'Brochure Design');

        $serviceRows = DB::table('project_services')->where('project_id', $projectId)->get()->toArray();

        $this->assertCount(1, $serviceRows);
        $this->assertSame('client_enquiry', $serviceRows[0]->service_tracking_type);
        $this->assertSame(1, (int) $serviceRows[0]->show_on_leads_board);
        $this->assertSame('pending_quote', $serviceRows[0]->enquiry_status);
    }

    public function test_convert_creates_task_and_marks_enquiry_as_converted(): void
    {
        $user = $this->createManagerUser();
        Sanctum::actingAs($user);

        $projectId = $this->createProjectWithServices([
            [
                'enquiry_id' => 'approved-enquiry-1',
                'service_id' => 'Landing Page Refresh',
                'description' => 'Approved by client and ready to start.',
                'service_tracking_type' => 'client_enquiry',
                'show_on_leads_board' => true,
                'enquiry_status' => 'approved',
                'enquiry_created_at' => now()->subDay()->toDateTimeString(),
                'enquiry_updated_at' => now()->subDay()->toDateTimeString(),
            ],
        ]);

        $response = $this->postJson('/api/existing-client-enquiries/approved-enquiry-1/convert', [
            'project_id' => $projectId,
            'conversion_type' => 'task',
        ]);

        $response->assertOk();
        $response->assertJsonPath('created.type', 'task');

        $this->assertDatabaseHas('tasks', [
            'name' => 'Landing Page Refresh',
            'source' => 'existing_client_enquiry',
            'source_id' => 'approved-enquiry-1',
        ]);

        $serviceRow = DB::table('project_services')->where('project_id', $projectId)->where('enquiry_id', 'approved-enquiry-1')->first();
        $this->assertNotNull($serviceRow);
        $this->assertSame('converted_to_service', $serviceRow->enquiry_status);

        $meta = json_decode((string) $serviceRow->enquiry_meta, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('task', $meta['conversion']['type'] ?? null);
    }

    protected function createManagerUser(): User
    {
        $role = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Manager role for tests',
            'type' => 'system',
        ]);

        foreach (['manage_projects', 'view_project_financial', 'manage_project_financial', 'create_projects'] as $slug) {
            $permission = Permission::create([
                'name' => ucfirst(str_replace('_', ' ', $slug)),
                'slug' => $slug,
                'description' => $slug,
                'category' => 'tests',
            ]);
            $role->assignPermission($permission);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }

    protected function createProjectWithServices(array $serviceDetails): int
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Existing Client',
            'email' => 'client'.uniqid().'@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $normalized = collect($serviceDetails)->map(function (array $detail) {
            return array_merge([
                'amount' => 0,
                'currency' => 'USD',
                'frequency' => 'one_off',
                'start_date' => null,
                'payment_breakdown' => [['label' => 'Payment 1', 'percentage' => 100, 'due_date' => null]],
                'description' => '',
                'service_tracking_type' => 'operational_service',
                'show_on_leads_board' => false,
                'enquiry_status' => null,
                'enquiry_meta' => [],
            ], $detail);
        })->values()->all();

        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Primary Project',
            'client_id' => $clientId,
            'status' => 'active',
            'payment_type' => 'one_off',
            'services' => json_encode(array_values(array_unique(array_column($normalized, 'service_id')))),
            'service_details' => json_encode($normalized),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($normalized as $detail) {
            DB::table('project_services')->insert([
                'project_id' => $projectId,
                'enquiry_id' => $detail['enquiry_id'] ?? (string) Str::uuid(),
                'service_id' => $detail['service_id'],
                'description' => $detail['description'] ?? null,
                'amount' => $detail['amount'] ?? 0,
                'currency' => $detail['currency'] ?? 'USD',
                'frequency' => $detail['frequency'] ?? 'one_off',
                'start_date' => $detail['start_date'] ?? null,
                'payment_breakdown' => json_encode($detail['payment_breakdown'] ?? [], JSON_THROW_ON_ERROR),
                'status' => 'active',
                'service_tracking_type' => $detail['service_tracking_type'] ?? 'operational_service',
                'show_on_leads_board' => (bool) ($detail['show_on_leads_board'] ?? false),
                'enquiry_status' => $detail['enquiry_status'] ?? null,
                'enquiry_created_at' => $detail['enquiry_created_at'] ?? null,
                'enquiry_updated_at' => $detail['enquiry_updated_at'] ?? null,
                'enquiry_meta' => json_encode($detail['enquiry_meta'] ?? [], JSON_THROW_ON_ERROR),
                'xero_account_code' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $projectId;
    }
}