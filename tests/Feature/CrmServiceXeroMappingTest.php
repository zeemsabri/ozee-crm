<?php

namespace Tests\Feature;

use App\Models\CrmService;
use App\Models\Role;
use App\Models\User;
use App\Services\XeroTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class CrmServiceXeroMappingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_fetch_xero_accounts_filtered_by_category(): void
    {
        $user = $this->createSuperAdminUser();
        Sanctum::actingAs($user);

        $tokenService = Mockery::mock(XeroTokenService::class);
        $tokenService->shouldReceive('getRuntimeCredentials')->andReturn([
            'access_token' => 'xero-access-token',
            'tenant_id' => 'tenant-123',
            'tenant_name' => 'Demo Tenant',
        ]);
        $this->app->instance(XeroTokenService::class, $tokenService);

        Http::fake([
            'https://api.xero.com/api.xro/2.0/Accounts' => Http::response([
                'Accounts' => [
                    ['Code' => '200', 'Name' => 'Sales', 'Type' => 'SALES', 'Status' => 'ACTIVE'],
                    ['Code' => '400', 'Name' => 'Advertising', 'Type' => 'EXPENSE', 'Status' => 'ACTIVE'],
                ],
            ], 200),
        ]);

        // Test revenue filter
        $response = $this->getJson(route('api.xero.accounts', ['category' => 'revenue']));
        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.code', '200');

        // Test expense filter
        $response = $this->getJson(route('api.xero.accounts', ['category' => 'expense']));
        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.code', '400');
    }

    public function test_can_update_single_crm_service_xero_account(): void
    {
        $user = $this->createSuperAdminUser();
        Sanctum::actingAs($user);

        $service = CrmService::create([
            'name' => 'Website Development',
        ]);

        $response = $this->putJson("/api/crm-services/{$service->id}", [
            'default_xero_account_code' => '200',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('crm_services', [
            'id' => $service->id,
            'default_xero_account_code' => '200',
        ]);
    }

    public function test_can_bulk_update_crm_services(): void
    {
        $user = $this->createSuperAdminUser();
        Sanctum::actingAs($user);

        $serviceOne = CrmService::create(['name' => 'SEO']);
        $serviceTwo = CrmService::create(['name' => 'PPC']);

        $response = $this->putJson('/api/crm-services/bulk', [
            'ids' => [$serviceOne->id, $serviceTwo->id],
            'default_xero_account_code' => '200',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('crm_services', [
            'id' => $serviceOne->id,
            'default_xero_account_code' => '200',
        ]);
        $this->assertDatabaseHas('crm_services', [
            'id' => $serviceTwo->id,
            'default_xero_account_code' => '200',
        ]);
    }

    private function createSuperAdminUser(): User
    {
        $role = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Super admin role',
            'type' => 'system',
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
