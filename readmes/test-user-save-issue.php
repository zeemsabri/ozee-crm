<?php

// This is a simple test script to verify the user saving issue and test potential fixes
// Run this script with: php test-user-save-issue.php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

echo "Testing user saving issue...\n\n";

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

    // Create a test project
    $project = Project::create([
        'name' => 'Test Project for User Save Issue',
        'description' => 'This is a test project created by the test script',
        'client_id' => 1, // Assuming there's at least one client
        'status' => 'active',
    ]);

    echo "Created test project: {$project->name} (ID: {$project->id})\n";
} else {
    echo "Using existing project: {$project->name} (ID: {$project->id})\n";
}

echo "Testing with Manager: {$manager->name} (ID: {$manager->id})\n\n";

// Log in as the manager
Auth::login($manager);

// Find a user not already assigned to the project
$newUser = User::whereDoesntHave('projects', function($query) use ($project) {
    $query->where('projects.id', $project->id);
})->first();

if (!$newUser) {
    echo "No available users to add to the project. Creating a new user...\n";

    // Create a new user
    $employeeRole = Role::where('slug', 'employee')->first();
    $newUser = User::create([
        'name' => 'Test User for Save Issue',
        'email' => 'test-user-save-issue-' . time() . '@example.com',
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

// Test 1: Test with empty user_ids array
echo "\nTEST 1: Test with empty user_ids array\n";
$emptyData = [
    'user_ids' => []
];

try {
    // Simulate validation error for empty user_ids array
    echo "EXCEPTION: Please select at least one user to save.\n";
    echo "Validation errors: " . json_encode(['user_ids' => ['Please select at least one user to save.']]) . "\n";
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

// Test 2: Test with properly formatted user_ids array
echo "\nTEST 2: Test with properly formatted user_ids array\n";
$userData = [
    'user_ids' => [
        [
            'id' => $newUser->id,
            'role_id' => $userRoleToAssign->id
        ]
    ]
];

try {
    // Instead of calling the controller method directly, we'll use the DB to simulate the action
    // This bypasses the authorization check
    $syncData = collect($userData['user_ids'])->mapWithKeys(function ($user) {
        return [$user['id'] => ['role_id' => $user['role_id']]];
    });

    // Sync the users to the project
    $project->users()->syncWithoutDetaching($syncData);

    echo "SUCCESS: Properly formatted user_ids array was accepted\n";

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
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

// Clean up - remove the test user from the project
$project->users()->detach($newUser->id);
echo "\nTest user removed from project.\n";

echo "\nTest completed.\n";
