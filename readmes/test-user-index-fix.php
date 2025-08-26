<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;

echo "Testing User Index Vue fix...\n";

// Get a user
$user = User::first();
if (!$user) {
    echo "No users found in the database\n";
    exit;
}

echo "User found: {$user->name}\n";

// Test role attributes
echo "Testing role attributes...\n";

// Check if role is accessible
echo "role attribute: " . (isset($user->role) ? "exists" : "does not exist") . "\n";
if (isset($user->role)) {
    echo "role value: " . (is_string($user->role) ? $user->role : "not a string") . "\n";
}

// Check role_data attribute
echo "role_data attribute: " . (isset($user->role_data) ? "exists" : "does not exist") . "\n";
if (isset($user->role_data)) {
    echo "role_data is " . (is_array($user->role_data) ? "an array" : "not an array") . "\n";
    if (is_array($user->role_data)) {
        echo "role_data values: id={$user->role_data['id']}, name={$user->role_data['name']}, slug={$user->role_data['slug']}\n";
    }
}

// Check appRole attribute
echo "appRole attribute: " . (isset($user->appRole) ? "exists" : "does not exist") . "\n";
if (isset($user->appRole)) {
    echo "appRole value: " . $user->appRole . "\n";
}

// Test role relationship
echo "Testing role relationship...\n";
$role = $user->role;
if ($role) {
    echo "Role relationship works: {$role->name} (ID: {$role->id})\n";
} else {
    echo "Role relationship returned null\n";
}

// Simulate the behavior in Users/Index.vue
echo "\nSimulating Users/Index.vue behavior...\n";

// Test the fix for userToEdit.role.replace
echo "Testing fix for userToEdit.role.replace...\n";
$roleSlug = isset($user->role) && is_string($user->role)
    ? str_replace('-', '_', $user->role)
    : 'employee';
echo "This would cause an error in the original code if role is not a string\n";
echo "With our fix, we default to 'employee' if role is not a string\n";

// Test the fix for userItem.role.replace in the table
echo "Testing fix for userItem.role.replace in the table...\n";
$displayRole = isset($user->role_data['name'])
    ? $user->role_data['name']
    : (isset($user->role) && is_string($user->role)
        ? str_replace(['_', '-'], ' ', $user->role)
        : 'Employee');
echo "Display role: {$displayRole}\n";

// Test the fix for userItem.role.replace in the Edit button
echo "Testing fix for userItem.role.replace in the Edit button...\n";
$isEmployee = isset($user->role) && is_string($user->role) &&
    (str_replace('-', '_', $user->role) === 'employee');
$isContractor = isset($user->role) && is_string($user->role) &&
    (str_replace('-', '_', $user->role) === 'contractor');
echo "Is employee (from role string): " . ($isEmployee ? "true" : "false") . "\n";
echo "Is contractor (from role string): " . ($isContractor ? "true" : "false") . "\n";

// Test the fix for userToDelete.role in the delete confirmation
echo "Testing fix for userToDelete.role in the delete confirmation...\n";
$deleteRoleDisplay = isset($user->role_data['name'])
    ? $user->role_data['name']
    : (isset($user->role) && is_string($user->role)
        ? $user->role
        : 'Employee');
echo "Delete role display: {$deleteRoleDisplay}\n";

echo "\nTest completed.\n";
