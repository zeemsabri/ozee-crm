<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_a_user_online_via_api_key(): void
    {
        $user = User::factory()->create([
            'api_key' => 'presence-test-key',
            'is_online' => false,
        ]);

        $response = $this->withHeaders([
            'X-API-KEY' => $user->api_key,
        ])->postJson('/api/presence/status', [
            'status' => 'online',
            'reason' => 'extension started',
            'metadata' => [
                'extension_version' => '1.2.3',
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'online',
                'is_online' => true,
                'changed' => true,
            ]);

        $user->refresh();

        $this->assertTrue($user->is_online);
        $this->assertSame('presence_endpoint', data_get($user->online_data, 'source'));
        $this->assertSame('extension started', data_get($user->online_data, 'reason'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'online_status',
            'description' => 'User went online',
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);
    }

    public function test_it_sets_a_user_offline_via_api_key(): void
    {
        $user = User::factory()->create([
            'api_key' => 'presence-test-key',
            'is_online' => true,
            'online_data' => ['source' => 'activity_data'],
        ]);

        $response = $this->withHeaders([
            'X-API-KEY' => $user->api_key,
        ])->postJson('/api/presence/status', [
            'status' => 'inactive',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'offline',
                'is_online' => false,
                'changed' => true,
            ]);

        $this->assertFalse($user->fresh()->is_online);
    }

    public function test_it_requires_a_valid_status_value(): void
    {
        $user = User::factory()->create([
            'api_key' => 'presence-test-key',
        ]);

        $response = $this->withHeaders([
            'X-API-KEY' => $user->api_key,
        ])->postJson('/api/presence/status', [
            'status' => 'paused',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_it_requires_an_api_key(): void
    {
        $response = $this->postJson('/api/presence/status', [
            'status' => 'online',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API Key is missing.',
            ]);
    }
}