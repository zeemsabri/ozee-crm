<?php
/**
 * Test script for the assigned tasks API endpoint using Laravel's Tinker
 *
 * This script tests the TaskController@getAssignedTasks method directly
 *
 * Usage: php artisan tinker --execute="require 'test-assigned-tasks-tinker.php';"
 */

// Get a user to test with
$user = \App\Models\User::first();
if (!$user) {
    echo "No users found in the database.\n";
    return;
}

echo "Testing with user: {$user->name} (ID: {$user->id})\n";

// Authenticate as this user
auth()->login($user);
echo "Authenticated as user: {$user->name}\n";

// Create an instance of the TaskController
$controller = new \App\Http\Controllers\Api\TaskController();

// Call the getAssignedTasks method
echo "Calling TaskController@getAssignedTasks method...\n";
$response = $controller->getAssignedTasks();

// Get the response data
$data = $response->getData(true);

// Display the results
echo "Response received.\n";
echo "Number of assigned tasks: " . count($data) . "\n";

// Display the first few tasks if any exist
if (count($data) > 0) {
    echo "\nSample of assigned tasks:\n";
    $sampleSize = min(3, count($data));

    for ($i = 0; $i < $sampleSize; $i++) {
        $task = $data[$i];
        echo "- Task: {$task['name']}\n";
        echo "  Project: " . ($task['project'] ? $task['project']['name'] : 'N/A') . "\n";
        echo "  Milestone: " . ($task['milestone'] ? $task['milestone']['name'] : 'N/A') . "\n";
        echo "  Due Date: " . ($task['due_date'] ?? 'No due date') . "\n";
        echo "\n";
    }
} else {
    echo "No tasks are currently assigned to this user.\n";
}

echo "\nTest completed successfully.\n";
