<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Project;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Support\Facades\Log;

// Get a project with a Google Chat space
$project = Project::whereNotNull('google_chat_id')->first();

if (!$project) {
    echo "No projects found with a Google Chat space. Please create a project with a Google Chat space first.\n";
    exit;
}

echo "Using project: {$project->name} (ID: {$project->id})\n";
echo "Project Google Chat ID: {$project->google_chat_id}\n";

// Get or create a task type
$taskType = TaskType::first();
if (!$taskType) {
    echo "No task types found. Creating a default task type.\n";
    $taskType = TaskType::create([
        'name' => 'Default Task Type',
        'description' => 'Default task type for testing',
        'created_by_user_id' => User::first()->id,
    ]);
}

// Get or create a milestone for the project
$milestone = $project->milestones()->first();
if (!$milestone) {
    echo "No milestones found for this project. Creating a test milestone.\n";
    $milestone = $project->milestones()->create([
        'name' => 'Test Milestone',
        'description' => 'Test milestone for Google Chat integration',
        'status' => 'Not Started',
    ]);
}

echo "Using milestone: {$milestone->name} (ID: {$milestone->id})\n";

// Create a task for the milestone
$task = new Task();
$task->name = "Test Task for Google Chat Integration";
$task->description = "This is a test task to verify Google Chat integration";
$task->status = "To Do";
$task->milestone_id = $milestone->id;
$task->task_type_id = $taskType->id;
$task->save();

echo "Created task: {$task->name} (ID: {$task->id})\n";

// Reload the task to get the Google Chat space ID
$task->refresh();
echo "Task Google Chat space ID: " . ($task->google_chat_space_id ?: 'Not set') . "\n";
echo "Task Google Chat thread ID: " . ($task->google_chat_thread_id ?: 'Not set') . "\n";
echo "Task chat message ID: " . ($task->chat_message_id ?: 'Not set') . "\n";

// Verify that the task is using the project's Google Chat space
if ($task->google_chat_space_id === $project->google_chat_id) {
    echo "✅ Task is correctly using the project's Google Chat space\n";
} else {
    echo "❌ Task is NOT using the project's Google Chat space\n";
}

// Add a note to the task
$user = User::first();
echo "Adding a note to the task as user: {$user->name}\n";

try {
    $result = $task->addNote("This is a test note for Google Chat integration", $user);
    echo "Note added successfully\n";
    echo "Result: " . json_encode($result) . "\n";

    // Reload the task to get updated Google Chat IDs
    $task->refresh();
    echo "Updated task Google Chat thread ID: " . ($task->google_chat_thread_id ?: 'Not set') . "\n";
    echo "Updated task chat message ID: " . ($task->chat_message_id ?: 'Not set') . "\n";
} catch (\Exception $e) {
    echo "Error adding note: " . $e->getMessage() . "\n";
}

// Start the task
echo "Starting the task as user: {$user->name}\n";

try {
    $task->start($user);
    echo "Task started successfully\n";

    // Verify that the task status is updated
    $task->refresh();
    if ($task->status === 'In Progress') {
        echo "✅ Task status is correctly set to 'In Progress'\n";
    } else {
        echo "❌ Task status is NOT set to 'In Progress', current status: {$task->status}\n";
    }
} catch (\Exception $e) {
    echo "Error starting task: " . $e->getMessage() . "\n";
}

// Clean up
echo "Cleaning up...\n";
$task->delete();
echo "Test completed.\n";
