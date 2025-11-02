<?php

// This script tests the access control for the Email Composer page
// It verifies that the page is accessible but shows a permission denied message for unauthorized users

echo "Testing Email Composer access control\n";
echo "-----------------------------------\n\n";

// Import necessary classes
require_once __DIR__.'/vendor/autoload.php';

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Find or create a test permission for composing emails
echo "Setting up test permission...\n";
$permission = Permission::firstOrCreate(
    ['slug' => 'compose_emails'],
    [
        'name' => 'Compose emails',
        'description' => 'Permission to compose emails',
        'category' => 'emails',
    ]
);
echo "- Permission 'compose_emails' ".($permission->wasRecentlyCreated ? 'created' : 'already exists')."\n\n";

// Find or create test roles with and without the permission
echo "Setting up test roles...\n";

// Role with the permission
$roleWithPermission = Role::firstOrCreate(
    ['slug' => 'email_composer'],
    [
        'name' => 'Email Composer',
        'description' => 'Role with permission to compose emails',
        'type' => 'application',
    ]
);
echo "- Role 'email_composer' ".($roleWithPermission->wasRecentlyCreated ? 'created' : 'already exists')."\n";

// Assign the permission to the role
DB::table('role_permission')->updateOrInsert(
    ['role_id' => $roleWithPermission->id, 'permission_id' => $permission->id],
    ['created_at' => now(), 'updated_at' => now()]
);
echo "  - Assigned 'compose_emails' permission to 'email_composer' role\n";

// Role without the permission
$roleWithoutPermission = Role::firstOrCreate(
    ['slug' => 'no_email_access'],
    [
        'name' => 'No Email Access',
        'description' => 'Role without permission to compose emails',
        'type' => 'application',
    ]
);
echo "- Role 'no_email_access' ".($roleWithoutPermission->wasRecentlyCreated ? 'created' : 'already exists')."\n";

// Make sure the role doesn't have the permission
DB::table('role_permission')
    ->where('role_id', $roleWithoutPermission->id)
    ->where('permission_id', $permission->id)
    ->delete();
echo "  - Removed 'compose_emails' permission from 'no_email_access' role\n\n";

// Find or create test users with different roles
echo "Setting up test users...\n";

// User with permission
$userWithPermission = User::firstOrCreate(
    ['email' => 'email_composer@example.com'],
    [
        'name' => 'Email Composer User',
        'password' => bcrypt('password'),
        'role_id' => $roleWithPermission->id,
    ]
);
if ($userWithPermission->role_id != $roleWithPermission->id) {
    $userWithPermission->role_id = $roleWithPermission->id;
    $userWithPermission->save();
}
echo "- User 'email_composer@example.com' ".($userWithPermission->wasRecentlyCreated ? 'created' : 'already exists')." with 'email_composer' role\n";

// User without permission
$userWithoutPermission = User::firstOrCreate(
    ['email' => 'no_email_access@example.com'],
    [
        'name' => 'No Email Access User',
        'password' => bcrypt('password'),
        'role_id' => $roleWithoutPermission->id,
    ]
);
if ($userWithoutPermission->role_id != $roleWithoutPermission->id) {
    $userWithoutPermission->role_id = $roleWithoutPermission->id;
    $userWithoutPermission->save();
}
echo "- User 'no_email_access@example.com' ".($userWithoutPermission->wasRecentlyCreated ? 'created' : 'already exists')." with 'no_email_access' role\n\n";

echo "Test setup completed.\n\n";
echo "To test the Email Composer access control:\n";
echo "1. Log in as 'email_composer@example.com' (password: 'password')\n";
echo "   - You should be able to access the Email Composer page and use the form\n";
echo "2. Log in as 'no_email_access@example.com' (password: 'password')\n";
echo "   - You should be able to access the Email Composer page but see a permission denied message\n";
echo "\nNote: This script only sets up the test data. You need to manually test the access control by logging in as the test users.\n";
