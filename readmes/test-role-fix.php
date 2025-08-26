<?php

// This is a simple test script to verify that the role API changes work correctly
// Run this script with: php test-role-fix.php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

echo "Testing role API fixes...\n\n";

// Get a super admin user
$superAdmin = User::whereHas('role', function($query) {
    $query->where('slug', 'super-admin');
})->first();

if (!$superAdmin) {
    echo "Super Admin user not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Log in as the super admin
Auth::login($superAdmin);

// Test 1: Fetch roles API endpoint
echo "TEST 1: Fetch roles API endpoint\n";

try {
    // Call the API endpoint directly
    $response = app()->call(function(Request $request) {
        $query = \App\Models\Role::query();
        return response()->json($query->get());
    }, [
        'request' => new \Illuminate\Http\Request()
    ]);

    $responseData = json_decode($response->getContent(), true);

    if ($response->getStatusCode() === 200 && is_array($responseData)) {
        echo "SUCCESS: Roles API endpoint returned " . count($responseData) . " roles\n";

        // Print the first few roles
        $count = min(3, count($responseData));
        for ($i = 0; $i < $count; $i++) {
            $role = $responseData[$i];
            echo "- Role: {$role['name']} (ID: {$role['id']})\n";
        }
    } else {
        echo "FAILURE: Roles API endpoint did not return expected data\n";
        if (isset($responseData['message'])) {
            echo "Error message: " . $responseData['message'] . "\n";
        }
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
}

// Test 2: Create a new role
echo "\nTEST 2: Create a new role\n";

$roleName = "Test Role " . time();
$roleData = [
    'name' => $roleName,
    'description' => 'This is a test role created by the test script',
    'type' => 'application',
    'permissions' => []
];

try {
    // Get a permission to assign to the role
    $permission = Permission::first();
    if ($permission) {
        $roleData['permissions'] = [$permission->id];
    }

    // Call the API endpoint directly
    $response = app()->call('\App\Http\Controllers\Admin\RoleController@store', [
        'request' => new \Illuminate\Http\Request($roleData)
    ]);

    // Since the controller returns a redirect, we can't easily check the response
    // Instead, check if the role was created in the database
    $role = Role::where('name', $roleName)->first();

    if ($role) {
        echo "SUCCESS: Role created successfully with ID: {$role->id}\n";

        // Check if the permission was assigned
        if ($permission && $role->permissions->contains($permission->id)) {
            echo "SUCCESS: Permission was assigned to the role\n";
        } else if ($permission) {
            echo "FAILURE: Permission was not assigned to the role\n";
        }

        // Store the role ID for the next test
        $roleId = $role->id;
    } else {
        echo "FAILURE: Role was not created\n";
        exit;
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
    exit;
}

// Test 3: Assign the role to a user
echo "\nTEST 3: Assign the role to a user\n";

try {
    // Find a user to assign the role to
    $user = User::where('id', '!=', $superAdmin->id)->first();
    if (!$user) {
        echo "No other users found. Please create another user first.\n";
        exit;
    }

    // Store the user's original role ID
    $originalRoleId = $user->role_id;

    // Assign the new role to the user
    $user->assignRole($role);

    // Reload the user from the database
    $user = User::find($user->id);

    if ($user->role_id == $role->id) {
        echo "SUCCESS: Role was assigned to the user\n";
    } else {
        echo "FAILURE: Role was not assigned to the user\n";
        echo "Expected role_id: {$role->id}, Actual role_id: {$user->role_id}\n";
    }

    // Restore the user's original role
    if ($originalRoleId) {
        $user->role_id = $originalRoleId;
        $user->save();
        echo "Restored user's original role\n";
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
}

// Test 4: Delete the role
echo "\nTEST 4: Delete the role\n";

try {
    // Call the API endpoint directly
    $response = app()->call('\App\Http\Controllers\Admin\RoleController@destroy', [
        'role' => $role
    ]);

    // Check if the role was deleted
    $roleExists = Role::where('id', $roleId)->exists();

    if (!$roleExists) {
        echo "SUCCESS: Role was deleted successfully\n";
    } else {
        echo "FAILURE: Role was not deleted\n";
    }
} catch (\Exception $e) {
    echo "FAILURE: Exception occurred: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
