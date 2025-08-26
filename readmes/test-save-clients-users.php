<?php

// This is a simple test script to verify that the save clients and users functionality works correctly
// Run this script with: php test-save-clients-users.php

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
use Illuminate\Support\Facades\Route;

echo "Testing save clients and users functionality...\n\n";

// Get roles
$roles = Role::all();
echo "Available roles:\n";
foreach ($roles as $role) {
    echo "- ID: {$role->id}, Name: {$role->name}, Slug: {$role->slug}\n";
}
echo "\n";

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
    echo "No projects found in the database. Creating a test project...\n";

    // Get a client
    $client = Client::first();
    if (!$client) {
        echo "No clients found in the database. Please create a client first.\n";
        exit;
    }

    // Create a test project
    $project = Project::create([
        'name' => 'Test Project for Save Clients/Users',
        'description' => 'This is a test project created by the test script',
        'client_id' => $client->id,
        'status' => 'active',
    ]);

    echo "Created test project: {$project->name} (ID: {$project->id})\n";
} else {
    echo "Using existing project: {$project->name} (ID: {$project->id})\n";
}

echo "Testing with Manager: {$manager->name} (ID: {$manager->id})\n\n";

// Log in as the manager
Auth::login($manager);

// Test 1: Verify API routes for attach-clients and attach-users
echo "TEST 1: Verify API routes for attach-clients and attach-users\n";

$attachClientsRoute = Route::getRoutes()->getByName('projects.attach-clients');
$attachUsersRoute = Route::getRoutes()->getByName('projects.attach-users');

if ($attachClientsRoute) {
    echo "SUCCESS: attach-clients route exists\n";
} else {
    echo "FAILURE: attach-clients route does not exist\n";
}

if ($attachUsersRoute) {
    echo "SUCCESS: attach-users route exists\n";
} else {
    echo "FAILURE: attach-users route does not exist\n";
}

// Test 2: Test saving clients
echo "\nTEST 2: Test saving clients\n";

// Find a client not already assigned to the project
$newClient = Client::whereDoesntHave('projects', function($query) use ($project) {
    $query->where('projects.id', $project->id);
})->first();

if (!$newClient) {
    echo "No available clients to add to the project. Creating a new client...\n";

    // Create a new client
    $newClient = Client::create([
        'name' => 'Test Client for Save Functionality',
        'email' => 'test-client-save-' . time() . '@example.com',
    ]);

    echo "Created test client: {$newClient->name} (ID: {$newClient->id})\n";
}

// Find a role to assign
$clientRoleToAssign = Role::where('slug', 'manager')->first();
if (!$clientRoleToAssign) {
    $clientRoleToAssign = $roles->first();
}

echo "Testing saving client {$newClient->name} with role {$clientRoleToAssign->name} (ID: {$clientRoleToAssign->id})...\n";

try {
    // Prepare the client data
    $clientData = [
        'client_ids' => [
            [
                'id' => $newClient->id,
                'role_id' => $clientRoleToAssign->id
            ]
        ]
    ];

    // Call the API endpoint directly
    $response = app()->call('\App\Http\Controllers\Api\ProjectController@attachClients', [
        'request' => new \Illuminate\Http\Request($clientData),
        'project' => $project
    ]);

    if ($response->getStatusCode() === 200) {
        echo "SUCCESS: Client saved successfully\n";

        // Verify the client was added with the correct role
        $projectClient = DB::table('project_client')
            ->where('project_id', $project->id)
            ->where('client_id', $newClient->id)
            ->first();

        if ($projectClient) {
            $roleName = Role::find($projectClient->role_id)->name ?? "Unknown Role ({$projectClient->role_id})";
            echo "Client saved with Role ID: {$projectClient->role_id}, Role Name: {$roleName}\n";

            if ($projectClient->role_id == $clientRoleToAssign->id) {
                echo "SUCCESS: Role ID matches the assigned role\n";
            } else {
                echo "FAILURE: Role ID does not match the assigned role\n";
            }
        } else {
            echo "FAILURE: Client was not added to the project\n";
        }
    } else {
        echo "FAILURE: API call failed with status code " . $response->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
}

// Test 3: Test saving users
echo "\nTEST 3: Test saving users\n";

// Find a user not already assigned to the project
$newUser = User::whereDoesntHave('projects', function($query) use ($project) {
    $query->where('projects.id', $project->id);
})->first();

if (!$newUser) {
    echo "No available users to add to the project. Creating a new user...\n";

    // Create a new user
    $employeeRole = Role::where('slug', 'employee')->first();
    $newUser = User::create([
        'name' => 'Test User for Save Functionality',
        'email' => 'test-user-save-' . time() . '@example.com',
        'password' => bcrypt('password'),
        'role_id' => $employeeRole->id,
    ]);

    echo "Created test user: {$newUser->name} (ID: {$newUser->id})\n";
}

// Find a role to assign
$userRoleToAssign = Role::where('slug', 'employee')->first();
if (!$userRoleToAssign) {
    $userRoleToAssign = $roles->first();
}

echo "Testing saving user {$newUser->name} with role {$userRoleToAssign->name} (ID: {$userRoleToAssign->id})...\n";

try {
    // Prepare the user data
    $userData = [
        'user_ids' => [
            [
                'id' => $newUser->id,
                'role_id' => $userRoleToAssign->id
            ]
        ]
    ];

    // Call the API endpoint directly
    $response = app()->call('\App\Http\Controllers\Api\ProjectController@attachUsers', [
        'request' => new \Illuminate\Http\Request($userData),
        'project' => $project
    ]);

    if ($response->getStatusCode() === 200) {
        echo "SUCCESS: User saved successfully\n";

        // Verify the user was added with the correct role
        $projectUser = DB::table('project_user')
            ->where('project_id', $project->id)
            ->where('user_id', $newUser->id)
            ->first();

        if ($projectUser) {
            $roleName = Role::find($projectUser->role_id)->name ?? "Unknown Role ({$projectUser->role_id})";
            echo "User saved with Role ID: {$projectUser->role_id}, Role Name: {$roleName}\n";

            if ($projectUser->role_id == $userRoleToAssign->id) {
                echo "SUCCESS: Role ID matches the assigned role\n";
            } else {
                echo "FAILURE: Role ID does not match the assigned role\n";
            }
        } else {
            echo "FAILURE: User was not added to the project\n";
        }
    } else {
        echo "FAILURE: API call failed with status code " . $response->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
}

// Clean up - remove the test client and user from the project
$project->clients()->detach($newClient->id);
$project->users()->detach($newUser->id);
echo "\nTest client and user removed from project.\n";

echo "\nTest completed.\n";
