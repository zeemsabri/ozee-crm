<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskIndexFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_active_tasks_without_requiring_due_until(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        [$projectId, $milestoneId, $taskTypeId] = $this->createProjectTaskContext($user->id);

        $todoId = $this->createTask([
            'name' => 'Todo future task',
            'status' => 'To Do',
            'assigned_to_user_id' => $user->id,
            'milestone_id' => $milestoneId,
            'task_type_id' => $taskTypeId,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $blockedId = $this->createTask([
            'name' => 'Blocked undated task',
            'status' => 'Blocked',
            'assigned_to_user_id' => $user->id,
            'milestone_id' => $milestoneId,
            'task_type_id' => $taskTypeId,
            'due_date' => null,
        ]);

        $doneId = $this->createTask([
            'name' => 'Completed task',
            'status' => 'Done',
            'assigned_to_user_id' => $user->id,
            'milestone_id' => $milestoneId,
            'task_type_id' => $taskTypeId,
            'due_date' => now()->toDateString(),
            'completed_at' => now()->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/tasks?project_id='.$projectId.'&assigned_to_user_id='.$user->id.'&statuses=To%20Do,In%20Progress,Paused,Blocked&per_page=100&page=1');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $todoId, 'name' => 'Todo future task']);
        $response->assertJsonFragment(['id' => $blockedId, 'name' => 'Blocked undated task']);
        $response->assertJsonMissing(['id' => $doneId, 'name' => 'Completed task']);
    }

    public function test_due_until_filters_out_future_and_undated_tasks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        [$projectId, $milestoneId, $taskTypeId] = $this->createProjectTaskContext($user->id);

        $dueTodayId = $this->createTask([
            'name' => 'Due today task',
            'status' => 'To Do',
            'assigned_to_user_id' => $user->id,
            'milestone_id' => $milestoneId,
            'task_type_id' => $taskTypeId,
            'due_date' => now()->toDateString(),
        ]);

        $futureId = $this->createTask([
            'name' => 'Future task',
            'status' => 'To Do',
            'assigned_to_user_id' => $user->id,
            'milestone_id' => $milestoneId,
            'task_type_id' => $taskTypeId,
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $undatedId = $this->createTask([
            'name' => 'Undated task',
            'status' => 'To Do',
            'assigned_to_user_id' => $user->id,
            'milestone_id' => $milestoneId,
            'task_type_id' => $taskTypeId,
            'due_date' => null,
        ]);

        $response = $this->getJson('/api/tasks?project_id='.$projectId.'&assigned_to_user_id='.$user->id.'&statuses=To%20Do,In%20Progress,Paused,Blocked,Done&due_until='.now()->toDateString().'&per_page=100&page=1');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $dueTodayId, 'name' => 'Due today task']);
        $response->assertJsonMissing(['id' => $futureId, 'name' => 'Future task']);
        $response->assertJsonMissing(['id' => $undatedId, 'name' => 'Undated task']);
    }

    public function test_completed_since_limits_recent_done_results(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        [$projectId, $milestoneId, $taskTypeId] = $this->createProjectTaskContext($user->id);

        $recentDoneId = $this->createTask([
            'name' => 'Recent done task',
            'status' => 'Done',
            'assigned_to_user_id' => $user->id,
            'milestone_id' => $milestoneId,
            'task_type_id' => $taskTypeId,
            'due_date' => now()->subDay()->toDateString(),
            'completed_at' => now()->subDays(2)->toDateTimeString(),
        ]);

        $olderDoneId = $this->createTask([
            'name' => 'Older done task',
            'status' => 'Done',
            'assigned_to_user_id' => $user->id,
            'milestone_id' => $milestoneId,
            'task_type_id' => $taskTypeId,
            'due_date' => now()->subDays(10)->toDateString(),
            'completed_at' => now()->subDays(45)->toDateTimeString(),
        ]);

        $response = $this->getJson('/api/tasks?project_id='.$projectId.'&assigned_to_user_id='.$user->id.'&statuses=Done&completed_since='.now()->subDays(30)->toDateString().'&per_page=100&page=1');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $recentDoneId, 'name' => 'Recent done task']);
        $response->assertJsonMissing(['id' => $olderDoneId, 'name' => 'Older done task']);
    }

    private function createProjectTaskContext(int $userId): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => 'Test Client',
            'email' => 'client'.uniqid().'@example.com',
            'phone' => null,
            'address' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Workspace Filtering Project',
            'description' => null,
            'website' => null,
            'social_media_link' => null,
            'preferred_keywords' => null,
            'google_chat_id' => null,
            'client_id' => $clientId,
            'status' => 'active',
            'project_type' => null,
            'services' => null,
            'service_details' => null,
            'source' => null,
            'total_amount' => null,
            'contract_details' => null,
            'google_drive_link' => null,
            'payment_type' => 'one_off',
            'logo' => null,
            'documents' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $milestoneId = DB::table('milestones')->insertGetId([
            'project_id' => $projectId,
            'name' => 'Support',
            'description' => null,
            'completion_date' => now()->addMonth()->toDateString(),
            'actual_completion_date' => null,
            'status' => 'Not Started',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $taskTypeId = DB::table('task_types')->insertGetId([
            'name' => 'General',
            'description' => null,
            'created_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$projectId, $milestoneId, $taskTypeId];
    }

    private function createTask(array $attributes): int
    {
        return DB::table('tasks')->insertGetId([
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'assigned_to_user_id' => $attributes['assigned_to_user_id'] ?? null,
            'due_date' => $attributes['due_date'] ?? null,
            'actual_completion_date' => $attributes['actual_completion_date'] ?? null,
            'status' => $attributes['status'],
            'task_type_id' => $attributes['task_type_id'],
            'milestone_id' => $attributes['milestone_id'],
            'google_chat_space_id' => null,
            'completed_at' => $attributes['completed_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
