<?php

// This is a simple test script to verify that the ProjectForm changes work correctly
// Run this script with: php test-project-form-changes.php

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

echo "Testing ProjectForm changes...\n\n";

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
        'name' => 'Test Project for ProjectForm Changes',
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

// Test 1: Verify API endpoints for clients and users
echo "TEST 1: Verify API endpoints for clients and users\n";

try {
    $clientsResponse = json_decode(file_get_contents(url('/api/clients')), true);
    if (isset($clientsResponse['data']) && is_array($clientsResponse['data'])) {
        echo "SUCCESS: Clients API endpoint returned " . count($clientsResponse['data']) . " clients\n";
    } else {
        echo "FAILURE: Clients API endpoint did not return expected data format\n";
    }
} catch (Exception $e) {
    echo "FAILURE: Error accessing clients API endpoint: " . $e->getMessage() . "\n";
}

try {
    $usersResponse = json_decode(file_get_contents(url('/api/users')), true);
    if (is_array($usersResponse)) {
        echo "SUCCESS: Users API endpoint returned " . count($usersResponse) . " users\n";
    } else {
        echo "FAILURE: Users API endpoint did not return expected data format\n";
    }
} catch (Exception $e) {
    echo "FAILURE: Error accessing users API endpoint: " . $e->getMessage() . "\n";
}

// Test 2: Verify project client and user relationships
echo "\nTEST 2: Verify project client and user relationships\n";

// Check current project clients and their roles
echo "Current project clients and their roles:\n";
$projectClients = DB::table('project_client')
    ->where('project_id', $project->id)
    ->get();

if ($projectClients->isEmpty()) {
    echo "No clients assigned to this project yet.\n";
} else {
    foreach ($projectClients as $pc) {
        $client = Client::find($pc->client_id);
        $roleName = $pc->role_id ? Role::find($pc->role_id)->name ?? "Unknown Role ({$pc->role_id})" : "No Role";
        echo "- Client: {$client->name}, Role ID: {$pc->role_id}, Role Name: {$roleName}\n";
    }
}

// Check current project users and their roles
echo "\nCurrent project users and their roles:\n";
$projectUsers = DB::table('project_user')
    ->where('project_id', $project->id)
    ->get();

if ($projectUsers->isEmpty()) {
    echo "No users assigned to this project yet.\n";
} else {
    foreach ($projectUsers as $pu) {
        $user = User::find($pu->user_id);
        $roleName = $pu->role_id ? Role::find($pu->role_id)->name ?? "Unknown Role ({$pu->role_id})" : "No Role";
        echo "- User: {$user->name}, Role ID: {$pu->role_id}, Role Name: {$roleName}\n";
    }
}

echo "\nTest completed.\n";
