<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_the_authenticated_users_attendance_summary(): void
    {
        $user = User::factory()->create(['timezone' => 'UTC']);
        $otherUser = User::factory()->create(['timezone' => 'UTC']);

        Sanctum::actingAs($user);

        UserActivity::create([
            'user_id' => $user->id,
            'domain' => 'example.com',
            'url' => 'https://example.com',
            'title' => 'Work tab',
            'duration' => 7200,
            'recorded_at' => '2026-04-09 09:00:00',
            'idle_state' => 'active',
        ]);

        UserActivity::create([
            'user_id' => $user->id,
            'domain' => 'example.com',
            'url' => 'https://example.com/idle',
            'title' => 'Idle tab',
            'duration' => 1800,
            'recorded_at' => '2026-04-09 11:30:00',
            'idle_state' => 'idle',
        ]);

        UserActivity::create([
            'user_id' => $user->id,
            'domain' => 'example.com',
            'url' => 'https://example.com/unlinked',
            'title' => 'Unlinked tab',
            'duration' => 900,
            'recorded_at' => '2026-04-09 12:15:00',
            'idle_state' => 'active',
            'task_id' => null,
        ]);

        UserActivity::create([
            'user_id' => $otherUser->id,
            'domain' => 'example.org',
            'url' => 'https://example.org',
            'title' => 'Other user',
            'duration' => 14400,
            'recorded_at' => '2026-04-09 08:00:00',
            'idle_state' => 'active',
        ]);

        UserAvailability::create([
            'user_id' => $user->id,
            'date' => '2026-04-09',
            'is_available' => true,
            'time_slots' => [
                ['start_time' => '09:00', 'end_time' => '13:00'],
            ],
        ]);

        $response = $this->getJson('/api/me/attendance?date_start=2026-04-09&date_end=2026-04-09');

        $response->assertOk()
            ->assertJsonPath('summary.online_minutes', 165.0)
            ->assertJsonPath('summary.active_minutes', 135.0)
            ->assertJsonPath('summary.idle_minutes', 30.0)
            ->assertJsonPath('summary.unlinked_minutes', 15.0)
            ->assertJsonPath('summary.unlinked_percentage', 9.1)
            ->assertJsonPath('summary.unlinked_needs_attention', true)
            ->assertJsonPath('summary.expected_minutes', 240)
            ->assertJsonPath('summary.shortfall_minutes', 75.0)
            ->assertJsonCount(1, 'days')
            ->assertJsonPath('days.0.status', 'short')
            ->assertJsonPath('days.0.unlinked_minutes', 15.0)
            ->assertJsonPath('days.0.unlinked_percentage', 9.1)
            ->assertJsonPath('days.0.unlinked_windows.0.label', '12:15 PM - 12:30 PM');
    }

    public function test_it_returns_validation_errors_for_invalid_date_ranges(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me/attendance?date_start=2026-04-10&date_end=2026-04-09');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_end']);
    }
}