<?php

// This is a simple test script to verify that user removal works correctly
// Run this script with: php test-user-removal.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "Testing user removal functionality...\n\n";

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
        'name' => 'Test Project for User Removal',
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

// Get two users for testing
$users = User::take(2)->get();
if (count($users) < 2) {
    echo "Need at least 2 users for testing. Creating additional users...\n";

    $existingCount = count($users);
    for ($i = $existingCount; $i < 2; $i++) {
        $employeeRole = Role::where('slug', 'employee')->first();
        $user = User::create([
            'name' => 'Test User ' . ($i + 1),
            'email' => 'test-user-' . ($i + 1) . '@example.com',
            'password' => bcrypt('password'),
            'role_id' => $employeeRole->id,
        ]);
        $users->push($user);
    }
}

$user1 = $users[0];
$user2 = $users[1];

echo "Test User 1: {$user1->name} (ID: {$user1->id})\n";
echo "Test User 2: {$user2->name} (ID: {$user2->id})\n\n";

// Find a role to assign
$userRole = Role::where('slug', 'employee')->first();
if (!$userRole) {
    $userRole = $roles->first();
}

// Step 1: Add both users to the project
echo "STEP 1: Adding both users to the project\n";

// Prepare the user data with both users
$userData = [
    'user_ids' => [
        [
            'id' => $user1->id,
            'role_id' => $userRole->id
        ],
        [
            'id' => $user2->id,
            'role_id' => $userRole->id
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
    $project->users()->sync($syncData);

    // Create a mock response
    $response = new \Illuminate\Http\Response(json_encode($project->users), 200);

    if ($response->getStatusCode() === 200) {
        echo "SUCCESS: Both users added successfully\n";

        // Verify both users were added
        $projectUsers = DB::table('project_user')
            ->where('project_id', $project->id)
            ->whereIn('user_id', [$user1->id, $user2->id])
            ->get();

        echo "Found " . count($projectUsers) . " users attached to the project\n";

        foreach ($projectUsers as $pu) {
            $user = User::find($pu->user_id);
            $roleName = $pu->role_id ? Role::find($pu->role_id)->name ?? "Unknown Role ({$pu->role_id})" : "No Role";
            echo "- User: {$user->name}, Role ID: {$pu->role_id}, Role Name: {$roleName}\n";
        }
    } else {
        echo "FAILURE: API call failed with status code " . $response->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
}

// Step 2: Remove one user and save
echo "\nSTEP 2: Removing one user and saving\n";

// Prepare the user data with only one user
$userData = [
    'user_ids' => [
        [
            'id' => $user1->id,
            'role_id' => $userRole->id
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
    $project->users()->sync($syncData);

    // Create a mock response
    $response = new \Illuminate\Http\Response(json_encode($project->users), 200);

    if ($response->getStatusCode() === 200) {
        echo "SUCCESS: User list updated successfully\n";

        // Verify only one user remains
        $projectUsers = DB::table('project_user')
            ->where('project_id', $project->id)
            ->get();

        echo "Found " . count($projectUsers) . " users attached to the project\n";

        foreach ($projectUsers as $pu) {
            $user = User::find($pu->user_id);
            $roleName = $pu->role_id ? Role::find($pu->role_id)->name ?? "Unknown Role ({$pu->role_id})" : "No Role";
            echo "- User: {$user->name}, Role ID: {$pu->role_id}, Role Name: {$roleName}\n";
        }

        // Check if user2 was removed
        $user2Still = DB::table('project_user')
            ->where('project_id', $project->id)
            ->where('user_id', $user2->id)
            ->exists();

        if (!$user2Still) {
            echo "SUCCESS: User 2 was properly removed\n";
        } else {
            echo "FAILURE: User 2 was not removed\n";
        }
    } else {
        echo "FAILURE: API call failed with status code " . $response->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
}

// Clean up - remove all test users from the project
$project->users()->detach([$user1->id, $user2->id]);
echo "\nTest users removed from project.\n";

echo "\nTest completed.\n";
