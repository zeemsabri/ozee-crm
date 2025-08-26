<?php

// This script tests the permission-based checks functionality
// Run this script to verify that permissions are correctly loaded and used

echo "Testing permission-based checks functionality\n";
echo "-------------------------------------------\n\n";

// Import necessary classes
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Project;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Find a test project
$project = Project::first();
if (!$project) {
    echo "No projects found. Please create a project first.\n";
    exit(1);
}

echo "Found project: {$project->name} (ID: {$project->id})\n\n";

// Find or create test permissions
$permissions = [
    'view_project_financial' => 'View project financial information',
    'view_project_transactions' => 'View project transactions',
    'view_client_contacts' => 'View client contacts',
    'view_client_financial' => 'View client financial information',
    'view_users' => 'View users',
    'manage_projects' => 'Manage projects',
    'view_emails' => 'View emails',
    'compose_emails' => 'Compose emails'
];

echo "Setting up test permissions...\n";
foreach ($permissions as $slug => $name) {
    $permission = Permission::firstOrCreate(
        ['slug' => $slug],
        [
            'name' => $name,
            'description' => "Permission to {$name}",
            'category' => 'projects'
        ]
    );
    echo "- Permission '{$slug}' " . ($permission->wasRecentlyCreated ? 'created' : 'already exists') . "\n";
}

// Find or create test roles with different permission combinations
echo "\nSetting up test roles...\n";

// Manager role with all permissions
$managerRole = Role::firstOrCreate(
    ['slug' => 'manager'],
    [
        'name' => 'Manager',
        'description' => 'Manager with access to most features',
        'type' => 'application'
    ]
);
echo "- Role 'manager' " . ($managerRole->wasRecentlyCreated ? 'created' : 'already exists') . "\n";

