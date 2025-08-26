<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Project;
use App\Models\Milestone;
use App\Models\Task;

// Get a project
$project = Project::first();

if (!$project) {
    echo "No projects found. Please create a project first.\n";
    exit;
}

echo "Using project: {$project->name} (ID: {$project->id})\n";

// Create a milestone for the project
$milestone = new Milestone();
$milestone->name = "Test Milestone";
$milestone->description = "This is a test milestone";
$milestone->status = "Not Started";
$milestone->project_id = $project->id;
$milestone->save();

echo "Created milestone: {$milestone->name} (ID: {$milestone->id})\n";

// Verify that the project_id was saved correctly
$savedMilestone = Milestone::find($milestone->id);
echo "Saved milestone project_id: {$savedMilestone->project_id}\n";

// Create a task for the milestone
$task = new Task();
$task->name = "Test Task";
$task->description = "This is a test task";
$task->status = "To Do";
$task->milestone_id = $milestone->id;
$task->task_type_id = 1; // Assuming there's at least one task type
$task->save();

echo "Created task: {$task->name} (ID: {$task->id})\n";

// Get all milestones for the project
$milestones = $project->milestones()->get();
echo "Milestones for project {$project->id}:\n";
foreach ($milestones as $m) {
    echo "- {$m->name} (ID: {$m->id}, Project ID: {$m->project_id})\n";
}

// Get all tasks for the project's milestones
$milestoneIds = $project->milestones()->pluck('id')->toArray();
$tasks = Task::whereIn('milestone_id', $milestoneIds)->get();
echo "Tasks for project {$project->id} milestones:\n";
foreach ($tasks as $t) {
    echo "- {$t->name} (ID: {$t->id}, Milestone ID: {$t->milestone_id})\n";
}

// Clean up
$task->delete();
$milestone->delete();

echo "Test completed.\n";
