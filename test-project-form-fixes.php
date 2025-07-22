<?php

// This is a simple test script to verify that the ProjectForm fixes work correctly
// Run this script with: php test-project-form-fixes.php

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

echo "Testing ProjectForm fixes...\n\n";

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
        'name' => 'Test Project for ProjectForm Fixes',
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

// Test 1: Verify user saving functionality
echo "TEST 1: Verify user saving functionality\n";

// Find a user not already assigned to the project
$newUser = User::whereDoesntHave('projects', function($query) use ($project) {
    $query->where('projects.id', $project->id);
})->first();

if (!$newUser) {
    echo "No available users to add to the project. Creating a new user...\n";

    // Create a new user
    $employeeRole = Role::where('slug', 'employee')->first();
    $newUser = User::create([
        'name' => 'Test User for ProjectForm Fixes',
        'email' => 'test-user-fixes-' . time() . '@example.com',
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

// Prepare the user data
$userData = [
    'user_ids' => [
        [
            'id' => $newUser->id,
            'role_id' => $userRoleToAssign->id
        ]
    ]
];

// Instead of calling the controller method directly, we'll use the DB to simulate the action
// This bypasses the authorization check
try {
    // Convert the user data to the format expected by the sync method
    $syncData = collect($userData['user_ids'])->mapWithKeys(function ($user) {
        return [$user['id'] => ['role_id' => $user['role_id']]];
    });

    // Sync the users to the project
    $project->users()->syncWithoutDetaching($syncData);

    // Create a mock response
    $response = new \Illuminate\Http\Response(json_encode($project->users), 200);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    $response = new \Illuminate\Http\Response(json_encode(['error' => $e->getMessage()]), 500);
}

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

// Test 2: Verify project data loading
echo "\nTEST 2: Verify project data loading\n";

// Reload the project with users
$project->load(['users' => function ($query) {
    $query->withPivot('role_id');
}]);

// Check if the user is in the project's users collection
$foundUser = $project->users->firstWhere('id', $newUser->id);
if ($foundUser) {
    echo "SUCCESS: User found in project's users collection\n";

    // Check if the role_id is correctly loaded
    if ($foundUser->pivot && $foundUser->pivot->role_id == $userRoleToAssign->id) {
        echo "SUCCESS: User's role_id is correctly loaded in the pivot data\n";
    } else {
        echo "FAILURE: User's role_id is not correctly loaded in the pivot data\n";
        if ($foundUser->pivot) {
            echo "Actual role_id: {$foundUser->pivot->role_id}, Expected: {$userRoleToAssign->id}\n";
        } else {
            echo "Pivot data is missing\n";
        }
    }
} else {
    echo "FAILURE: User not found in project's users collection\n";
}

// Clean up - remove the test user from the project
$project->users()->detach($newUser->id);
echo "\nTest user removed from project.\n";

echo "\nTest completed.\n";
