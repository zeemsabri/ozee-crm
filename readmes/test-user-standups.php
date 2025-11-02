<?php

require __DIR__.'/vendor/autoload.php';

use App\Models\ProjectNote;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Test the getUserStandups endpoint
echo "Testing getUserStandups endpoint...\n";

// Get a user for testing
$user = User::first();
if (! $user) {
    echo "No users found in the database.\n";
    exit(1);
}

echo "Using user: {$user->name} (ID: {$user->id})\n";

// Authenticate as this user
Auth::login($user);

// Create a test standup note for today
$today = now()->format('Y-m-d');
echo "Creating a test standup note for today ({$today})...\n";

$standup = new ProjectNote;
$standup->user_id = $user->id;
$standup->type = 'standup';
$standup->content = "Test standup for {$today}";
$standup->save();

echo "Created test standup with ID: {$standup->id}\n";

// Call the endpoint directly
$controller = new \App\Http\Controllers\Api\ProjectReadController;
$response = $controller->getUserStandups();

// Get the response data
$data = $response->getData(true);

echo "Response from getUserStandups:\n";
print_r($data);

// Clean up the test data
$standup->delete();
echo "Test standup deleted.\n";

echo "Test completed.\n";
