<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\DailyTask;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalQuickTaskEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_starts_a_quick_task_for_the_authenticated_user(): void
    {
        $user = User::factory()->create([
            'api_key' => 'quick-task-test-key',
        ]);

        $project = $this->createProjectForUser($user);
        $supportMilestone = $project->supportMilestone();
        $taskType = TaskType::firstOrCreate(['name' => 'New']);

        $previousTask = Task::create([
            'name' => 'Existing active task',
            'status' => TaskStatus::InProgress,
            'milestone_id' => $supportMilestone->id,
            'assigned_to_user_id' => $user->id,
            'due_date' => now()->toDateString(),
            'priority' => 'medium',
            'task_type_id' => $taskType->id,
        ]);

        $response = $this->withHeaders([
            'X-API-KEY' => $user->api_key,
        ])->postJson('/api/activity/tasks/quick', [
            'name' => 'Meeting with Client',
            'project_id' => $project->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('task.name', 'Meeting with Client')
            ->assertJsonPath('task.assigned_to_user_id', $user->id)
            ->assertJsonPath('task.milestone_id', $supportMilestone->id)
            ->assertJsonPath('task.status', TaskStatus::InProgress->value)
            ->assertJsonPath('active_task.name', 'Meeting with Client')
            ->assertJsonPath('active_task.status', TaskStatus::InProgress->value);

        $createdTaskId = $response->json('task.id');

        $this->assertDatabaseHas('tasks', [
            'id' => $createdTaskId,
            'name' => 'Meeting with Client',
            'assigned_to_user_id' => $user->id,
            'milestone_id' => $supportMilestone->id,
            'source' => 'extension_quick_task',
        ]);

        $this->assertSame(TaskStatus::Paused, $previousTask->fresh()->status);
        $this->assertSame($createdTaskId, $user->fresh()->activeTask?->id);

        $this->assertDatabaseHas('daily_tasks', [
            'user_id' => $user->id,
            'task_id' => $createdTaskId,
            'status' => DailyTask::STATUS_PENDING,
        ]);
    }

    public function test_it_rejects_quick_task_creation_for_projects_the_user_cannot_access(): void
    {
        $user = User::factory()->create([
            'api_key' => 'quick-task-test-key',
        ]);

        $project = $this->createProject();

        $response = $this->withHeaders([
            'X-API-KEY' => $user->api_key,
        ])->postJson('/api/activity/tasks/quick', [
            'name' => 'Unauthorized quick task',
            'project_id' => $project->id,
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Unauthorized',
            ]);
    }

    private function createProjectForUser(User $user): Project
    {
        $project = $this->createProject();
        $user->projects()->attach($project->id);

        return $project;
    }

    private function createProject(): Project
    {
        $client = Client::create([
            'name' => 'Quick Task Client',
            'email' => fake()->unique()->safeEmail(),
        ]);

        return Project::create([
            'name' => 'Quick Task Project',
            'client_id' => $client->id,
            'status' => 'active',
        ]);
    }
}