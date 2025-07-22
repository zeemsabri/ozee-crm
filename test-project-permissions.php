<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;

echo "Testing Projects page permission checks...\n\n";

// Get roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$managerRole = Role::where('slug', 'manager')->first();
$employeeRole = Role::where('slug', 'employee')->first();
$contractorRole = Role::where('slug', 'contractor')->first();

if (!$superAdminRole || !$managerRole || !$employeeRole || !$contractorRole) {
    echo "ERROR: Required roles not found. Please ensure RolePermissionSeeder has been run.\n";
    exit(1);
}

// Get users with these roles
$superAdmin = User::where('role_id', $superAdminRole->id)->first();
$manager = User::where('role_id', $managerRole->id)->first();
$employee = User::where('role_id', $employeeRole->id)->first();
$contractor = User::where('role_id', $contractorRole->id)->first();

if (!$superAdmin || !$manager || !$employee || !$contractor) {
    echo "ERROR: Not all user roles found. Please ensure users with all roles exist.\n";
    exit(1);
}

echo "Found users:\n";
echo "- Super Admin: {$superAdmin->name} (ID: {$superAdmin->id})\n";
echo "- Manager: {$manager->name} (ID: {$manager->id})\n";
echo "- Employee: {$employee->name} (ID: {$employee->id})\n";
echo "- Contractor: {$contractor->name} (ID: {$contractor->id})\n\n";

// Function to simulate the permission checks in Projects/Index.vue
function checkProjectsAccess($user) {
    // Role checks
    $isSuperAdmin = $user->role_data['slug'] === 'super-admin' ||
                   $user->role === 'super_admin' ||
                   $user->role === 'super-admin';

    $isManager = $user->role_data['slug'] === 'manager' ||
                $user->role === 'manager' ||
                $user->role === 'manager-role' ||
                $user->role === 'manager_role';

    $isEmployee = $user->role_data['slug'] === 'employee' ||
                 $user->role === 'employee' ||
                 $user->role === 'employee-role';

    $isContractor = $user->role_data['slug'] === 'contractor' ||
                   $user->role === 'contractor' ||
                   $user->role === 'contractor-role';

    // Access check
    $hasAccessToProjects = $isSuperAdmin || $isManager || $isEmployee;

    return [
        'role' => $user->role_data['name'] ?? $user->role,
        'isSuperAdmin' => $isSuperAdmin,
        'isManager' => $isManager,
        'isEmployee' => $isEmployee,
        'isContractor' => $isContractor,
        'hasAccessToProjects' => $hasAccessToProjects,
        'shouldBeRedirected' => !$hasAccessToProjects
    ];
}

// Test each user role
echo "Testing Projects page access for each role...\n\n";

// Super Admin
$superAdminAccess = checkProjectsAccess($superAdmin);
echo "Super Admin ({$superAdmin->name}):\n";
echo "- Role: {$superAdminAccess['role']}\n";
echo "- Has access to Projects page: " . ($superAdminAccess['hasAccessToProjects'] ? "YES" : "NO") . "\n";
echo "- Should be redirected: " . ($superAdminAccess['shouldBeRedirected'] ? "YES" : "NO") . "\n\n";

// Manager
$managerAccess = checkProjectsAccess($manager);
echo "Manager ({$manager->name}):\n";
echo "- Role: {$managerAccess['role']}\n";
echo "- Has access to Projects page: " . ($managerAccess['hasAccessToProjects'] ? "YES" : "NO") . "\n";
echo "- Should be redirected: " . ($managerAccess['shouldBeRedirected'] ? "YES" : "NO") . "\n\n";

// Employee
$employeeAccess = checkProjectsAccess($employee);
echo "Employee ({$employee->name}):\n";
echo "- Role: {$employeeAccess['role']}\n";
echo "- Has access to Projects page: " . ($employeeAccess['hasAccessToProjects'] ? "YES" : "NO") . "\n";
echo "- Should be redirected: " . ($employeeAccess['shouldBeRedirected'] ? "YES" : "NO") . "\n\n";

// Contractor
$contractorAccess = checkProjectsAccess($contractor);
echo "Contractor ({$contractor->name}):\n";
echo "- Role: {$contractorAccess['role']}\n";
echo "- Has access to Projects page: " . ($contractorAccess['hasAccessToProjects'] ? "YES" : "NO") . "\n";
echo "- Should be redirected: " . ($contractorAccess['shouldBeRedirected'] ? "YES (FIXED)" : "NO (BUG)") . "\n\n";

// Summary
echo "Summary of permission checks:\n";
echo "- Super Admin: " . ($superAdminAccess['hasAccessToProjects'] ? "Has access ✓" : "No access ✗") . "\n";
echo "- Manager: " . ($managerAccess['hasAccessToProjects'] ? "Has access ✓" : "No access ✗") . "\n";
echo "- Employee: " . ($employeeAccess['hasAccessToProjects'] ? "Has access ✓" : "No access ✗") . "\n";
echo "- Contractor: " . (!$contractorAccess['hasAccessToProjects'] ? "No access (correctly redirected) ✓" : "Has access (incorrectly allowed) ✗") . "\n\n";

echo "Test completed.\n";
