<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

echo "Testing frontend compatibility with role_id changes...\n";

// Check if 'role' column exists in users table
echo "Checking if 'role' column exists in users table...\n";
$roleColumnExists = Schema::hasColumn('users', 'role');
echo $roleColumnExists ? "FAIL: 'role' column still exists in users table\n" : "PASS: 'role' column has been removed from users table\n";

// Check if 'role_id' column exists in users table
echo "Checking if 'role_id' column exists in users table...\n";
$roleIdColumnExists = Schema::hasColumn('users', 'role_id');
echo $roleIdColumnExists ? "PASS: 'role_id' column exists in users table\n" : "FAIL: 'role_id' column does not exist in users table\n";

// Check if 'role' column exists in project_user table
echo "Checking if 'role' column exists in project_user table...\n";
$roleColumnExists = Schema::hasColumn('project_user', 'role');
echo $roleColumnExists ? "FAIL: 'role' column still exists in project_user table\n" : "PASS: 'role' column has been removed from project_user table\n";

// Check if 'role_id' column exists in project_user table
echo "Checking if 'role_id' column exists in project_user table...\n";
$roleIdColumnExists = Schema::hasColumn('project_user', 'role_id');
echo $roleIdColumnExists ? "PASS: 'role_id' column exists in project_user table\n" : "FAIL: 'role_id' column does not exist in project_user table\n";

// Test User model
echo "\nTesting User model...\n";
$user = User::first();
if ($user) {
    echo "User found: {$user->name}\n";

    // Check if 'role' property exists
    echo "Checking if 'role' property exists...\n";
    $rolePropertyExists = isset($user->role);
    echo $rolePropertyExists ? "FAIL: 'role' property still exists\n" : "PASS: 'role' property has been removed\n";

    // Check if 'role_id' property exists
    echo "Checking if 'role_id' property exists...\n";
    $roleIdPropertyExists = isset($user->role_id);
    echo $roleIdPropertyExists ? "PASS: 'role_id' property exists\n" : "FAIL: 'role_id' property does not exist\n";

    // Check if 'role' relationship exists
    echo "Checking if 'role' relationship exists...\n";
    $roleRelationship = $user->role();
    $roleRelationshipExists = $roleRelationship !== null;
    echo $roleRelationshipExists ? "PASS: 'role' relationship exists\n" : "FAIL: 'role' relationship does not exist\n";

    // Check if role helper methods work
    echo "Checking if role helper methods work...\n";
    $isSuperAdmin = $user->isSuperAdmin();
    $isManager = $user->isManager();
    $isEmployee = $user->isEmployee();
    $isContractor = $user->isContractor();
    echo "Role helper methods: isSuperAdmin={$isSuperAdmin}, isManager={$isManager}, isEmployee={$isEmployee}, isContractor={$isContractor}\n";
    echo "PASS: Role helper methods are working\n";

    // Test projects relationship
    echo "\nTesting projects relationship...\n";
    $projects = $user->projects;

    if (count($projects) > 0) {
        echo 'Projects found: '.count($projects)."\n";

        // Check first project pivot
        $firstProject = $projects->first();
        echo "Checking project pivot...\n";

        // Check if 'role' exists in pivot
        echo "Checking if 'role' exists in pivot...\n";
        $pivotRoleExists = isset($firstProject->pivot->role);
        echo $pivotRoleExists ? "FAIL: 'role' still exists in pivot\n" : "PASS: 'role' has been removed from pivot\n";

        // Check if 'role_id' exists in pivot
        echo "Checking if 'role_id' exists in pivot...\n";
        $pivotRoleIdExists = isset($firstProject->pivot->role_id);
        echo $pivotRoleIdExists ? "PASS: 'role_id' exists in pivot\n" : "FAIL: 'role_id' does not exist in pivot\n";
    } else {
        echo "No projects found for this user\n";
    }
} else {
    echo "No users found in the database\n";
}

// Test Project model
echo "\nTesting Project model...\n";
$project = Project::first();
if ($project) {
    echo "Project found: {$project->name}\n";

    // Test users relationship
    echo "Testing users relationship...\n";
    $users = $project->users;

    if (count($users) > 0) {
        echo 'Users found: '.count($users)."\n";

        // Check first user pivot
        $firstUser = $users->first();
        echo "Checking user pivot...\n";

        // Check if 'role' exists in pivot
        echo "Checking if 'role' exists in pivot...\n";
        $pivotRoleExists = isset($firstUser->pivot->role);
        echo $pivotRoleExists ? "FAIL: 'role' still exists in pivot\n" : "PASS: 'role' has been removed from pivot\n";

        // Check if 'role_id' exists in pivot
        echo "Checking if 'role_id' exists in pivot...\n";
        $pivotRoleIdExists = isset($firstUser->pivot->role_id);
        echo $pivotRoleIdExists ? "PASS: 'role_id' exists in pivot\n" : "FAIL: 'role_id' does not exist in pivot\n";
    } else {
        echo "No users found for this project\n";
    }
} else {
    echo "No projects found in the database\n";
}

echo "\nTest completed.\n";
