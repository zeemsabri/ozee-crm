<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_flags_the_user_when_the_latest_reported_extension_version_is_outdated(): void
    {
        $user = User::factory()->create([
            'extension_mandatory' => true,
            'is_online' => true,
        ]);

        Sanctum::actingAs($user);

        UserActivity::create([
            'user_id' => $user->id,
            'domain' => 'example.com',
            'url' => 'https://example.com',
            'title' => 'Old extension activity',
            'duration' => 300,
            'recorded_at' => now()->subMinutes(10),
            'idle_state' => 'active',
            'metadata' => [
                'extension_version' => '0.5.6',
            ],
        ]);

        $response = $this->getJson('/api/me/status');

        $response->assertOk()
            ->assertJsonPath('reported_extension_version', '0.5.6')
            ->assertJsonPath('required_extension_version', '0.5.7')
            ->assertJsonPath('extension_version_missing', false)
            ->assertJsonPath('extension_version_outdated', true)
            ->assertJsonPath('extension_reminder_reason', 'outdated_version');
    }

    public function test_it_flags_the_user_when_no_extension_version_has_been_reported(): void
    {
        $user = User::factory()->create([
            'extension_mandatory' => true,
            'is_online' => true,
        ]);

        Sanctum::actingAs($user);

        UserActivity::create([
            'user_id' => $user->id,
            'domain' => 'example.com',
            'url' => 'https://example.com',
            'title' => 'Activity without version metadata',
            'duration' => 300,
            'recorded_at' => now()->subMinutes(10),
            'idle_state' => 'active',
            'metadata' => [
                'event_type' => 'heartbeat',
            ],
        ]);

        $response = $this->getJson('/api/me/status');

        $response->assertOk()
            ->assertJsonPath('reported_extension_version', null)
            ->assertJsonPath('required_extension_version', '0.5.7')
            ->assertJsonPath('extension_version_missing', true)
            ->assertJsonPath('extension_version_outdated', false)
            ->assertJsonPath('extension_reminder_reason', 'missing_version');
    }

    public function test_force_refresh_recomputes_the_cached_extension_version_status(): void
    {
        $user = User::factory()->create([
            'extension_mandatory' => true,
            'is_online' => true,
        ]);

        Sanctum::actingAs($user);

        UserActivity::create([
            'user_id' => $user->id,
            'domain' => 'example.com',
            'url' => 'https://example.com/old',
            'title' => 'Old activity',
            'duration' => 300,
            'recorded_at' => now()->subMinutes(20),
            'idle_state' => 'active',
            'metadata' => [
                'extension_version' => '0.5.6',
            ],
        ]);

        $this->getJson('/api/me/status')
            ->assertOk()
            ->assertJsonPath('extension_reminder_reason', 'outdated_version');

        UserActivity::create([
            'user_id' => $user->id,
            'domain' => 'example.com',
            'url' => 'https://example.com/new',
            'title' => 'New activity',
            'duration' => 300,
            'recorded_at' => now()->subMinutes(5),
            'idle_state' => 'active',
            'metadata' => [
                'extension_version' => '0.5.7',
            ],
        ]);

        $this->getJson('/api/me/status')
            ->assertOk()
            ->assertJsonPath('reported_extension_version', '0.5.6')
            ->assertJsonPath('extension_reminder_reason', 'outdated_version');

        $this->getJson('/api/me/status?force=1')
            ->assertOk()
            ->assertJsonPath('reported_extension_version', '0.5.7')
            ->assertJsonPath('extension_version_outdated', false)
            ->assertJsonPath('extension_reminder_reason', null);
    }
}