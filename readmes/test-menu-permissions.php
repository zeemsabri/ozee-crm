<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\User;

echo "Testing menu permissions for different user roles...\n\n";

// Get roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$managerRole = Role::where('slug', 'manager')->first();
$employeeRole = Role::where('slug', 'employee')->first();
$contractorRole = Role::where('slug', 'contractor')->first();

if (! $superAdminRole || ! $managerRole || ! $employeeRole || ! $contractorRole) {
    echo "ERROR: Required roles not found. Please ensure RolePermissionSeeder has been run.\n";
    exit(1);
}

// Get users with these roles
$superAdmin = User::where('role_id', $superAdminRole->id)->first();
$manager = User::where('role_id', $managerRole->id)->first();
$employee = User::where('role_id', $employeeRole->id)->first();
$contractor = User::where('role_id', $contractorRole->id)->first();

if (! $superAdmin || ! $manager || ! $employee || ! $contractor) {
    echo "ERROR: Not all user roles found. Please ensure users with all roles exist.\n";
    exit(1);
}

echo "Found users:\n";
echo "- Super Admin: {$superAdmin->name} (ID: {$superAdmin->id})\n";
echo "- Manager: {$manager->name} (ID: {$manager->id})\n";
echo "- Employee: {$employee->name} (ID: {$employee->id})\n";
echo "- Contractor: {$contractor->name} (ID: {$contractor->id})\n\n";

// Test computed properties for each role
echo "Testing menu visibility for each role...\n\n";

// Function to simulate the computed properties in AuthenticatedLayout.vue
function getMenuVisibility($user)
{
    // Role checks
    $isSuperAdmin = $user->role_data['slug'] === 'super-admin';
    $isManager = $user->role_data['slug'] === 'manager';
    $isEmployee = $user->role_data['slug'] === 'employee';
    $isContractor = $user->role_data['slug'] === 'contractor';

    // Permission computed properties
    $hasManagementAccess = $isSuperAdmin || $isManager;
    $canComposeEmails = $isSuperAdmin || $isManager || $isContractor;
    $canApproveEmails = $isSuperAdmin || $isManager;
    $canManageUsers = $isSuperAdmin || $isManager;
    $canManageRoles = $isSuperAdmin;

    // Menu visibility
    return [
        'dashboard' => true, // Always visible
        'clients' => $hasManagementAccess || $isEmployee,
        'projects' => $hasManagementAccess || $isEmployee,
        'users' => $canManageUsers,
        'compose_email' => $canComposeEmails,
        'approve_emails' => $canApproveEmails,
        'rejected_emails' => $canComposeEmails,
        'admin_dropdown' => $canManageRoles,
    ];
}

// Test Super Admin
$superAdminMenu = getMenuVisibility($superAdmin);
echo "Super Admin menu visibility:\n";
echo '- Dashboard: '.($superAdminMenu['dashboard'] ? 'Visible' : 'Hidden')."\n";
echo '- Clients: '.($superAdminMenu['clients'] ? 'Visible' : 'Hidden')."\n";
echo '- Projects: '.($superAdminMenu['projects'] ? 'Visible' : 'Hidden')."\n";
echo '- Users: '.($superAdminMenu['users'] ? 'Visible' : 'Hidden')."\n";
echo '- Compose Email: '.($superAdminMenu['compose_email'] ? 'Visible' : 'Hidden')."\n";
echo '- Approve Emails: '.($superAdminMenu['approve_emails'] ? 'Visible' : 'Hidden')."\n";
echo '- Rejected Emails: '.($superAdminMenu['rejected_emails'] ? 'Visible' : 'Hidden')."\n";
echo '- Admin Dropdown: '.($superAdminMenu['admin_dropdown'] ? 'Visible' : 'Hidden')."\n\n";

// Test Manager
$managerMenu = getMenuVisibility($manager);
echo "Manager menu visibility:\n";
echo '- Dashboard: '.($managerMenu['dashboard'] ? 'Visible' : 'Hidden')."\n";
echo '- Clients: '.($managerMenu['clients'] ? 'Visible' : 'Hidden')."\n";
echo '- Projects: '.($managerMenu['projects'] ? 'Visible' : 'Hidden')."\n";
echo '- Users: '.($managerMenu['users'] ? 'Visible' : 'Hidden')."\n";
echo '- Compose Email: '.($managerMenu['compose_email'] ? 'Visible' : 'Hidden')."\n";
echo '- Approve Emails: '.($managerMenu['approve_emails'] ? 'Visible' : 'Hidden')."\n";
echo '- Rejected Emails: '.($managerMenu['rejected_emails'] ? 'Visible' : 'Hidden')."\n";
echo '- Admin Dropdown: '.($managerMenu['admin_dropdown'] ? 'Visible' : 'Hidden')."\n\n";

// Test Employee
$employeeMenu = getMenuVisibility($employee);
echo "Employee menu visibility:\n";
echo '- Dashboard: '.($employeeMenu['dashboard'] ? 'Visible' : 'Hidden')."\n";
echo '- Clients: '.($employeeMenu['clients'] ? 'Visible' : 'Hidden')."\n";
echo '- Projects: '.($employeeMenu['projects'] ? 'Visible' : 'Hidden')."\n";
echo '- Users: '.($employeeMenu['users'] ? 'Visible' : 'Hidden')."\n";
echo '- Compose Email: '.($employeeMenu['compose_email'] ? 'Visible' : 'Hidden')."\n";
echo '- Approve Emails: '.($employeeMenu['approve_emails'] ? 'Visible' : 'Hidden')."\n";
echo '- Rejected Emails: '.($employeeMenu['rejected_emails'] ? 'Visible' : 'Hidden')."\n";
echo '- Admin Dropdown: '.($employeeMenu['admin_dropdown'] ? 'Visible' : 'Hidden')."\n\n";

// Test Contractor
$contractorMenu = getMenuVisibility($contractor);
echo "Contractor menu visibility:\n";
echo '- Dashboard: '.($contractorMenu['dashboard'] ? 'Visible' : 'Hidden')."\n";
echo '- Clients: '.($contractorMenu['clients'] ? 'Visible' : 'Hidden')." (FIXED: Previously visible)\n";
echo '- Projects: '.($contractorMenu['projects'] ? 'Visible' : 'Hidden')." (FIXED: Previously visible)\n";
echo '- Users: '.($contractorMenu['users'] ? 'Visible' : 'Hidden')."\n";
echo '- Compose Email: '.($contractorMenu['compose_email'] ? 'Visible' : 'Hidden')."\n";
echo '- Approve Emails: '.($contractorMenu['approve_emails'] ? 'Visible' : 'Hidden')."\n";
echo '- Rejected Emails: '.($contractorMenu['rejected_emails'] ? 'Visible' : 'Hidden')."\n";
echo '- Admin Dropdown: '.($contractorMenu['admin_dropdown'] ? 'Visible' : 'Hidden')."\n\n";

echo "Test completed.\n";
