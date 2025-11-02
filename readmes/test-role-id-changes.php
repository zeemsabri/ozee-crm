<?php

// This is a simple test script to verify that the role_id changes work correctly
// Run this script with: php test-role-id-changes.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\Role;
use App\Models\User;

echo "Testing role_id changes...\n\n";

// Find users with different roles
$superAdmin = User::where('role', 'super_admin')->first();
$manager = User::where('role', 'manager')->first();
$employee = User::where('role', 'employee')->first();
$contractor = User::where('role', 'contractor')->first();

// Check if we have users with these roles
if (! $superAdmin) {
    echo "Super Admin not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

if (! $manager) {
    echo "Manager not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

if (! $employee) {
    echo "Employee not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

if (! $contractor) {
    echo "Contractor not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Get all roles
$roles = Role::all();
echo "Available roles in the system:\n";
foreach ($roles as $role) {
    echo "- {$role->name} (ID: {$role->id}, Slug: {$role->slug})\n";
}
echo "\n";

// Test user role_id values
echo "Testing User role_id values:\n";
echo "Super Admin role: {$superAdmin->role}, role_id: {$superAdmin->role_id}\n";
echo "Manager role: {$manager->role}, role_id: {$manager->role_id}\n";
echo "Employee role: {$employee->role}, role_id: {$employee->role_id}\n";
echo "Contractor role: {$contractor->role}, role_id: {$contractor->role_id}\n\n";

// Test role() relationship
echo "Testing role() relationship:\n";
if ($superAdmin->role_id) {
    echo "Super Admin role name from relationship: {$superAdmin->role->name}\n";
}
if ($manager->role_id) {
    echo "Manager role name from relationship: {$manager->role->name}\n";
}
if ($employee->role_id) {
    echo "Employee role name from relationship: {$employee->role->name}\n";
}
if ($contractor->role_id) {
    echo "Contractor role name from relationship: {$contractor->role->name}\n";
}
echo "\n";

// Test hasPermission method
echo "Testing hasPermission method:\n";
echo 'Super Admin has view_clients permission: '.($superAdmin->hasPermission('view_clients') ? 'Yes' : 'No')."\n";
echo 'Manager has view_clients permission: '.($manager->hasPermission('view_clients') ? 'Yes' : 'No')."\n";
echo 'Employee has view_clients permission: '.($employee->hasPermission('view_clients') ? 'Yes' : 'No')."\n";
echo 'Contractor has view_clients permission: '.($contractor->hasPermission('view_clients') ? 'Yes' : 'No')."\n\n";

// Test project roles
echo "Testing project roles:\n";
$project = Project::first();
if (! $project) {
    echo "No projects found in the database. Please create a project first.\n";
    exit;
}

// Make sure users are assigned to the project
if (! $project->users->contains($superAdmin->id)) {
    $project->users()->attach($superAdmin->id, ['role' => 'Admin', 'role_id' => $superAdmin->role_id]);
    echo "Assigned Super Admin to project with role 'Admin'\n";
}

if (! $project->users->contains($contractor->id)) {
    $project->users()->attach($contractor->id, ['role' => 'Contractor', 'role_id' => $contractor->role_id]);
    echo "Assigned Contractor to project with role 'Contractor'\n";
}

// Refresh the project to get the updated relationships
$project = $project->fresh(['users']);

// Check project user roles
foreach ($project->users as $user) {
    echo "User {$user->name} has project role: {$user->pivot->role}, role_id: {$user->pivot->role_id}\n";
}

echo "\nTest completed.\n";
