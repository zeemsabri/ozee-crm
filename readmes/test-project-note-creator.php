<?php

use App\Models\Client;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing ProjectNote polymorphic creator relationship\n";
echo "==================================================\n\n";

try {
    // Get a user and a client
    $user = User::first();
    $client = Client::first();

    if (! $user) {
        echo "Error: No users found in the database.\n";
        exit(1);
    }

    if (! $client) {
        echo "Error: No clients found in the database.\n";
        exit(1);
    }

    echo "Using User: {$user->name} (ID: {$user->id})\n";
    echo "Using Client: {$client->name} (ID: {$client->id})\n\n";

    // Create a test project and milestone
    $project = Project::create([
        'name' => 'Test Project for Notes',
        'description' => 'This is a test project for testing notes',
    ]);

    $milestone = Milestone::create([
        'name' => 'Test Milestone',
        'project_id' => $project->id,
    ]);

    // Get a task type
    $taskType = \App\Models\TaskType::first();

    if (! $taskType) {
        echo "Error: No task types found in the database.\n";
        exit(1);
    }

    echo "Using TaskType: {$taskType->name} (ID: {$taskType->id})\n";

    // Create a test task
    $task = Task::create([
        'name' => 'Test Task for Notes',
        'description' => 'This is a test task for testing notes',
        'milestone_id' => $milestone->id,
        'status' => 'To Do',
        'task_type_id' => $taskType->id,
    ]);

    echo "Created test task: {$task->name} (ID: {$task->id})\n\n";

    // Add a note to the task using a User
    echo "Adding note from User...\n";
    $userNoteResult = $task->addNote('This is a test note from a User', $user);
    echo 'User note result: '.($userNoteResult ? 'Success' : 'Failed')."\n";

    // Add a note to the task using a Client
    echo "Adding note from Client...\n";
    $clientNoteResult = $task->addNote('This is a test note from a Client', $client);
    echo 'Client note result: '.($clientNoteResult ? 'Success' : 'Failed')."\n";

    // Check if the task has a Google Chat space ID
    echo "\nTask Google Chat space ID: ".($task->google_chat_space_id ?? 'null')."\n";
    echo 'Task Google Chat thread ID: '.($task->google_chat_thread_id ?? 'null')."\n";

    // Check if the task has a milestone and project
    echo 'Task has milestone: '.($task->milestone ? 'Yes' : 'No')."\n";
    if ($task->milestone) {
        echo 'Milestone has project: '.($task->milestone->project ? 'Yes' : 'No')."\n";
        if ($task->milestone->project) {
            echo 'Project Google Chat ID: '.($task->milestone->project->google_chat_id ?? 'null')."\n";
        }
    }

    // Retrieve the notes and verify the creator relationships
    $notes = ProjectNote::where('noteable_id', $task->id)
        ->where('noteable_type', Task::class)
        ->orderBy('created_at', 'desc')
        ->get();

    // Check the raw database values
    $rawNotes = DB::table('project_notes')
        ->where('noteable_id', $task->id)
        ->where('noteable_type', Task::class)
        ->orderBy('created_at', 'desc')
        ->get();

    echo "\nRaw database values:\n";
    foreach ($rawNotes as $index => $note) {
        echo "\nRaw Note ".($index + 1).":\n";
        echo "ID: {$note->id}\n";
        echo 'Creator ID: '.($note->creator_id ?? 'null')."\n";
        echo 'Creator Type: '.($note->creator_type ?? 'null')."\n";
        echo 'User ID: '.($note->user_id ?? 'null')."\n";
    }

    echo "\nRetrieved ".count($notes)." notes:\n";

    foreach ($notes as $index => $note) {
        echo "\nNote ".($index + 1).":\n";
        echo 'Content: '.\Illuminate\Support\Facades\Crypt::decryptString($note->content)."\n";
        echo "Creator ID: {$note->creator_id}\n";
        echo "Creator Type: {$note->creator_type}\n";

        if ($note->creator) {
            echo "Creator Name: {$note->creator->name}\n";
            echo 'Creator is '.($note->creator_type == User::class ? 'User' : 'Client')."\n";
        } else {
            echo "Error: Creator relationship not working correctly\n";
        }
    }

    echo "\nTest completed successfully!\n";

    // Clean up
    echo "\nCleaning up test data...\n";
    $task->delete();
    $milestone->delete();
    $project->delete();

    echo "Done!\n";

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo $e->getTraceAsString()."\n";
}
