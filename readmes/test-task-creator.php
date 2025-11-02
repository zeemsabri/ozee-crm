<?php

use App\Models\Client;
use App\Models\Task;
use App\Models\User;

// Test script to verify the polymorphic creator relationship for Task model

// 1. Test setting a User as creator
$user = User::first();
if ($user) {
    // Authenticate as the user
    auth()->login($user);

    // Create a new task
    $task = new Task([
        'name' => 'Test Task Created by User',
        'description' => 'This is a test task created by a User',
        'status' => 'To Do',
        'task_type_id' => 1, // Assuming task_type_id 1 exists
    ]);

    // Save the task
    $task->save();

    // Set the creator from auth
    $task->setCreatorFromAuth();

    // Verify the creator was set correctly
    echo "Task created by User:\n";
    echo 'Task ID: '.$task->id."\n";
    echo 'Creator ID: '.$task->creator_id."\n";
    echo 'Creator Type: '.$task->creator_type."\n";
    echo 'Creator Name: '.$task->getCreatorName()."\n\n";

    // Logout
    auth()->logout();
} else {
    echo "No users found in the database.\n\n";
}

// 2. Test setting a Client as creator
$client = Client::first();
if ($client) {
    // We need to simulate a client being authenticated
    // In a real application, this would be handled by the auth system
    // For testing purposes, we'll manually set the creator

    $task = new Task([
        'name' => 'Test Task Created by Client',
        'description' => 'This is a test task created by a Client',
        'status' => 'To Do',
        'task_type_id' => 1, // Assuming task_type_id 1 exists
    ]);

    // Save the task
    $task->save();

    // Manually set the creator (simulating auth)
    $task->creator_id = $client->id;
    $task->creator_type = get_class($client);
    $task->save();

    // Verify the creator was set correctly
    echo "Task created by Client:\n";
    echo 'Task ID: '.$task->id."\n";
    echo 'Creator ID: '.$task->creator_id."\n";
    echo 'Creator Type: '.$task->creator_type."\n";

    // Load the creator relationship
    $task->load('creator');
    echo 'Creator Name: '.$task->getCreatorName()."\n\n";
} else {
    echo "No clients found in the database.\n\n";
}

// 3. Test retrieving tasks by creator type
echo "Tasks created by Users:\n";
$userTasks = Task::where('creator_type', User::class)->get();
foreach ($userTasks as $task) {
    echo '- Task ID: '.$task->id.', Name: '.$task->name.', Creator: '.$task->getCreatorName()."\n";
}
echo "\n";

echo "Tasks created by Clients:\n";
$clientTasks = Task::where('creator_type', Client::class)->get();
foreach ($clientTasks as $task) {
    echo '- Task ID: '.$task->id.', Name: '.$task->name.', Creator: '.$task->getCreatorName()."\n";
}
echo "\n";

echo "Test completed.\n";
