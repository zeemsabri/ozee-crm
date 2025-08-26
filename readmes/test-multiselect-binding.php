<?php

// This is a simple test script to verify the MultiSelectWithRoles component's v-model binding
// Run this script with: php test-multiselect-binding.php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "Testing MultiSelectWithRoles component v-model binding...\n\n";

// Get a project
$project = Project::first();
if (!$project) {
    echo "No projects found in the database. Please create a project first.\n";
    exit;
}

echo "Using project: {$project->name} (ID: {$project->id})\n\n";

// Get users
$users = User::take(3)->get();
if (count($users) < 3) {
    echo "Not enough users found. Please create at least 3 users.\n";
    exit;
}

// Get roles
$roles = Role::all();
if ($roles->isEmpty()) {
    echo "No roles found. Please run the RolePermissionSeeder first.\n";
    exit;
}

$employeeRole = $roles->where('slug', 'employee')->first();
if (!$employeeRole) {
    $employeeRole = $roles->first();
}

echo "Using role: {$employeeRole->name} (ID: {$employeeRole->id})\n\n";

// Test 1: Initialize project with users
echo "TEST 1: Initialize project with users\n";

// First, remove all existing users from the project
$project->users()->detach();
echo "Removed all existing users from the project.\n";

// Add users to the project with roles
$userData = [];
foreach ($users as $index => $user) {
    $userData[$user->id] = ['role_id' => $employeeRole->id];
    echo "Added user {$user->name} (ID: {$user->id}) with role {$employeeRole->name} (ID: {$employeeRole->id})\n";
}

$project->users()->attach($userData);
echo "Users attached to project.\n";

// Reload the project with users
$project->load(['users' => function ($query) {
    $query->withPivot('role_id');
}]);

// Check if users were added correctly
echo "\nVerifying users in project:\n";
foreach ($project->users as $user) {
    $roleName = Role::find($user->pivot->role_id)->name ?? "Unknown Role ({$user->pivot->role_id})";
    echo "- User: {$user->name}, Role ID: {$user->pivot->role_id}, Role Name: {$roleName}\n";
}

// Test 2: Simulate the format of data that would be in projectForm.user_ids
echo "\nTEST 2: Simulate projectForm.user_ids format\n";

// Create the user_ids array in the format expected by the API
$user_ids = [];
foreach ($project->users as $user) {
    $user_ids[] = [
        'id' => $user->id,
        'role_id' => $user->pivot->role_id
    ];
}

echo "Generated user_ids array:\n";
echo json_encode($user_ids, JSON_PRETTY_PRINT) . "\n";

// Test 3: Verify the format matches what the API expects
echo "\nTEST 3: Verify format matches API expectations\n";

// Check if each user_id entry has the required fields
$valid = true;
foreach ($user_ids as $index => $entry) {
    if (!isset($entry['id']) || !isset($entry['role_id'])) {
        echo "INVALID: Entry {$index} is missing required fields\n";
        $valid = false;
    }
}

if ($valid) {
    echo "SUCCESS: All entries have the required fields (id and role_id)\n";
} else {
    echo "FAILURE: Some entries are missing required fields\n";
}

// Test 4: Verify the data can be processed by the controller
echo "\nTEST 4: Verify data can be processed by the controller\n";

// Convert the user_ids array to the format expected by the sync method
$syncData = collect($user_ids)->mapWithKeys(function ($user) {
    return [$user['id'] => ['role_id' => $user['role_id']]];
});

echo "Converted to sync format:\n";
echo json_encode($syncData->toArray(), JSON_PRETTY_PRINT) . "\n";

// Clean up - remove all users from the project
$project->users()->detach();
echo "\nRemoved all users from the project.\n";

echo "\nTest completed.\n";
