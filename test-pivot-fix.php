<?php

use App\Helpers\PermissionHelper;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// This script tests the fixed PermissionHelper::getUsersWithProjectPermission method

echo "Testing PermissionHelper::getUsersWithProjectPermission fix\n\n";

// Get a project ID to test with
$project = Project::first();

if (!$project) {
    echo "No projects found in the database. Please create a project first.\n";
    exit;
}

echo "Using project ID: {$project->id}\n";

// Get a permission slug to test with
$permission = DB::table('permissions')->first();

if (!$permission) {
    echo "No permissions found in the database. Please create permissions first.\n";
    exit;
}

$permissionSlug = $permission->slug;
echo "Using permission slug: {$permissionSlug}\n\n";

// Test the fixed method
try {
    echo "Testing getUsersWithProjectPermission method...\n";
    $users = PermissionHelper::getUsersWithProjectPermission($permissionSlug, $project->id);
    echo "Success! Found " . $users->count() . " users with project permission '{$permissionSlug}' for project {$project->id}\n";

    // Display the users
    if ($users->count() > 0) {
        echo "\nUsers with permission:\n";
        foreach ($users as $user) {
            echo "- {$user->name} (ID: {$user->id})\n";
        }
    }

    // Test getAllUsersWithPermission method which uses getUsersWithProjectPermission
    echo "\nTesting getAllUsersWithPermission method...\n";
    $allUsers = PermissionHelper::getAllUsersWithPermission($permissionSlug, $project->id);
    echo "Success! Found " . $allUsers->count() . " users with permission '{$permissionSlug}' (global or project-specific) for project {$project->id}\n";

    // Display the users
    if ($allUsers->count() > 0) {
        echo "\nAll users with permission (global or project-specific):\n";
        foreach ($allUsers as $user) {
            echo "- {$user->name} (ID: {$user->id})\n";
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
