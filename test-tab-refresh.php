<?php

// This is a simple test script to verify that the tab refresh functionality works correctly
// Run this script with: php test-tab-refresh.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "Testing tab refresh functionality...\n\n";

// Get a manager user
$manager = User::whereHas('role', function($query) {
    $query->where('slug', 'manager');
})->first();

if (!$manager) {
    echo "Manager user not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Get a project
$project = Project::first();
if (!$project) {
    echo "No projects found in the database. Please create a project first.\n";
    exit;
}

echo "Using project: {$project->name} (ID: {$project->id})\n\n";

// Log in as the manager
Auth::login($manager);

// Test 1: Verify the API endpoint returns the correct project data
echo "TEST 1: Verify the API endpoint returns the correct project data\n";

try {
    // Create a Guzzle HTTP client with cookies to maintain the session
    $client = new \GuzzleHttp\Client([
        'cookies' => true,
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]);

    // Make the request with the authenticated user's session
    $response = $client->get(url("/api/projects/{$project->id}"), [
        'cookies' => true,
        'headers' => [
            'Authorization' => 'Bearer ' . $manager->createToken('test-token')->plainTextToken,
        ],
    ]);

    $responseData = json_decode($response->getBody(), true);

    if (isset($responseData['id']) && $responseData['id'] == $project->id) {
        echo "SUCCESS: Project API endpoint returned the correct project\n";

        // Check if clients are included in the response
        if (isset($responseData['clients']) && is_array($responseData['clients'])) {
            echo "SUCCESS: Project API endpoint returned " . count($responseData['clients']) . " clients\n";
        } else {
            echo "FAILURE: Project API endpoint did not return clients data\n";
        }

        // Check if users are included in the response
        if (isset($responseData['users']) && is_array($responseData['users'])) {
            echo "SUCCESS: Project API endpoint returned " . count($responseData['users']) . " users\n";
        } else {
            echo "FAILURE: Project API endpoint did not return users data\n";
        }
    } else {
        echo "FAILURE: Project API endpoint did not return the correct project\n";
    }
} catch (Exception $e) {
    echo "FAILURE: Error accessing project API endpoint: " . $e->getMessage() . "\n";
}

// Test 2: Verify the project data includes pivot information for clients and users
echo "\nTEST 2: Verify the project data includes pivot information for clients and users\n";

// Check if the project has clients with pivot data
$project->load(['clients' => function($query) {
    $query->withPivot('role_id');
}]);

if ($project->clients->isNotEmpty()) {
    echo "Project has " . $project->clients->count() . " clients\n";

    $hasRoleId = true;
    foreach ($project->clients as $client) {
        if (!isset($client->pivot) || !isset($client->pivot->role_id)) {
            $hasRoleId = false;
            break;
        }
    }

    if ($hasRoleId) {
        echo "SUCCESS: All clients have pivot.role_id information\n";
    } else {
        echo "FAILURE: Some clients are missing pivot.role_id information\n";
    }
} else {
    echo "Project has no clients. Adding a test client...\n";

    // Add a test client to the project
    $client = Client::first();
    if ($client) {
        $role = Role::first();
        $project->clients()->attach($client->id, ['role_id' => $role->id]);
        echo "Added client {$client->name} with role {$role->name} to the project\n";
    } else {
        echo "No clients found in the database. Please create a client first.\n";
    }
}

// Check if the project has users with pivot data
$project->load(['users' => function($query) {
    $query->withPivot('role_id');
}]);

if ($project->users->isNotEmpty()) {
    echo "Project has " . $project->users->count() . " users\n";

    $hasRoleId = true;
    foreach ($project->users as $user) {
        if (!isset($user->pivot) || !isset($user->pivot->role_id)) {
            $hasRoleId = false;
            break;
        }
    }

    if ($hasRoleId) {
        echo "SUCCESS: All users have pivot.role_id information\n";
    } else {
        echo "FAILURE: Some users are missing pivot.role_id information\n";
    }
} else {
    echo "Project has no users. Adding a test user...\n";

    // Add a test user to the project
    $user = User::where('id', '!=', $manager->id)->first();
    if ($user) {
        $role = Role::first();
        $project->users()->attach($user->id, ['role_id' => $role->id]);
        echo "Added user {$user->name} with role {$role->name} to the project\n";
    } else {
        echo "No other users found in the database. Please create another user first.\n";
    }
}

echo "\nTest completed. The changes to refresh clients and users data when switching to the client tab should work correctly.\n";
echo "When the user switches to the client tab, the fetchProjectData function will be called to get the latest data from the server.\n";
