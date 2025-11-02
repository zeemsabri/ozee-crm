<?php

// This file should be run with Laravel's artisan tinker
// Run: php artisan tinker --execute="require 'test-notification.php';"

use App\Models\Milestone;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssigned;
use Illuminate\Support\Facades\Notification;

echo "Testing notification flow...\n";

// Find a user to assign the task to
$user = User::first();
if (! $user) {
    echo "Error: No users found in the database.\n";

    return;
}

echo "Found user: {$user->name} (ID: {$user->id})\n";

// Find a milestone to associate with the task
$milestone = Milestone::first();
if (! $milestone) {
    echo "Warning: No milestones found. Creating task without milestone.\n";
}

// Create a new task
$task = new Task;
$task->name = 'Test Task '.now()->format('Y-m-d H:i:s');
$task->description = 'This is a test task created to verify notifications.';
$task->due_date = now()->addDays(7);
$task->status = 'pending';
$task->creator_id = $user->id;
$task->creator_type = 'App\\Models\\User';
$task->assigned_to_user_id = $user->id;

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

// Return the task for further inspection in tinker if needed
return $task;
