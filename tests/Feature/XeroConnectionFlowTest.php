<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\XeroConnection;
use App\Models\XeroTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XeroConnectionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('xero.clientId', 'test-client-id');
        config()->set('xero.clientSecret', 'test-client-secret');
        config()->set('xero.redirectUri', 'http://localhost/xero/callback');
        config()->set('xero.landingUri', '/admin/xero');
        config()->set('xero.scopes', 'openid email profile offline_access accounting.settings accounting.contacts');
    }

    public function test_super_admin_can_start_xero_connect_flow(): void
    {
        $user = $this->makeUserWithRole('super-admin');

        $response = $this->actingAs($user)->get(route('admin.xero.connect'));

        $response->assertRedirectContains('https://login.xero.com/identity/connect/authorize');
        $this->assertSame($user->id, session('xero_oauth_user_id'));
        $this->assertNotEmpty(session('xero_oauth_state'));
    }

    public function test_non_super_admin_cannot_start_xero_connect_flow(): void
    {
        $user = $this->makeUserWithRole('manager');

        $this->actingAs($user)
            ->get(route('admin.xero.connect'))
            ->assertForbidden();
    }

    public function test_callback_rejects_invalid_state(): void
    {
        $user = $this->makeUserWithRole('super-admin');

        $this->actingAs($user)
            ->withSession([
                'xero_oauth_state' => 'expected-state',
                'xero_oauth_user_id' => $user->id,
            ])
            ->get(route('xero.callback', [
                'code' => 'auth-code',
                'state' => 'wrong-state',
            ]))
            ->assertForbidden();
    }

    public function test_callback_stores_connection_and_single_tenant(): void
    {
        $user = $this->makeUserWithRole('super-admin');

        Http::fake([
            'https://identity.xero.com/connect/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-123',
                'id_token' => 'id-token-123',
                'expires_in' => 1800,
                'scope' => 'offline_access accounting.contacts',
                'token_type' => 'Bearer',
            ], 200),
            'https://api.xero.com/connections' => Http::response([
                [
                    'id' => 'connection-id-1',
                    'authEventId' => 'auth-event-1',
                    'tenantId' => 'tenant-id-1',
                    'tenantType' => 'ORGANISATION',
                    'tenantName' => 'Demo Org',
                    'createdDateUtc' => '2026-05-12T00:00:00.0000000',
                    'updatedDateUtc' => '2026-05-12T00:00:00.0000000',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'xero_oauth_state' => 'valid-state',
                'xero_oauth_user_id' => $user->id,
            ])
            ->get(route('xero.callback', [
                'code' => 'auth-code',
                'state' => 'valid-state',
            ]));

        $response->assertRedirect(route('admin.xero.index'));

        $this->assertDatabaseHas('xero_connections', [
            'provider' => 'xero',
            'connected_by_user_id' => $user->id,
            'status' => 'connected',
            'selected_tenant_id' => 'tenant-id-1',
            'selected_tenant_name' => 'Demo Org',
        ]);

        $this->assertDatabaseHas('xero_tenants', [
            'tenant_id' => 'tenant-id-1',
            'tenant_name' => 'Demo Org',
            'is_selected' => 1,
        ]);
    }

    public function test_super_admin_can_select_tenant_after_callback(): void
    {
        $user = $this->makeUserWithRole('super-admin');

        $connection = XeroConnection::create([
            'provider' => XeroConnection::PROVIDER,
            'connected_by_user_id' => $user->id,
            'status' => 'pending_tenant_selection',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'access_token_expires_at' => now()->addMinutes(30),
            'refresh_token_expires_at' => now()->addDays(60),
            'last_connected_at' => now(),
            'last_refreshed_at' => now(),
        ]);

        XeroTenant::create([
            'xero_connection_id' => $connection->id,
            'tenant_id' => 'tenant-one',
            'tenant_name' => 'Tenant One',
            'tenant_type' => 'ORGANISATION',
        ]);

        XeroTenant::create([
            'xero_connection_id' => $connection->id,
            'tenant_id' => 'tenant-two',
            'tenant_name' => 'Tenant Two',
            'tenant_type' => 'ORGANISATION',
        ]);

        $response = $this->actingAs($user)->post(route('admin.xero.select-tenant'), [
            'tenant_id' => 'tenant-two',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('xero_connections', [
            'id' => $connection->id,
            'status' => 'connected',
            'selected_tenant_id' => 'tenant-two',
            'selected_tenant_name' => 'Tenant Two',
        ]);

        $this->assertDatabaseHas('xero_tenants', [
            'xero_connection_id' => $connection->id,
            'tenant_id' => 'tenant-two',
            'is_selected' => 1,
        ]);
    }

    private function makeUserWithRole(string $roleSlug): User
    {
        $role = Role::create([
            'name' => ucfirst(str_replace('-', ' ', $roleSlug)),
            'slug' => $roleSlug,
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}