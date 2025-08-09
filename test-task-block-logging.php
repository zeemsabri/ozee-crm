<?php

// Test script to verify task block reason logging
// Run with: php test-task-block-logging.php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Task;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\DB;

echo "Testing task block reason logging...\n\n";

// Get a valid task_type_id
$taskType = \App\Models\TaskType::first();
if (!$taskType) {
    echo "Error: No TaskType found in the database. Please create one first.\n";
    exit(1);
}

// Create a test task
$task = Task::create([
    'name' => 'Test Task for Block Reason Logging',
    'description' => 'This is a test task to verify block reason logging',
    'status' => 'To Do',
    'priority' => 'Medium',
    'task_type_id' => $taskType->id,
]);

echo "Created test task with ID: {$task->id}\n";

// Block the task with a reason
echo "Blocking task with reason...\n";
$task->previous_status = $task->status;
$task->status = 'Blocked';
$task->block_reason = 'Waiting for client feedback';
$task->save();

// Sleep to ensure activity is logged
sleep(1);

// Get all activity log entries for this task
$activities = Activity::where('subject_type', Task::class)
    ->where('subject_id', $task->id)
    ->orderBy('created_at', 'desc')
    ->get();

echo "\nActivity Log Entries (" . count($activities) . " found):\n";
foreach ($activities as $index => $activity) {
    echo "Entry " . ($index + 1) . ":\n";
    echo "Description: " . $activity->description . "\n";
    echo "Event: " . $activity->event . "\n";
    echo "Created at: " . $activity->created_at . "\n";
    echo "Properties: " . json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n\n";
}

// Update just the block reason
echo "Updating just the block reason...\n";
$task->block_reason = 'Waiting for external API access';
$task->save();

// Sleep to ensure activity is logged
sleep(1);

// Get all activity log entries for this task
$activities = Activity::where('subject_type', Task::class)
    ->where('subject_id', $task->id)
    ->orderBy('created_at', 'desc')
    ->get();

echo "\nActivity Log Entries after block reason update (" . count($activities) . " found):\n";
foreach ($activities as $index => $activity) {
    echo "Entry " . ($index + 1) . ":\n";
    echo "Description: " . $activity->description . "\n";
    echo "Event: " . $activity->event . "\n";
    echo "Created at: " . $activity->created_at . "\n";
    echo "Properties: " . json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n\n";
}

// Unblock the task
echo "Unblocking task...\n";
$task->status = $task->previous_status ?: 'To Do';
$task->block_reason = null;
$task->previous_status = null;
$task->save();

// Sleep to ensure activity is logged
sleep(1);

// Get all activity log entries for this task
$activities = Activity::where('subject_type', Task::class)
    ->where('subject_id', $task->id)
    ->orderBy('created_at', 'desc')
    ->get();

echo "\nActivity Log Entries after unblocking (" . count($activities) . " found):\n";
foreach ($activities as $index => $activity) {
    echo "Entry " . ($index + 1) . ":\n";
    echo "Description: " . $activity->description . "\n";
    echo "Event: " . $activity->event . "\n";
    echo "Created at: " . $activity->created_at . "\n";
    echo "Properties: " . json_encode($activity->properties, JSON_PRETTY_PRINT) . "\n\n";
}

// Clean up - delete the test task
$task->delete();
echo "Test completed and test task deleted.\n";
