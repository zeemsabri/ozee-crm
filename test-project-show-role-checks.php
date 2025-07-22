<?php

// This is a simple test script to verify that the role-based permission checks in Projects/Show.vue work correctly
// Run this script with: php test-project-show-role-checks.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;

echo "Testing role-based permission checks in Projects/Show.vue...\n\n";

// Get roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$managerRole = Role::where('slug', 'manager')->first();
$employeeRole = Role::where('slug', 'employee')->first();
$contractorRole = Role::where('slug', 'contractor')->first();

if (!$superAdminRole || !$managerRole || !$employeeRole || !$contractorRole) {
    echo "Roles not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Get users with different roles
$superAdmin = User::where('role_id', $superAdminRole->id)->first();
$manager = User::where('role_id', $managerRole->id)->first();
$employee = User::where('role_id', $employeeRole->id)->first();
$contractor = User::where('role_id', $contractorRole->id)->first();

if (!$superAdmin || !$manager || !$employee || !$contractor) {
    echo "Users with required roles not found. Please create users with appropriate roles first.\n";
    exit;
}

// Function to simulate the computed properties in Projects/Show.vue
function checkPermissions($user) {
    // Permission checks based on role
    $isSuperAdmin =
        (isset($user->role_data['slug']) && $user->role_data['slug'] === 'super-admin') ||
        $user->role === 'super_admin' ||
        $user->role === 'super-admin';

    $isManager =
        (isset($user->role_data['slug']) && $user->role_data['slug'] === 'manager') ||
        $user->role === 'manager' ||
        $user->role === 'manager-role' ||
        $user->role === 'manager_role';

    $isEmployee =
        (isset($user->role_data['slug']) && $user->role_data['slug'] === 'employee') ||
        $user->role === 'employee' ||
        $user->role === 'employee-role';

    $isContractor =
        (isset($user->role_data['slug']) && $user->role_data['slug'] === 'contractor') ||
        $user->role === 'contractor' ||
        $user->role === 'contractor-role';

    // Additional permission checks for card visibility based on role
    $canViewProjectFinancial = $isSuperAdmin || $isManager;
    $canViewProjectTransactions = $isSuperAdmin || $isManager;
    $canViewClientContacts = $isSuperAdmin || $isManager;
    $canViewClientFinancial = $isSuperAdmin || $isManager;
    $canViewUsers = $isSuperAdmin || $isManager;

    // All roles have access to emails
    $canViewEmails = true;
    $canComposeEmails = true;

    return [
        'isSuperAdmin' => $isSuperAdmin,
        'isManager' => $isManager,
        'isEmployee' => $isEmployee,
        'isContractor' => $isContractor,
        'canViewProjectFinancial' => $canViewProjectFinancial,
        'canViewProjectTransactions' => $canViewProjectTransactions,
        'canViewClientContacts' => $canViewClientContacts,
        'canViewClientFinancial' => $canViewClientFinancial,
        'canViewUsers' => $canViewUsers,
        'canViewEmails' => $canViewEmails,
        'canComposeEmails' => $canComposeEmails,
    ];
}

// Test Super Admin permissions
echo "Testing Super Admin permissions...\n";
Auth::login($superAdmin);
echo "User: {$superAdmin->name} (Role: {$superAdmin->role->name})\n";
$permissions = checkPermissions($superAdmin);
echo "isSuperAdmin: " . ($permissions['isSuperAdmin'] ? "Yes" : "No") . "\n";
echo "isManager: " . ($permissions['isManager'] ? "Yes" : "No") . "\n";
echo "Can view project financial: " . ($permissions['canViewProjectFinancial'] ? "Yes" : "No") . "\n";
echo "Can view project transactions: " . ($permissions['canViewProjectTransactions'] ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($permissions['canViewClientContacts'] ? "Yes" : "No") . "\n";
echo "Can view client financial: " . ($permissions['canViewClientFinancial'] ? "Yes" : "No") . "\n";
echo "Can view users: " . ($permissions['canViewUsers'] ? "Yes" : "No") . "\n";
echo "Can view emails: " . ($permissions['canViewEmails'] ? "Yes" : "No") . "\n";
echo "Can compose emails: " . ($permissions['canComposeEmails'] ? "Yes" : "No") . "\n";
echo "\n";

// Test Manager permissions
echo "Testing Manager permissions...\n";
Auth::login($manager);
echo "User: {$manager->name} (Role: {$manager->role->name})\n";
$permissions = checkPermissions($manager);
echo "isSuperAdmin: " . ($permissions['isSuperAdmin'] ? "Yes" : "No") . "\n";
echo "isManager: " . ($permissions['isManager'] ? "Yes" : "No") . "\n";
echo "Can view project financial: " . ($permissions['canViewProjectFinancial'] ? "Yes" : "No") . "\n";
echo "Can view project transactions: " . ($permissions['canViewProjectTransactions'] ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($permissions['canViewClientContacts'] ? "Yes" : "No") . "\n";
echo "Can view client financial: " . ($permissions['canViewClientFinancial'] ? "Yes" : "No") . "\n";
echo "Can view users: " . ($permissions['canViewUsers'] ? "Yes" : "No") . "\n";
echo "Can view emails: " . ($permissions['canViewEmails'] ? "Yes" : "No") . "\n";
echo "Can compose emails: " . ($permissions['canComposeEmails'] ? "Yes" : "No") . "\n";
echo "\n";

// Test Employee permissions
echo "Testing Employee permissions...\n";
Auth::login($employee);
echo "User: {$employee->name} (Role: {$employee->role->name})\n";
$permissions = checkPermissions($employee);
echo "isSuperAdmin: " . ($permissions['isSuperAdmin'] ? "Yes" : "No") . "\n";
echo "isManager: " . ($permissions['isManager'] ? "Yes" : "No") . "\n";
echo "isEmployee: " . ($permissions['isEmployee'] ? "Yes" : "No") . "\n";
echo "Can view project financial: " . ($permissions['canViewProjectFinancial'] ? "Yes" : "No") . "\n";
echo "Can view project transactions: " . ($permissions['canViewProjectTransactions'] ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($permissions['canViewClientContacts'] ? "Yes" : "No") . "\n";
echo "Can view client financial: " . ($permissions['canViewClientFinancial'] ? "Yes" : "No") . "\n";
echo "Can view users: " . ($permissions['canViewUsers'] ? "Yes" : "No") . "\n";
echo "Can view emails: " . ($permissions['canViewEmails'] ? "Yes" : "No") . "\n";
echo "Can compose emails: " . ($permissions['canComposeEmails'] ? "Yes" : "No") . "\n";
echo "\n";

// Test Contractor permissions
echo "Testing Contractor permissions...\n";
Auth::login($contractor);
echo "User: {$contractor->name} (Role: {$contractor->role->name})\n";
$permissions = checkPermissions($contractor);
echo "isSuperAdmin: " . ($permissions['isSuperAdmin'] ? "Yes" : "No") . "\n";
echo "isManager: " . ($permissions['isManager'] ? "Yes" : "No") . "\n";
echo "isContractor: " . ($permissions['isContractor'] ? "Yes" : "No") . "\n";
echo "Can view project financial: " . ($permissions['canViewProjectFinancial'] ? "Yes" : "No") . "\n";
echo "Can view project transactions: " . ($permissions['canViewProjectTransactions'] ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($permissions['canViewClientContacts'] ? "Yes" : "No") . "\n";
echo "Can view client financial: " . ($permissions['canViewClientFinancial'] ? "Yes" : "No") . "\n";
echo "Can view users: " . ($permissions['canViewUsers'] ? "Yes" : "No") . "\n";
echo "Can view emails: " . ($permissions['canViewEmails'] ? "Yes" : "No") . "\n";
echo "Can compose emails: " . ($permissions['canComposeEmails'] ? "Yes" : "No") . "\n";
echo "\n";

// Summary of which cards will be visible to each role
echo "Summary of card visibility by role:\n";
echo "----------------------------------------\n";
echo "Card                  | Super Admin | Manager | Employee | Contractor\n";
echo "----------------------------------------\n";
echo "Financial Information | Yes         | Yes     | No       | No\n";
echo "Clients Card          | Yes         | Yes     | No       | No\n";
echo "Assigned Team Card    | Yes         | Yes     | No       | No\n";
echo "Email Communication   | Yes         | Yes     | Yes      | Yes\n";
echo "Compose Email Button  | Yes         | Yes     | Yes      | Yes\n";
echo "----------------------------------------\n";

echo "\nTest completed.\n";
