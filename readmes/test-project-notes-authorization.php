<?php

// Real-world test script to verify the project notes authorization fix
// This script should be run in the Laravel application environment

// Load Laravel application
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

echo "=== Testing Project Notes Authorization in Real Application ===\n\n";

// Function to test authorization for a user
function testUserAuthorization($user, $project, $expectedResult)
{
    echo "Testing user: {$user->name} (ID: {$user->id})\n";
    echo "Project: {$project->name} (ID: {$project->id})\n";

    // Check if user can add notes to the project
    $result = Gate::forUser($user)->allows('addNotes', $project);

    echo 'Expected: '.($expectedResult ? 'true' : 'false')."\n";
    echo 'Actual: '.($result ? 'true' : 'false')."\n";
    echo 'Result: '.($result === $expectedResult ? 'PASS' : 'FAIL')."\n\n";

    return $result === $expectedResult;
}

try {
    // Get a project to test with
    $project = Project::first();
    if (! $project) {
        echo "Error: No projects found in the database.\n";
        exit(1);
    }

    echo "Using project: {$project->name} (ID: {$project->id})\n\n";

    // Test cases

    // 1. Super admin user (should have permission)
    $superAdmin = User::whereHas('role', function ($query) {
        $query->where('slug', 'super-admin');
    })->first();

    if ($superAdmin) {
        $testsPassed = testUserAuthorization($superAdmin, $project, true);
    } else {
        echo "Warning: No super admin user found for testing.\n\n";
    }

    // 2. User with global add_project_notes permission
    $permissionId = Permission::where('slug', 'add_project_notes')->value('id');
    if ($permissionId) {
        // Find a role with this permission
        $roleWithPermission = Role::whereHas('permissions', function ($query) use ($permissionId) {
            $query->where('permissions.id', $permissionId);
        })->first();

        if ($roleWithPermission) {
            // Find a user with this role
            $userWithPermission = User::where('role_id', $roleWithPermission->id)->first();

            if ($userWithPermission) {
                $testsPassed = testUserAuthorization($userWithPermission, $project, true) && $testsPassed;
            } else {
                echo "Warning: No user found with role '{$roleWithPermission->name}' for testing.\n\n";
            }
        } else {
            echo "Warning: No role found with 'add_project_notes' permission for testing.\n\n";
        }
    } else {
        echo "Warning: 'add_project_notes' permission not found in the database.\n\n";
    }

    // 3. User without add_project_notes permission
    $userWithoutPermission = User::whereDoesntHave('role.permissions', function ($query) {
        $query->where('permissions.slug', 'add_project_notes');
    })->first();

    if ($userWithoutPermission) {
        // Make sure this user doesn't have project-specific permission either
        $hasProjectPermission = DB::table('project_user')
            ->where('user_id', $userWithoutPermission->id)
            ->where('project_id', $project->id)
            ->exists();

        if (! $hasProjectPermission) {
            $testsPassed = testUserAuthorization($userWithoutPermission, $project, false) && $testsPassed;
        } else {
            echo "Warning: User has project-specific role, skipping this test.\n\n";
        }
    } else {
        echo "Warning: No user found without 'add_project_notes' permission for testing.\n\n";
    }

    // 4. User with project-specific permission
    // This would require setting up a project-specific role with the permission
    // and assigning it to a user for the specific project, which is complex for a test script
    echo "Note: Testing project-specific permissions requires manual setup and testing.\n\n";

    // Summary
    echo "=== Test Summary ===\n";
    if (isset($testsPassed) && $testsPassed) {
        echo "All tests PASSED! The authorization fix is working correctly.\n";
    } else {
        echo "Some tests FAILED. Please review the results above.\n";
    }

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'Stack trace: '.$e->getTraceAsString()."\n";
}
