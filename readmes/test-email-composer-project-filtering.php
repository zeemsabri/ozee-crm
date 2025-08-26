<?php

// This script tests the project filtering for the Email Composer page
// It verifies that users only see projects they have permission to send emails from

echo "Testing Email Composer project filtering\n";
echo "--------------------------------------\n\n";

// Import necessary classes
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Project;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Find or create test permissions
echo "Setting up test permissions...\n";
$composeEmailsPermission = Permission::firstOrCreate(
    ['slug' => 'compose_emails'],
    [
        'name' => 'Compose emails',
        'description' => 'Permission to compose emails',
        'category' => 'emails'
    ]
);
echo "- Permission 'compose_emails' " . ($composeEmailsPermission->wasRecentlyCreated ? 'created' : 'already exists') . "\n\n";

// Find or create test roles with different permission combinations
echo "Setting up test roles...\n";

// Role with compose_emails permission
$roleWithPermission = Role::firstOrCreate(
    ['slug' => 'email_composer'],
    [
        'name' => 'Email Composer',
        'description' => 'Role with permission to compose emails',
        'type' => 'application'
    ]
);
echo "- Role 'email_composer' " . ($roleWithPermission->wasRecentlyCreated ? 'created' : 'already exists') . "\n";

// Assign the permission to the role
DB::table('role_permission')->updateOrInsert(
    ['role_id' => $roleWithPermission->id, 'permission_id' => $composeEmailsPermission->id],
    ['created_at' => now(), 'updated_at' => now()]
);
echo "  - Assigned 'compose_emails' permission to 'email_composer' role\n";

// Role without compose_emails permission
$roleWithoutPermission = Role::firstOrCreate(
    ['slug' => 'no_email_access'],
    [
        'name' => 'No Email Access',
        'description' => 'Role without permission to compose emails',
        'type' => 'application'
    ]
);
echo "- Role 'no_email_access' " . ($roleWithoutPermission->wasRecentlyCreated ? 'created' : 'already exists') . "\n";

// Make sure the role doesn't have the permission
DB::table('role_permission')
    ->where('role_id', $roleWithoutPermission->id)
    ->where('permission_id', $composeEmailsPermission->id)
    ->delete();
echo "  - Removed 'compose_emails' permission from 'no_email_access' role\n";

// Project-specific role with compose_emails permission
$projectRoleWithPermission = Role::firstOrCreate(
    ['slug' => 'project_email_composer', 'type' => 'project'],
    [
        'name' => 'Project Email Composer',
        'description' => 'Project-specific role with permission to compose emails',
        'type' => 'project'
    ]
);
echo "- Role 'project_email_composer' " . ($projectRoleWithPermission->wasRecentlyCreated ? 'created' : 'already exists') . "\n";

// Assign the permission to the project role
DB::table('role_permission')->updateOrInsert(
    ['role_id' => $projectRoleWithPermission->id, 'permission_id' => $composeEmailsPermission->id],
    ['created_at' => now(), 'updated_at' => now()]
);
echo "  - Assigned 'compose_emails' permission to 'project_email_composer' role\n\n";

// Find or create test users with different roles
echo "Setting up test users...\n";

// User with global compose_emails permission
$userWithGlobalPermission = User::firstOrCreate(
    ['email' => 'global_email_composer@example.com'],
    [
        'name' => 'Global Email Composer',
        'password' => bcrypt('password'),
        'role_id' => $roleWithPermission->id
    ]
);
if ($userWithGlobalPermission->role_id != $roleWithPermission->id) {
    $userWithGlobalPermission->role_id = $roleWithPermission->id;
    $userWithGlobalPermission->save();
}
echo "- User 'global_email_composer@example.com' " . ($userWithGlobalPermission->wasRecentlyCreated ? 'created' : 'already exists') . " with 'email_composer' role\n";

