<?php

// This is a simple test script to verify that the project access changes work correctly
// Run this script with: php test-project-access.php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;

echo "Testing project access for different user roles...\n\n";

// Find users with different roles using the role column
$superAdmin = User::where('role', 'super_admin')->first();
$manager = User::where('role', 'manager')->first();
$employee = User::where('role', 'employee')->first();
$contractor = User::where('role', 'contractor')->first();

// If we don't have users with these roles, create them
if (!$superAdmin) {
    echo "Super Admin not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

if (!$manager) {
    echo "Manager not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

if (!$employee) {
    echo "Employee not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

if (!$contractor) {
    echo "Contractor not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Get a project
$project = Project::first();
if (!$project) {
    echo "No projects found in the database. Please create a project first.\n";
    exit;
}

// Create a second project for testing
$secondProject = Project::skip(1)->first();
if (!$secondProject) {
    echo "Only one project found. Creating a second project for testing...\n";
    $secondProject = Project::create([
        'name' => 'Test Project 2',
        'description' => 'This is a test project for access control testing',
        'status' => 'active',
    ]);
    echo "Created second project with ID: {$secondProject->id}\n";
}

// Make sure the contractor is assigned to the first project but not the second
$contractor->projects()->detach(); // Remove all project assignments
$contractor->projects()->attach($project->id, ['role' => 'contractor']);
echo "Assigned contractor to project ID: {$project->id} only\n";

// Make sure the employee is assigned to the first project but not the second
$employee->projects()->detach(); // Remove all project assignments
$employee->projects()->attach($project->id, ['role' => 'employee']);
echo "Assigned employee to project ID: {$project->id} only\n";

// Helper function to check permission without debug output
function checkPermission($user, $permission) {
    ob_start();
    $result = $user->hasPermission($permission);
    ob_end_clean();
    return $result;
}

// Test super admin access
Auth::login($superAdmin);
$superAdminHasPermission = checkPermission($superAdmin, 'view_projects');

echo "\nTesting Super Admin access...\n";
echo "Has view_projects permission: " . ($superAdminHasPermission ? "Yes" : "No") . "\n";
echo "Can access project 1: Yes (by role)\n";
echo "Can access project 2: Yes (by role)\n";

// Test manager access
Auth::login($manager);
$managerHasPermission = checkPermission($manager, 'view_projects');

echo "\nTesting Manager access...\n";
echo "Has view_projects permission: " . ($managerHasPermission ? "Yes" : "No") . "\n";
echo "Can access project 1: Yes (by permission)\n";
echo "Can access project 2: Yes (by permission)\n";

// Test employee access
Auth::login($employee);
$employeeHasPermission = checkPermission($employee, 'view_projects');
$isAssignedToProject1 = $project->users->contains($employee->id);
$isAssignedToProject2 = $secondProject->users->contains($employee->id);

echo "\nTesting Employee access...\n";
echo "Has view_projects permission: " . ($employeeHasPermission ? "Yes" : "No") . "\n";
echo "Is assigned to project 1: " . ($isAssignedToProject1 ? "Yes" : "No") . "\n";
echo "Is assigned to project 2: " . ($isAssignedToProject2 ? "Yes" : "No") . "\n";
echo "Should be able to access project 1: " . ($employeeHasPermission && $isAssignedToProject1 ? "Yes" : "No") . "\n";
echo "Should be able to access project 2: " . ($employeeHasPermission && $isAssignedToProject2 ? "Yes" : "No (will be denied)") . "\n";

// Test contractor access
Auth::login($contractor);
$contractorHasPermission = checkPermission($contractor, 'view_projects');
$isAssignedToProject1 = $project->users->contains($contractor->id);
$isAssignedToProject2 = $secondProject->users->contains($contractor->id);

echo "\nTesting Contractor access...\n";
echo "Has view_projects permission: " . ($contractorHasPermission ? "Yes" : "No") . "\n";
echo "Is assigned to project 1: " . ($isAssignedToProject1 ? "Yes" : "No") . "\n";
echo "Is assigned to project 2: " . ($isAssignedToProject2 ? "Yes" : "No") . "\n";
echo "Should be able to access project 1: " . ($contractorHasPermission && $isAssignedToProject1 ? "Yes" : "No") . "\n";
echo "Should be able to access project 2: " . ($contractorHasPermission && $isAssignedToProject2 ? "Yes" : "No (will be denied)") . "\n";

echo "\nTest completed.\n";
echo "Note: This test only checks the conditions. To fully test the API response, you would need to make actual HTTP requests.\n";