// Assign all permissions to manager role
foreach ($permissions as $slug => $name) {
    $permission = Permission::where('slug', $slug)->first();
    if ($permission) {
        DB::table('role_permission')->updateOrInsert(
            ['role_id' => $managerRole->id, 'permission_id' => $permission->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }
}
echo "  - Assigned all permissions to manager role\n";

// Employee role with limited permissions
$employeeRole = Role::firstOrCreate(
    ['slug' => 'employee'],
    [
        'name' => 'Employee',
        'description' => 'Regular employee with limited access',
        'type' => 'application'
    ]
);
echo "- Role 'employee' " . ($employeeRole->wasRecentlyCreated ? 'created' : 'already exists') . "\n";

// Assign limited permissions to employee role
$employeePermissions = ['view_emails', 'compose_emails'];
foreach ($employeePermissions as $slug) {
    $permission = Permission::where('slug', $slug)->first();
    if ($permission) {
        DB::table('role_permission')->updateOrInsert(
            ['role_id' => $employeeRole->id, 'permission_id' => $permission->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }
}
echo "  - Assigned limited permissions to employee role\n";

// Project Manager role with project-specific permissions
$projectManagerRole = Role::firstOrCreate(
    ['slug' => 'project-manager'],
    [
        'name' => 'Project Manager',
        'description' => 'Manager for specific projects',
        'type' => 'project'
    ]
);
echo "- Role 'project-manager' " . ($projectManagerRole->wasRecentlyCreated ? 'created' : 'already exists') . "\n";

// Assign project-specific permissions to project manager role
$projectManagerPermissions = [
    'view_project_financial',
    'view_project_transactions',
    'view_client_contacts',
    'view_users',
    'manage_projects'
];
foreach ($projectManagerPermissions as $slug) {
    $permission = Permission::where('slug', $slug)->first();
    if ($permission) {
        DB::table('role_permission')->updateOrInsert(
            ['role_id' => $projectManagerRole->id, 'permission_id' => $permission->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }
}
echo "  - Assigned project-specific permissions to project manager role\n";

// Find or create test users with different roles
echo "\nSetting up test users...\n";

// Manager user
$managerUser = User::firstOrCreate(
    ['email' => 'manager@example.com'],
    [
        'name' => 'Manager User',
        'password' => bcrypt('password'),
        'role_id' => $managerRole->id
    ]
);
if ($managerUser->role_id != $managerRole->id) {
    $managerUser->role_id = $managerRole->id;
    $managerUser->save();
}
echo "- User 'manager@example.com' " . ($managerUser->wasRecentlyCreated ? 'created' : 'already exists') . " with manager role\n";

// Employee user
$employeeUser = User::firstOrCreate(
    ['email' => 'employee@example.com'],
    [
        'name' => 'Employee User',
        'password' => bcrypt('password'),
        'role_id' => $employeeRole->id
    ]
);
if ($employeeUser->role_id != $employeeRole->id) {
    $employeeUser->role_id = $employeeRole->id;
    $employeeUser->save();
}
echo "- User 'employee@example.com' " . ($employeeUser->wasRecentlyCreated ? 'created' : 'already exists') . " with employee role\n";

// Assign project-specific role to employee user
echo "\nAssigning project-specific role to employee user...\n";
DB::table('project_user')->updateOrInsert(
    ['project_id' => $project->id, 'user_id' => $employeeUser->id],
    ['role_id' => $projectManagerRole->id, 'created_at' => now(), 'updated_at' => now()]
);
echo "- Assigned project manager role to employee user for project {$project->id}\n";

// Test API response for manager user
echo "\nTesting API response for manager user...\n";
Auth::login($managerUser);
$response = app()->call('\App\Http\Controllers\Api\ProjectController@show', [
    'project' => $project
]);
$responseData = $response->getData(true);

// Check if the response contains global permissions
if (!isset($managerUser->global_permissions)) {
    echo "ERROR: Manager user does not have global_permissions property.\n";
} else {
    echo "SUCCESS: Manager user has global_permissions property.\n";
    echo "Number of global permissions: " . count($managerUser->global_permissions) . "\n";

    // Check if all expected permissions are present
    $missingPermissions = [];
    foreach ($permissions as $slug => $name) {
        $found = false;
        foreach ($managerUser->global_permissions as $permission) {
            if ($permission['slug'] === $slug) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missingPermissions[] = $slug;
        }
    }

    if (count($missingPermissions) > 0) {
        echo "WARNING: Some expected permissions are missing: " . implode(', ', $missingPermissions) . "\n";
    } else {
        echo "SUCCESS: All expected permissions are present in global_permissions.\n";
    }
}

// Test API response for employee user with project-specific role
echo "\nTesting API response for employee user with project-specific role...\n";
Auth::login($employeeUser);
$response = app()->call('\App\Http\Controllers\Api\ProjectController@show', [
    'project' => $project
]);
$responseData = $response->getData(true);

// Find the employee user in the response
$employeeUserInResponse = null;
foreach ($responseData['users'] as $user) {
    if ($user['id'] === $employeeUser->id) {
        $employeeUserInResponse = $user;
        break;
    }
}

if (!$employeeUserInResponse) {
    echo "ERROR: Employee user not found in response.\n";
    exit(1);
}

// Check if the employee user has global permissions
if (!isset($employeeUser->global_permissions)) {
    echo "ERROR: Employee user does not have global_permissions property.\n";
} else {
    echo "SUCCESS: Employee user has global_permissions property.\n";
    echo "Number of global permissions: " . count($employeeUser->global_permissions) . "\n";

    // Check if expected global permissions are present
    $missingPermissions = [];
    foreach ($employeePermissions as $slug) {
        $found = false;
        foreach ($employeeUser->global_permissions as $permission) {
            if ($permission['slug'] === $slug) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missingPermissions[] = $slug;
        }
    }

    if (count($missingPermissions) > 0) {
        echo "WARNING: Some expected global permissions are missing: " . implode(', ', $missingPermissions) . "\n";
    } else {
        echo "SUCCESS: All expected global permissions are present.\n";
    }
}

// Check if the employee user has project-specific permissions
if (!isset($employeeUserInResponse['pivot']['role_data']['permissions'])) {
    echo "ERROR: Employee user does not have project-specific permissions in the response.\n";
    exit(1);
}

echo "SUCCESS: Employee user has project-specific permissions in the response.\n";
echo "Number of project-specific permissions: " . count($employeeUserInResponse['pivot']['role_data']['permissions']) . "\n";

// Check if expected project-specific permissions are present
$missingPermissions = [];
foreach ($projectManagerPermissions as $slug) {
    $found = false;
    foreach ($employeeUserInResponse['pivot']['role_data']['permissions'] as $permission) {
        if ($permission['slug'] === $slug) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $missingPermissions[] = $slug;
    }
}

if (count($missingPermissions) > 0) {
    echo "WARNING: Some expected project-specific permissions are missing: " . implode(', ', $missingPermissions) . "\n";
} else {
    echo "SUCCESS: All expected project-specific permissions are present.\n";
}

echo "\nTest completed successfully.\n";
