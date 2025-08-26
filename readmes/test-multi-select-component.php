<?php

// This is a simple test script to verify that the MultiSelectWithRoles component works correctly
// Run this script with: php test-multi-select-component.php

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

echo "Testing MultiSelectWithRoles component functionality...\n\n";

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
        'name' => 'Test Project for MultiSelectWithRoles',
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
echo "\n";

// Check current project users and their roles
echo "Current project users and their roles:\n";
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
echo "\n";

// Test adding a client with a specific role
echo "Testing adding a client with a specific role...\n";

// Find a client not already assigned to the project
$newClient = Client::whereDoesntHave('projects', function($query) use ($project) {
    $query->where('projects.id', $project->id);
})->first();

if (!$newClient) {
    echo "No available clients to add to the project. Creating a new client...\n";

    // Create a new client
    $newClient = Client::create([
        'name' => 'Test Client for MultiSelectWithRoles',
        'email' => 'test-client-' . time() . '@example.com',
    ]);

    echo "Created test client: {$newClient->name} (ID: {$newClient->id})\n";
}

// Find a role to assign
$clientRoleToAssign = Role::where('slug', 'manager')->first();
if (!$clientRoleToAssign) {
    $clientRoleToAssign = $roles->first();
}

echo "Adding client {$newClient->name} with role {$clientRoleToAssign->name} (ID: {$clientRoleToAssign->id})...\n";

// Add the client to the project with the role
$project->clients()->attach($newClient->id, ['role_id' => $clientRoleToAssign->id]);

// Verify the client was added with the correct role
$projectClient = DB::table('project_client')
    ->where('project_id', $project->id)
    ->where('client_id', $newClient->id)
    ->first();

if ($projectClient) {
    $roleName = Role::find($projectClient->role_id)->name ?? "Unknown Role ({$projectClient->role_id})";
    echo "Client added successfully with Role ID: {$projectClient->role_id}, Role Name: {$roleName}\n";

    if ($projectClient->role_id == $clientRoleToAssign->id) {
        echo "SUCCESS: Role ID matches the assigned role.\n";
    } else {
        echo "FAILURE: Role ID does not match the assigned role.\n";
    }
} else {
    echo "FAILURE: Client was not added to the project.\n";
}

// Test adding a user with a specific role
echo "\nTesting adding a user with a specific role...\n";

// Find a user not already assigned to the project
$newUser = User::whereDoesntHave('projects', function($query) use ($project) {
    $query->where('projects.id', $project->id);
})->first();

if (!$newUser) {
    echo "No available users to add to the project. Creating a new user...\n";

    // Create a new user
    $employeeRole = Role::where('slug', 'employee')->first();
    $newUser = User::create([
        'name' => 'Test User for MultiSelectWithRoles',
        'email' => 'test-user-' . time() . '@example.com',
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

echo "Adding user {$newUser->name} with role {$userRoleToAssign->name} (ID: {$userRoleToAssign->id})...\n";

// Add the user to the project with the role
$project->users()->attach($newUser->id, ['role_id' => $userRoleToAssign->id]);

// Verify the user was added with the correct role
$projectUser = DB::table('project_user')
    ->where('project_id', $project->id)
    ->where('user_id', $newUser->id)
    ->first();

if ($projectUser) {
    $roleName = Role::find($projectUser->role_id)->name ?? "Unknown Role ({$projectUser->role_id})";
    echo "User added successfully with Role ID: {$projectUser->role_id}, Role Name: {$roleName}\n";

    if ($projectUser->role_id == $userRoleToAssign->id) {
        echo "SUCCESS: Role ID matches the assigned role.\n";
    } else {
        echo "FAILURE: Role ID does not match the assigned role.\n";
    }
} else {
    echo "FAILURE: User was not added to the project.\n";
}

// Clean up - remove the test client and user from the project
$project->clients()->detach($newClient->id);
$project->users()->detach($newUser->id);
echo "\nTest client and user removed from project.\n";

echo "\nTest completed.\n";
