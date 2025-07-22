<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;

echo "Testing Client Permissions with New Role System...\n\n";

// Get roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$managerRole = Role::where('slug', 'manager')->first();

if (!$superAdminRole || !$managerRole) {
    echo "ERROR: Required roles not found. Please ensure RolePermissionSeeder has been run.\n";
    exit(1);
}

// Get users with these roles
$superAdmin = User::where('role_id', $superAdminRole->id)->first();
$manager = User::where('role_id', $managerRole->id)->first();

if (!$superAdmin) {
    echo "ERROR: No Super Admin user found\n";
    exit(1);
}

if (!$manager) {
    echo "ERROR: No Manager user found\n";
    exit(1);
}

echo "Found Super Admin user: {$superAdmin->name} (ID: {$superAdmin->id})\n";
echo "Found Manager user: {$manager->name} (ID: {$manager->id})\n\n";

// Test role_data attribute for Super Admin
echo "Testing Super Admin role_data attribute...\n";
$roleData = $superAdmin->role_data;
if (is_array($roleData)) {
    echo "role_data is an array with keys: " . implode(", ", array_keys($roleData)) . "\n";
    echo "role_data values: id={$roleData['id']}, name={$roleData['name']}, slug={$roleData['slug']}\n";
} else {
    echo "ERROR: role_data is not an array or is null\n";
}

// Test appRole attribute for Super Admin
echo "Testing Super Admin appRole attribute...\n";
$appRole = $superAdmin->appRole;
echo "appRole value: " . $appRole . "\n\n";

// Simulate the permission checks from Clients/Index.vue
echo "Simulating permission checks from Clients/Index.vue...\n";

// Old permission check (before our fix)
$oldIsSuperAdmin = $superAdmin->role === 'super_admin';
echo "Old isSuperAdmin check (using role string): " . ($oldIsSuperAdmin ? "true" : "false") . "\n";

// New permission check (after our fix)
$newIsSuperAdmin = $superAdmin->role_data['slug'] === 'super-admin' ||
                  $superAdmin->role === 'super_admin' ||
                  $superAdmin->role === 'super-admin';
echo "New isSuperAdmin check (using role_data): " . ($newIsSuperAdmin ? "true" : "false") . "\n\n";

// Test Manager permissions
echo "Testing Manager permissions...\n";

// Old permission check
$oldIsManager = $manager->role === 'manager';
echo "Old isManager check (using role string): " . ($oldIsManager ? "true" : "false") . "\n";

// New permission check
$newIsManager = $manager->role_data['slug'] === 'manager' ||
               $manager->role === 'manager' ||
               $manager->role === 'manager-role' ||
               $manager->role === 'manager_role';
echo "New isManager check (using role_data): " . ($newIsManager ? "true" : "false") . "\n\n";

// Test canManageClients computed property
$canManageClients = $newIsSuperAdmin || $newIsManager;
echo "canManageClients computed property: " . ($canManageClients ? "true" : "false") . "\n";
echo "This means buttons should be visible: " . ($canManageClients ? "YES" : "NO") . "\n\n";

echo "Test completed.\n";
