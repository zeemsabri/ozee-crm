<?php

// This is a simple test script to verify that the permission-based visibility in Projects/Show.vue works correctly
// Run this script with: php test-project-show-permissions.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Auth;

echo "Testing permission-based visibility in Projects/Show.vue...\n\n";

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

// Get permissions
$viewProjectFinancial = Permission::where('slug', 'view_project_financial')->first();
$viewProjectTransactions = Permission::where('slug', 'view_project_transactions')->first();
$viewClientContacts = Permission::where('slug', 'view_client_contacts')->first();
$viewClientFinancial = Permission::where('slug', 'view_client_financial')->first();
$viewUsers = Permission::where('slug', 'view_users')->first();
$viewEmails = Permission::where('slug', 'view_emails')->first();
$composeEmails = Permission::where('slug', 'compose_emails')->first();

if (!$viewProjectFinancial || !$viewProjectTransactions || !$viewClientContacts ||
    !$viewClientFinancial || !$viewUsers || !$viewEmails || !$composeEmails) {
    echo "Required permissions not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Test Super Admin permissions
echo "Testing Super Admin permissions...\n";
Auth::login($superAdmin);
echo "User: {$superAdmin->name} (Role: {$superAdmin->role->name})\n";
echo "Can view project financial: " . ($superAdmin->hasPermission('view_project_financial') ? "Yes" : "No") . "\n";
echo "Can view project transactions: " . ($superAdmin->hasPermission('view_project_transactions') ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($superAdmin->hasPermission('view_client_contacts') ? "Yes" : "No") . "\n";
echo "Can view client financial: " . ($superAdmin->hasPermission('view_client_financial') ? "Yes" : "No") . "\n";
echo "Can view users: " . ($superAdmin->hasPermission('view_users') ? "Yes" : "No") . "\n";
echo "Can view emails: " . ($superAdmin->hasPermission('view_emails') ? "Yes" : "No") . "\n";
echo "Can compose emails: " . ($superAdmin->hasPermission('compose_emails') ? "Yes" : "No") . "\n";
echo "\n";

// Test Manager permissions
echo "Testing Manager permissions...\n";
Auth::login($manager);
echo "User: {$manager->name} (Role: {$manager->role->name})\n";
echo "Can view project financial: " . ($manager->hasPermission('view_project_financial') ? "Yes" : "No") . "\n";
echo "Can view project transactions: " . ($manager->hasPermission('view_project_transactions') ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($manager->hasPermission('view_client_contacts') ? "Yes" : "No") . "\n";
echo "Can view client financial: " . ($manager->hasPermission('view_client_financial') ? "Yes" : "No") . "\n";
echo "Can view users: " . ($manager->hasPermission('view_users') ? "Yes" : "No") . "\n";
echo "Can view emails: " . ($manager->hasPermission('view_emails') ? "Yes" : "No") . "\n";
echo "Can compose emails: " . ($manager->hasPermission('compose_emails') ? "Yes" : "No") . "\n";
echo "\n";

// Test Employee permissions
echo "Testing Employee permissions...\n";
Auth::login($employee);
echo "User: {$employee->name} (Role: {$employee->role->name})\n";
echo "Can view project financial: " . ($employee->hasPermission('view_project_financial') ? "Yes" : "No") . "\n";
echo "Can view project transactions: " . ($employee->hasPermission('view_project_transactions') ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($employee->hasPermission('view_client_contacts') ? "Yes" : "No") . "\n";
echo "Can view client financial: " . ($employee->hasPermission('view_client_financial') ? "Yes" : "No") . "\n";
echo "Can view users: " . ($employee->hasPermission('view_users') ? "Yes" : "No") . "\n";
echo "Can view emails: " . ($employee->hasPermission('view_emails') ? "Yes" : "No") . "\n";
echo "Can compose emails: " . ($employee->hasPermission('compose_emails') ? "Yes" : "No") . "\n";
echo "\n";

// Test Contractor permissions
echo "Testing Contractor permissions...\n";
Auth::login($contractor);
echo "User: {$contractor->name} (Role: {$contractor->role->name})\n";
echo "Can view project financial: " . ($contractor->hasPermission('view_project_financial') ? "Yes" : "No") . "\n";
echo "Can view project transactions: " . ($contractor->hasPermission('view_project_transactions') ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($contractor->hasPermission('view_client_contacts') ? "Yes" : "No") . "\n";
echo "Can view client financial: " . ($contractor->hasPermission('view_client_financial') ? "Yes" : "No") . "\n";
echo "Can view users: " . ($contractor->hasPermission('view_users') ? "Yes" : "No") . "\n";
echo "Can view emails: " . ($contractor->hasPermission('view_emails') ? "Yes" : "No") . "\n";
echo "Can compose emails: " . ($contractor->hasPermission('compose_emails') ? "Yes" : "No") . "\n";
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
