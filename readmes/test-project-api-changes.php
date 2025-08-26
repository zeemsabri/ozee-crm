<?php

// This is a simple test script to verify that the project API changes work correctly
// Run this script with: php test-project-api-changes.php

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
use Illuminate\Http\Request;

echo "Testing project API changes...\n\n";

// Get a manager user
$manager = User::whereHas('role', function($query) {
    $query->where('slug', 'manager');
})->first();

if (!$manager) {
    echo "Manager user not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Get a client
$client = Client::first();
if (!$client) {
    echo "No clients found in the database. Please create a client first.\n";
    exit;
}

echo "Testing with Manager: {$manager->name} (ID: {$manager->id})\n";
echo "Testing with Client: {$client->name} (ID: {$client->id})\n\n";

// Log in as the manager
Auth::login($manager);

// Test 1: Create a project without sending client_ids and user_ids
echo "TEST 1: Create a project without sending client_ids and user_ids\n";

$projectData = [
    'name' => 'Test Project API Changes ' . time(),
    'description' => 'This is a test project created by the test script',
    'client_id' => $client->id,
    'status' => 'active',
    'project_type' => 'Test',
    'services' => ['Website Designing'],
    'service_details' => [
        [
            'service_id' => 'Website Designing',
            'amount' => 1000,
            'frequency' => 'one_off',
            'payment_breakdown' => [
                'first' => 30,
                'second' => 30,
                'third' => 40
            ]
        ]
    ],
    'source' => 'Direct',
    'total_amount' => 1000,
    'contract_details' => 'Test contract details',
    'payment_type' => 'one_off',
];

try {
    // Call the API endpoint directly
    $response = app()->call('\App\Http\Controllers\Api\ProjectController@store', [
        'request' => new \Illuminate\Http\Request($projectData)
    ]);

    $responseData = json_decode($response->getContent(), true);

    if ($response->getStatusCode() === 201 && isset($responseData['id'])) {
        echo "SUCCESS: Project created successfully with ID: {$responseData['id']}\n";

        // Store the project ID for the next test
        $projectId = $responseData['id'];

        // Verify that the project was created with the correct client_id
        $project = Project::find($projectId);
        if ($project && $project->client_id === $client->id) {
            echo "SUCCESS: Project has the correct client_id: {$project->client_id}\n";
        } else {
            echo "FAILURE: Project does not have the correct client_id\n";
        }

        // Verify that no clients were attached to the project through project_client
        $projectClients = DB::table('project_client')
            ->where('project_id', $projectId)
            ->get();

        if ($projectClients->isEmpty()) {
            echo "SUCCESS: No clients were attached to the project through project_client\n";
        } else {
            echo "FAILURE: Clients were attached to the project through project_client\n";
            foreach ($projectClients as $pc) {
                echo "- Client ID: {$pc->client_id}, Role ID: {$pc->role_id}\n";
            }
        }

        // Verify that no users were attached to the project
        $projectUsers = DB::table('project_user')
            ->where('project_id', $projectId)
            ->get();

        if ($projectUsers->isEmpty()) {
            echo "SUCCESS: No users were attached to the project\n";
        } else {
            echo "FAILURE: Users were attached to the project\n";
            foreach ($projectUsers as $pu) {
                echo "- User ID: {$pu->user_id}, Role ID: {$pu->role_id}\n";
            }
        }
    } else {
        echo "FAILURE: Project creation failed with status code " . $response->getStatusCode() . "\n";
        if (isset($responseData['message'])) {
            echo "Error message: " . $responseData['message'] . "\n";
        }
        if (isset($responseData['errors'])) {
            echo "Validation errors: " . json_encode($responseData['errors']) . "\n";
        }
        exit;
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Update the project without sending client_ids and user_ids
echo "\nTEST 2: Update the project without sending client_ids and user_ids\n";

$updateData = [
    'name' => 'Updated Test Project API Changes ' . time(),
    'description' => 'This is an updated test project',
    'client_id' => $client->id,
    'status' => 'on_hold',
];

try {
    // Get the project
    $project = Project::find($projectId);

    // Call the API endpoint directly
    $response = app()->call('\App\Http\Controllers\Api\ProjectController@update', [
        'request' => new \Illuminate\Http\Request($updateData),
        'project' => $project
    ]);

    $responseData = json_decode($response->getContent(), true);

    if ($response->getStatusCode() === 200 && isset($responseData['id'])) {
        echo "SUCCESS: Project updated successfully\n";

        // Verify that the project was updated with the correct data
        $updatedProject = Project::find($projectId);
        if ($updatedProject->name === $updateData['name'] && $updatedProject->status === $updateData['status']) {
            echo "SUCCESS: Project was updated with the correct data\n";
        } else {
            echo "FAILURE: Project was not updated with the correct data\n";
            echo "Expected name: {$updateData['name']}, Actual name: {$updatedProject->name}\n";
            echo "Expected status: {$updateData['status']}, Actual status: {$updatedProject->status}\n";
        }

        // Verify that no clients were attached to the project through project_client
        $projectClients = DB::table('project_client')
            ->where('project_id', $projectId)
            ->get();

        if ($projectClients->isEmpty()) {
            echo "SUCCESS: No clients were attached to the project through project_client\n";
        } else {
            echo "FAILURE: Clients were attached to the project through project_client\n";
            foreach ($projectClients as $pc) {
                echo "- Client ID: {$pc->client_id}, Role ID: {$pc->role_id}\n";
            }
        }

        // Verify that no users were attached to the project
        $projectUsers = DB::table('project_user')
            ->where('project_id', $projectId)
            ->get();

        if ($projectUsers->isEmpty()) {
            echo "SUCCESS: No users were attached to the project\n";
        } else {
            echo "FAILURE: Users were attached to the project\n";
            foreach ($projectUsers as $pu) {
                echo "- User ID: {$pu->user_id}, Role ID: {$pu->role_id}\n";
            }
        }
    } else {
        echo "FAILURE: Project update failed with status code " . $response->getStatusCode() . "\n";
        if (isset($responseData['message'])) {
            echo "Error message: " . $responseData['message'] . "\n";
        }
        if (isset($responseData['errors'])) {
            echo "Validation errors: " . json_encode($responseData['errors']) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
}

// Clean up - delete the test project
try {
    $project = Project::find($projectId);
    if ($project) {
        $project->delete();
        echo "\nTest project deleted.\n";
    }
} catch (\Exception $e) {
    echo "\nFailed to delete test project: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
