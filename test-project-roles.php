<?php

// This is a simple test script to verify that project roles are working correctly
// Run this script with: php test-project-roles.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "Testing project roles functionality...\n\n";

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
    echo "No projects found in the database. Please create a project first.\n";
    exit;
}

echo "Testing with Project: {$project->name} (ID: {$project->id})\n";
echo "Testing with Manager: {$manager->name} (ID: {$manager->id})\n\n";

// Log in as the manager
Auth::login($manager);

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

// Test adding a user with a specific role
echo "Testing adding a user with a specific role...\n";

// Find a user not already assigned to the project
$newUser = User::whereDoesntHave('projects', function($query) use ($project) {
    $query->where('projects.id', $project->id);
})->first();

if (!$newUser) {
    echo "No available users to add to the project. Please create more users.\n";
    exit;
}

// Find a role to assign
$roleToAssign = Role::where('slug', 'employee')->first();
if (!$roleToAssign) {
    $roleToAssign = $roles->first();
}

echo "Adding user {$newUser->name} with role {$roleToAssign->name} (ID: {$roleToAssign->id})...\n";

// Add the user to the project with the role
$project->users()->attach($newUser->id, ['role_id' => $roleToAssign->id]);

// Verify the user was added with the correct role
$projectUser = DB::table('project_user')
    ->where('project_id', $project->id)
    ->where('user_id', $newUser->id)
    ->first();

if ($projectUser) {
    $roleName = Role::find($projectUser->role_id)->name ?? "Unknown Role ({$projectUser->role_id})";
    echo "User added successfully with Role ID: {$projectUser->role_id}, Role Name: {$roleName}\n";

    if ($projectUser->role_id == $roleToAssign->id) {
        echo "SUCCESS: Role ID matches the assigned role.\n";
    } else {
        echo "FAILURE: Role ID does not match the assigned role.\n";
    }
} else {
    echo "FAILURE: User was not added to the project.\n";
}

// Clean up - remove the test user from the project
$project->users()->detach($newUser->id);
echo "Test user removed from project.\n";

echo "\nTest completed.\n";
