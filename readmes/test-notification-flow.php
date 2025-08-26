<?php

// Test script to verify the entire notification flow
// This script will create a task and assign it to a user to trigger a notification

use App\Models\Task;
use App\Models\User;
use App\Models\Milestone;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\Notification;

// Enable output buffering
ob_start();

echo "Testing notification flow...\n";

// Find a user to assign the task to
$user = User::first();
if (!$user) {
    echo "Error: No users found in the database.\n";
    exit;
}

echo "Found user: {$user->name} (ID: {$user->id})\n";

// Find a milestone to associate with the task
$milestone = Milestone::first();
if (!$milestone) {
    echo "Warning: No milestones found. Creating task without milestone.\n";
}

// Create a new task
$task = new Task();
$task->name = "Test Task " . date('Y-m-d H:i:s');
$task->description = "This is a test task created to verify notifications.";
$task->due_date = now()->addDays(7);
$task->status = 'pending';
$task->created_by = $user->id;
$task->assigned_to = $user->id;

if ($milestone) {
    $task->milestone_id = $milestone->id;
    echo "Using milestone: {$milestone->name} (ID: {$milestone->id})\n";
}

$task->save();

echo "Created task: {$task->name} (ID: {$task->id})\n";

// Send the notification
echo "Sending notification...\n";
Notification::send($user, new TaskAssigned($task));

echo "Notification sent. Check the browser for push notifications.\n";
echo "Also check the database 'notifications' table for the record.\n";

// Output the buffer
$output = ob_get_clean();
echo $output;

// Also log to a file for reference
file_put_contents('notification-test-log.txt', $output);

echo "Test completed. Log saved to notification-test-log.txt\n";
