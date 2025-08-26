<?php

// This script tests that permissions are being loaded correctly in the Inertia props
// Run this script to verify that the HandleInertiaRequests middleware is loading permissions correctly

echo "Testing permissions loading in Inertia props\n";
echo "-------------------------------------------\n\n";

// Import necessary classes
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Inertia\Middleware;

// Find or create a test permission
echo "Setting up test permission...\n";
$permission = Permission::firstOrCreate(
    ['slug' => 'compose_emails'],
    [
        'name' => 'Compose emails',
        'description' => 'Permission to compose emails',
        'category' => 'emails'
    ]
);
echo "- Permission 'compose_emails' " . ($permission->wasRecentlyCreated ? 'created' : 'already exists') . "\n\n";

// Find or create a test role with the permission
echo "Setting up test role...\n";
$role = Role::firstOrCreate(
    ['slug' => 'email_composer'],
    [
        'name' => 'Email Composer',
        'description' => 'Role with permission to compose emails',
        'type' => 'application'
    ]
);
echo "- Role 'email_composer' " . ($role->wasRecentlyCreated ? 'created' : 'already exists') . "\n";

// Assign the permission to the role
DB::table('role_permission')->updateOrInsert(
    ['role_id' => $role->id, 'permission_id' => $permission->id],
    ['created_at' => now(), 'updated_at' => now()]
);
echo "  - Assigned 'compose_emails' permission to 'email_composer' role\n\n";

// Find or create a test user with the role
echo "Setting up test user...\n";
$user = User::firstOrCreate(
    ['email' => 'test_permissions@example.com'],
    [
        'name' => 'Test Permissions User',
        'password' => bcrypt('password'),
        'role_id' => $role->id
    ]
);
if ($user->role_id != $role->id) {
    $user->role_id = $role->id;
    $user->save();
}
echo "- User 'test_permissions@example.com' " . ($user->wasRecentlyCreated ? 'created' : 'already exists') . " with 'email_composer' role\n\n";

// Log in as the test user
Auth::login($user);
echo "Logged in as {$user->name}\n\n";

// Create a mock request
$request = Request::create('/test', 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Create an instance of the HandleInertiaRequests middleware
$middleware = new HandleInertiaRequests();

// Get the shared data from the middleware
$sharedData = $middleware->share($request);

// Check if the auth.user data includes global_permissions
echo "Checking if auth.user data includes global_permissions...\n";
if (!isset($sharedData['auth']['user'])) {
    echo "ERROR: auth.user data not found in shared data.\n";
    exit(1);
}

$userData = $sharedData['auth']['user'];
if (!isset($userData->global_permissions)) {
    echo "ERROR: global_permissions not found in auth.user data.\n";
    exit(1);
}

echo "SUCCESS: global_permissions found in auth.user data.\n";
echo "Number of global permissions: " . count($userData->global_permissions) . "\n\n";

// Check if the compose_emails permission is included
echo "Checking if compose_emails permission is included...\n";
$hasComposeEmailsPermission = false;
foreach ($userData->global_permissions as $globalPermission) {
    if ($globalPermission['slug'] === 'compose_emails') {
        $hasComposeEmailsPermission = true;
        break;
    }
}

if (!$hasComposeEmailsPermission) {
    echo "ERROR: compose_emails permission not found in global_permissions.\n";
    exit(1);
}

echo "SUCCESS: compose_emails permission found in global_permissions.\n\n";

// Test the hasPermission function
echo "Testing hasPermission function...\n";
$hasPermission = function ($permissionSlug) use ($userData) {
    if (!$userData) return false;

    // Check global permissions from the database
    if ($userData->global_permissions) {
        return collect($userData->global_permissions)->some(function ($p) use ($permissionSlug) {
            return $p['slug'] === $permissionSlug;
        });
    }

    // If no permissions are found, return false
    return false;
};

$canComposeEmails = $hasPermission('compose_emails');
echo "Can compose emails: " . ($canComposeEmails ? "Yes" : "No") . "\n";

if (!$canComposeEmails) {
    echo "ERROR: hasPermission function returned false for compose_emails permission.\n";
    exit(1);
}

echo "SUCCESS: hasPermission function returned true for compose_emails permission.\n\n";

echo "All tests passed successfully!\n";
echo "The HandleInertiaRequests middleware is correctly loading permissions and the hasPermission function is working as expected.\n";
