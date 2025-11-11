<?php

namespace Tests\Feature;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneDueDateUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private Milestone $milestone;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create();
        $this->milestone = Milestone::factory()->create([
            'project_id' => $this->project->id,
            'completion_date' => Carbon::now()->addDays(10),
            'name' => 'Test Milestone',
        ]);
    }

    public function test_can_update_milestone_due_date()
    {
        $newDate = Carbon::now()->addDays(20)->format('Y-m-d');
        $reason = 'Extending deadline due to client request';

        $response = $this->actingAs($this->user)
            ->postJson("/api/milestones/{$this->milestone->id}/update-due-date", [
                'completion_date' => $newDate,
                'reason' => $reason,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'name', 'completion_date']);

        $this->milestone->refresh();
        $this->assertEquals($newDate, $this->milestone->completion_date->format('Y-m-d'));
    }

    public function test_creates_project_note_on_due_date_update()
    {
        $oldDate = $this->milestone->completion_date;
        $newDate = Carbon::now()->addDays(20)->format('Y-m-d');
        $reason = 'Project scope changed';

        $this->actingAs($this->user)
            ->postJson("/api/milestones/{$this->milestone->id}/update-due-date", [
                'completion_date' => $newDate,
                'reason' => $reason,
            ]);

        $note = ProjectNote::where('project_id', $this->project->id)
            ->where('noteable_id', $this->milestone->id)
            ->where('noteable_type', Milestone::class)
            ->where('type', 'milestone')
            ->latest()
            ->first();

        $this->assertNotNull($note);
        $this->assertStringContainsString('due date changed', $note->content);
        $this->assertStringContainsString($reason, $note->content);
    }

    public function test_validates_required_fields()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/milestones/{$this->milestone->id}/update-due-date", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['completion_date', 'reason']);
    }

    public function test_validates_reason_minimum_length()
    {
        $newDate = Carbon::now()->addDays(20)->format('Y-m-d');
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/milestones/{$this->milestone->id}/update-due-date", [
                'completion_date' => $newDate,
                'reason' => 'short',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    public function test_validates_valid_date_format()
    {
        $reason = 'This is a valid reason for extending the deadline';
        
        $response = $this->actingAs($this->user)
            ->postJson("/api/milestones/{$this->milestone->id}/update-due-date", [
                'completion_date' => 'invalid-date',
                'reason' => $reason,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['completion_date']);
    }

    public function test_milestone_note_includes_both_old_and_new_dates()
    {
        $oldDate = $this->milestone->completion_date;
        $newDate = Carbon::now()->addDays(20);
        $reason = 'Extending timeline for quality assurance';

        $this->actingAs($this->user)
            ->postJson("/api/milestones/{$this->milestone->id}/update-due-date", [
                'completion_date' => $newDate->format('Y-m-d'),
                'reason' => $reason,
            ]);

        $note = ProjectNote::where('noteable_id', $this->milestone->id)
            ->latest()
            ->first();

        $this->assertStringContainsString($oldDate->toDateString(), $note->content);
        $this->assertStringContainsString($newDate->toDateString(), $note->content);
    }

    public function test_returns_milestone_with_updated_date()
    {
        $newDate = Carbon::now()->addDays(20)->format('Y-m-d');
        $reason = 'Updated timeline';

        $response = $this->actingAs($this->user)
            ->postJson("/api/milestones/{$this->milestone->id}/update-due-date", [
                'completion_date' => $newDate,
                'reason' => $reason,
            ]);

        $response->assertJsonPath('completion_date', $newDate);
    }
}