// User without global compose_emails permission
$userWithoutGlobalPermission = User::firstOrCreate(
    ['email' => 'no_global_email_access@example.com'],
    [
        'name' => 'No Global Email Access',
        'password' => bcrypt('password'),
        'role_id' => $roleWithoutPermission->id
    ]
);
if ($userWithoutGlobalPermission->role_id != $roleWithoutPermission->id) {
    $userWithoutGlobalPermission->role_id = $roleWithoutPermission->id;
    $userWithoutGlobalPermission->save();
}
echo "- User 'no_global_email_access@example.com' " . ($userWithoutGlobalPermission->wasRecentlyCreated ? 'created' : 'already exists') . " with 'no_email_access' role\n\n";

// Find or create test projects
echo "Setting up test projects...\n";

// Project 1 - For testing global permissions
$project1 = Project::firstOrCreate(
    ['name' => 'Test Project for Global Permissions'],
    [
        'description' => 'This is a test project for testing global permissions',
        'status' => 'active',
        'client_id' => 1 // Assuming client with ID 1 exists
    ]
);
echo "- Project 'Test Project for Global Permissions' " . ($project1->wasRecentlyCreated ? 'created' : 'already exists') . "\n";

// Project 2 - For testing project-specific permissions
$project2 = Project::firstOrCreate(
    ['name' => 'Test Project for Project-Specific Permissions'],
    [
        'description' => 'This is a test project for testing project-specific permissions',
        'status' => 'active',
        'client_id' => 1 // Assuming client with ID 1 exists
    ]
);
echo "- Project 'Test Project for Project-Specific Permissions' " . ($project2->wasRecentlyCreated ? 'created' : 'already exists') . "\n\n";

// Assign users to projects with specific roles
echo "Assigning users to projects with specific roles...\n";

// Assign user without global permission to project 2 with project-specific permission
DB::table('project_user')->updateOrInsert(
    ['project_id' => $project2->id, 'user_id' => $userWithoutGlobalPermission->id],
    ['role_id' => $projectRoleWithPermission->id, 'created_at' => now(), 'updated_at' => now()]
);
echo "- Assigned 'no_global_email_access@example.com' to 'Test Project for Project-Specific Permissions' with 'project_email_composer' role\n\n";

// Test scenario 1: User with global compose_emails permission
echo "Test scenario 1: User with global compose_emails permission\n";
echo "------------------------------------------------------\n";
Auth::login($userWithGlobalPermission);
echo "Logged in as {$userWithGlobalPermission->name} (global 'email_composer' role)\n";

$response = app()->call('\App\Http\Controllers\Api\ProjectController@getProjectsForEmailComposer');
$projects = $response->getData(true);

echo "Number of projects returned: " . count($projects) . "\n";
echo "Projects:\n";
foreach ($projects as $project) {
    echo "- {$project['name']} (ID: {$project['id']})\n";
}
echo "\n";

// Test scenario 2: User without global permission but with project-specific permission
echo "Test scenario 2: User without global permission but with project-specific permission\n";
echo "----------------------------------------------------------------------------\n";
Auth::login($userWithoutGlobalPermission);
echo "Logged in as {$userWithoutGlobalPermission->name} (global 'no_email_access' role, project-specific 'project_email_composer' role)\n";

$response = app()->call('\App\Http\Controllers\Api\ProjectController@getProjectsForEmailComposer');
$projects = $response->getData(true);

echo "Number of projects returned: " . count($projects) . "\n";
echo "Projects:\n";
foreach ($projects as $project) {
    echo "- {$project['name']} (ID: {$project['id']})\n";
}
echo "\n";

// Verify results
echo "Verification:\n";
echo "------------\n";
echo "User with global permission should see all projects: " . (count($projects) >= 2 ? "PASS" : "FAIL") . "\n";
echo "User without global permission but with project-specific permission should see only that project: " . (count($projects) == 1 && $projects[0]['id'] == $project2->id ? "PASS" : "FAIL") . "\n";

echo "\nTest completed.\n";
