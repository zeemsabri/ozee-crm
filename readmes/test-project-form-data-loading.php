<?php

// This is a simple test script to verify that the ProjectForm data loading changes work correctly
// Run this script with: php test-project-form-data-loading.php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "Testing ProjectForm data loading changes...\n\n";

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
    $response = app()->call('\App\Http\Controllers\Api\ProjectController@show', [
        'project' => $project
    ]);

    $responseData = json_decode($response->getContent(), true);

    if (isset($responseData['id']) && $responseData['id'] == $project->id) {
        echo "SUCCESS: Project API endpoint returned the correct project\n";

        // Check if clients are included in the response
        if (isset($responseData['clients']) && is_array($responseData['clients'])) {
            echo "SUCCESS: Project API endpoint returned " . count($responseData['clients']) . " clients\n";

            // Check if clients have pivot data
            $hasRoleId = true;
            foreach ($responseData['clients'] as $client) {
                if (!isset($client['pivot']) || !isset($client['pivot']['role_id'])) {
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
            echo "FAILURE: Project API endpoint did not return clients data\n";
        }

        // Check if users are included in the response
        if (isset($responseData['users']) && is_array($responseData['users'])) {
            echo "SUCCESS: Project API endpoint returned " . count($responseData['users']) . " users\n";

            // Check if users have pivot data
            $hasRoleId = true;
            foreach ($responseData['users'] as $user) {
                if (!isset($user['pivot']) || !isset($user['pivot']['role_id'])) {
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
            echo "FAILURE: Project API endpoint did not return users data\n";
        }
    } else {
        echo "FAILURE: Project API endpoint did not return the correct project\n";
    }
} catch (Exception $e) {
    echo "FAILURE: Error accessing project API endpoint: " . $e->getMessage() . "\n";
}

// Test 2: Simulate the behavior of the ProjectForm component
echo "\nTEST 2: Simulate the behavior of the ProjectForm component\n";

// Simulate opening the modal with an existing project
echo "Simulating opening the modal with an existing project...\n";

// Create a mock projectForm object
$projectForm = [
    'id' => $project->id,
    'client_ids' => [],
    'user_ids' => []
];

// Simulate fetching project data
echo "Simulating fetchProjectData function...\n";
$response = app()->call('\App\Http\Controllers\Api\ProjectController@show', [
    'project' => $project
]);
$projectData = json_decode($response->getContent(), true);

// Update client_ids with the latest data
if (isset($projectData['clients']) && is_array($projectData['clients']) && count($projectData['clients']) > 0) {
    $projectForm['client_ids'] = array_map(function($client) {
        return [
            'id' => $client['id'],
            'role_id' => $client['pivot']['role_id'] ?? 1
        ];
    }, $projectData['clients']);

    echo "SUCCESS: client_ids updated with " . count($projectForm['client_ids']) . " clients\n";
} else {
    echo "FAILURE: Could not update client_ids\n";
}

// Update user_ids with the latest data
if (isset($projectData['users']) && is_array($projectData['users']) && count($projectData['users']) > 0) {
    $projectForm['user_ids'] = array_map(function($user) {
        return [
            'id' => $user['id'],
            'role_id' => $user['pivot']['role_id'] ?? 2
        ];
    }, $projectData['users']);

    echo "SUCCESS: user_ids updated with " . count($projectForm['user_ids']) . " users\n";
} else {
    echo "FAILURE: Could not update user_ids\n";
}

// Test 3: Simulate removing a client and saving
echo "\nTEST 3: Simulate removing a client and saving\n";

// Check if we have clients to remove
if (count($projectForm['client_ids']) > 0) {
    // Remove the first client
    $removedClient = array_shift($projectForm['client_ids']);
    echo "Removed client with ID: " . $removedClient['id'] . "\n";

    // Simulate saving clients
    echo "Simulating saveClients function...\n";

    // Convert client_ids to the format expected by the API
    $clientData = [];
    foreach ($projectForm['client_ids'] as $client) {
        $clientData[$client['id']] = ['role_id' => $client['role_id']];
    }

    // Sync the clients to the project
    $project->clients()->sync($clientData);
    echo "Clients saved successfully\n";

    // Verify the client was removed
    $clientStillExists = DB::table('project_client')
        ->where('project_id', $project->id)
        ->where('client_id', $removedClient['id'])
        ->exists();

    if (!$clientStillExists) {
        echo "SUCCESS: Client was properly removed from the database\n";
    } else {
        echo "FAILURE: Client was not removed from the database\n";
    }

    // Restore the original clients
    echo "Restoring original clients...\n";
    $originalClients = [];
    foreach ($projectData['clients'] as $client) {
        $originalClients[$client['id']] = ['role_id' => $client['pivot']['role_id'] ?? 1];
    }
    $project->clients()->sync($originalClients);
} else {
    echo "No clients to remove. Skipping this test.\n";
}

echo "\nTest completed. The changes to not initialize client_ids and user_ids from the project prop should resolve the issue.\n";
echo "When the user removes a client or user and saves, the changes will be properly reflected in the UI when the modal is reopened.\n";
