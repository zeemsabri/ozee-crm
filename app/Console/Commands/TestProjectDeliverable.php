<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\Task;
use App\Models\Milestone;
use Illuminate\Console\Command;

class TestProjectDeliverable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-project-deliverable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the ProjectDeliverable implementation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing ProjectDeliverable implementation...');

        // Get a project to work with
        $project = Project::first();

        if (!$project) {
            $this->error('No projects found. Please create a project first.');
            return 1;
        }

        $this->info("Using project: {$project->name} (ID: {$project->id})");

        // Get a milestone to work with
        $milestone = $project->milestones()->first();

        if (!$milestone) {
            $this->info("No milestones found for this project. Creating one...");
            $milestone = $project->milestones()->create([
                'name' => 'Test Milestone',
                'description' => 'Created for testing project deliverables',
                'status' => 'In Progress'
            ]);
            $this->info("Created milestone: {$milestone->name} (ID: {$milestone->id})");
        } else {
            $this->info("Using milestone: {$milestone->name} (ID: {$milestone->id})");
        }

        // Create a project deliverable
        $this->info("Creating a project deliverable...");
        $deliverable = new ProjectDeliverable([
            'name' => 'Test Deliverable ' . now()->format('Y-m-d H:i:s'),
            'description' => 'This is a test deliverable created by the test command',
            'status' => 'pending',
            'due_date' => now()->addDays(7),
            'details' => [
                'priority' => 'high',
                'estimated_hours' => 10,
                'notes' => 'These are some test notes in the JSON details field'
            ]
        ]);

        $deliverable->project_id = $project->id;
        $deliverable->milestone_id = $milestone->id;
        $deliverable->save();

        $this->info("Created project deliverable: {$deliverable->name} (ID: {$deliverable->id})");

        // Create a task associated with the deliverable
        $this->info("Creating a task associated with the deliverable...");
        $task = new Task([
            'name' => 'Test Task for Deliverable ' . now()->format('Y-m-d H:i:s'),
            'description' => 'This is a test task associated with the deliverable',
            'status' => 'To Do',
            'due_date' => now()->addDays(5),
        ]);

        $task->milestone_id = $milestone->id;
        $task->project_deliverable_id = $deliverable->id;
        $task->save();

        $this->info("Created task: {$task->name} (ID: {$task->id})");

        // Test the relationships
        $this->info("\nTesting relationships...");

        // Test Project -> ProjectDeliverables relationship
        $projectDeliverables = $project->projectDeliverables;
        $this->info("Project has " . count($projectDeliverables) . " deliverables");

        // Test Milestone -> ProjectDeliverables relationship
        $milestoneDeliverables = $milestone->projectDeliverables;
        $this->info("Milestone has " . count($milestoneDeliverables) . " deliverables");

        // Test ProjectDeliverable -> Tasks relationship
        $deliverableTasks = $deliverable->tasks;
        $this->info("Deliverable has " . count($deliverableTasks) . " tasks");

        // Test Task -> ProjectDeliverable relationship
        $taskDeliverable = $task->projectDeliverable;
        if ($taskDeliverable) {
            $this->info("Task is associated with deliverable: {$taskDeliverable->name} (ID: {$taskDeliverable->id})");
        } else {
            $this->error("Task is not associated with any deliverable");
        }

        $this->info("\nTest completed successfully!");

        return 0;
    }
}
