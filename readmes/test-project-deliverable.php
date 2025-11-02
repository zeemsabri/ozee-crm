<?php

use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\Task;

// This script tests the ProjectDeliverable implementation

// Get a project to work with
$project = Project::first();

if (! $project) {
    echo "No projects found. Please create a project first.\n";
    exit(1);
}

echo "Using project: {$project->name} (ID: {$project->id})\n";

// Get a milestone to work with
$milestone = $project->milestones()->first();

if (! $milestone) {
    echo "No milestones found for this project. Creating one...\n";
    $milestone = $project->milestones()->create([
        'name' => 'Test Milestone',
        'description' => 'Created for testing project deliverables',
        'status' => 'In Progress',
    ]);
    echo "Created milestone: {$milestone->name} (ID: {$milestone->id})\n";
} else {
    echo "Using milestone: {$milestone->name} (ID: {$milestone->id})\n";
}

// Create a project deliverable
echo "Creating a project deliverable...\n";
$deliverable = new ProjectDeliverable([
    'name' => 'Test Deliverable '.date('Y-m-d H:i:s'),
    'description' => 'This is a test deliverable created by the test script',
    'status' => 'pending',
    'due_date' => now()->addDays(7),
    'details' => [
        'priority' => 'high',
        'estimated_hours' => 10,
        'notes' => 'These are some test notes in the JSON details field',
    ],
]);

$deliverable->project_id = $project->id;
$deliverable->milestone_id = $milestone->id;
$deliverable->save();

echo "Created project deliverable: {$deliverable->name} (ID: {$deliverable->id})\n";

// Create a task associated with the deliverable
echo "Creating a task associated with the deliverable...\n";
$task = new Task([
    'name' => 'Test Task for Deliverable '.date('Y-m-d H:i:s'),
    'description' => 'This is a test task associated with the deliverable',
    'status' => 'To Do',
    'due_date' => now()->addDays(5),
]);

$task->milestone_id = $milestone->id;
$task->project_deliverable_id = $deliverable->id;
$task->save();

echo "Created task: {$task->name} (ID: {$task->id})\n";

// Test the relationships
echo "\nTesting relationships...\n";

// Test Project -> ProjectDeliverables relationship
$projectDeliverables = $project->projectDeliverables;
echo 'Project has '.count($projectDeliverables)." deliverables\n";

// Test Milestone -> ProjectDeliverables relationship
$milestoneDeliverables = $milestone->projectDeliverables;
echo 'Milestone has '.count($milestoneDeliverables)." deliverables\n";

// Test ProjectDeliverable -> Tasks relationship
$deliverableTasks = $deliverable->tasks;
echo 'Deliverable has '.count($deliverableTasks)." tasks\n";

// Test Task -> ProjectDeliverable relationship
$taskDeliverable = $task->projectDeliverable;
if ($taskDeliverable) {
    echo "Task is associated with deliverable: {$taskDeliverable->name} (ID: {$taskDeliverable->id})\n";
} else {
    echo "Task is not associated with any deliverable\n";
}

echo "\nTest completed successfully!\n";
