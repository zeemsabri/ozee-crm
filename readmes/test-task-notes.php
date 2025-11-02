<?php

require __DIR__.'/vendor/autoload.php';

use App\Models\Task;
use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find a task to test with
$task = Task::first();

if (! $task) {
    echo "No tasks found in the database.\n";
    exit(1);
}

// Find a user to associate with the note
$user = User::first();

if (! $user) {
    echo "No users found in the database.\n";
    exit(1);
}

echo "Testing addNote method on Task ID: {$task->id}\n";
echo 'Before adding note, count of notes for this task: '.$task->notes()->count()."\n";

// Add a note to the task
$noteContent = 'Test note created at '.date('Y-m-d H:i:s');
$result = $task->addNote($noteContent, $user);

// Refresh the task model to get the latest data
$task->refresh();

echo 'After adding note, count of notes for this task: '.$task->notes()->count()."\n";

// Get the latest note
$latestNote = $task->notes()->latest()->first();

if ($latestNote) {
    echo "Latest note details:\n";
    echo "- Content: {$latestNote->content}\n";
    echo "- User ID: {$latestNote->user_id}\n";
    echo "- Noteable ID: {$latestNote->noteable_id}\n";
    echo "- Noteable Type: {$latestNote->noteable_type}\n";
    echo "- Project ID: {$latestNote->project_id}\n";
    echo "- Type: {$latestNote->type}\n";
    echo "- Created at: {$latestNote->created_at}\n";
} else {
    echo "No notes found for this task.\n";
}

echo "Test completed.\n";
