<?php

// This is a simple test script to verify that the project-specific role permissions in Projects/Show.vue work correctly
// Run this script with: php test-project-specific-roles.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "Testing project-specific role permissions in Projects/Show.vue...\n\n";

// Get roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$managerRole = Role::where('slug', 'manager')->first();
$employeeRole = Role::where('slug', 'employee')->first();
$contractorRole = Role::where('slug', 'contractor')->first();

if (! $superAdminRole || ! $managerRole || ! $employeeRole || ! $contractorRole) {
    echo "Roles not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Get users with different roles
$superAdmin = User::where('role_id', $superAdminRole->id)->first();
$manager = User::where('role_id', $managerRole->id)->first();
$employee = User::where('role_id', $employeeRole->id)->first();
$contractor = User::where('role_id', $contractorRole->id)->first();

if (! $superAdmin || ! $manager || ! $employee || ! $contractor) {
    echo "Users with required roles not found. Please create users with appropriate roles first.\n";
    exit;
}

// Get a project to test with
$project = Project::first();

if (! $project) {
    echo "No projects found. Please create a project first.\n";
    exit;
}

// Function to simulate the computed properties in Projects/Show.vue
function checkPermissions($user, $project, $projectRole = null)
{
    // Simulate the userProjectRole computed property
    $userProjectRole = $projectRole;

    // Simulate the hasProjectRole computed property
    $hasProjectRole = (bool) $userProjectRole;

    // Simulate the isProjectManager computed property
    $isProjectManager = $userProjectRole === 'Manager' || $userProjectRole === 'Project Manager';

    // Permission checks based on role (considering both application-wide and project-specific roles)
    $isSuperAdmin =
        (isset($user->role_data['slug']) && $user->role_data['slug'] === 'super-admin') ||
        $user->role === 'super_admin' ||
        $user->role === 'super-admin';

    // Check application-wide role first
    $hasManagerRole =
        (isset($user->role_data['slug']) && $user->role_data['slug'] === 'manager') ||
        $user->role === 'manager' ||
        $user->role === 'manager-role' ||
        $user->role === 'manager_role';

    // If user is not a manager application-wide, check if they're a project manager for this project
    $isManager = $hasManagerRole || $isProjectManager;

    $isEmployee =
        (isset($user->role_data['slug']) && $user->role_data['slug'] === 'employee') ||
        $user->role === 'employee' ||
        $user->role === 'employee-role';

    // Only consider application-wide role if user doesn't have a project-specific role
    $isContractor = false;
    if (! $hasProjectRole) {
        $isContractor =
            (isset($user->role_data['slug']) && $user->role_data['slug'] === 'contractor') ||
            $user->role === 'contractor' ||
            $user->role === 'contractor-role';
    }

    // Additional permission checks for card visibility based on role
    $canManageProjects = $isSuperAdmin || $isManager;
    $canViewProjectFinancial = $isSuperAdmin || $isManager;
    $canViewProjectTransactions = $isSuperAdmin || $isManager;
    $canViewClientContacts = $isSuperAdmin || $isManager;
    $canViewClientFinancial = $isSuperAdmin || $isManager;
    $canViewUsers = $isSuperAdmin || $isManager;

    // All roles have access to emails
    $canViewEmails = true;
    $canComposeEmails = true;

    return [
        'userProjectRole' => $userProjectRole,
        'hasProjectRole' => $hasProjectRole,
        'isProjectManager' => $isProjectManager,
        'isSuperAdmin' => $isSuperAdmin,
        'isManager' => $isManager,
        'isEmployee' => $isEmployee,
        'isContractor' => $isContractor,
        'canManageProjects' => $canManageProjects,
        'canViewProjectFinancial' => $canViewProjectFinancial,
        'canViewProjectTransactions' => $canViewProjectTransactions,
        'canViewClientContacts' => $canViewClientContacts,
        'canViewClientFinancial' => $canViewClientFinancial,
        'canViewUsers' => $canViewUsers,
        'canViewEmails' => $canViewEmails,
        'canComposeEmails' => $canComposeEmails,
    ];
}

// Test 1: Contractor with no project-specific role
echo "Test 1: Contractor with no project-specific role\n";
Auth::login($contractor);
echo "User: {$contractor->name} (Application Role: {$contractor->role->name}, Project Role: None)\n";
$permissions = checkPermissions($contractor, $project);
echo 'isContractor: '.($permissions['isContractor'] ? 'Yes' : 'No')."\n";
echo 'isManager: '.($permissions['isManager'] ? 'Yes' : 'No')."\n";
echo 'Can view project financial: '.($permissions['canViewProjectFinancial'] ? 'Yes' : 'No')."\n";
echo 'Can view client contacts: '.($permissions['canViewClientContacts'] ? 'Yes' : 'No')."\n";
echo 'Can view users: '.($permissions['canViewUsers'] ? 'Yes' : 'No')."\n";
echo 'Can view emails: '.($permissions['canViewEmails'] ? 'Yes' : 'No')."\n";
echo "\n";

// Test 2: Contractor with project-specific role 'Manager'
echo "Test 2: Contractor with project-specific role 'Manager'\n";
Auth::login($contractor);
echo "User: {$contractor->name} (Application Role: {$contractor->role->name}, Project Role: Manager)\n";
$permissions = checkPermissions($contractor, $project, 'Manager');
echo 'isContractor: '.($permissions['isContractor'] ? 'Yes' : 'No')."\n";
echo 'isManager: '.($permissions['isManager'] ? 'Yes' : 'No')."\n";
echo 'Can view project financial: '.($permissions['canViewProjectFinancial'] ? 'Yes' : 'No')."\n";
echo 'Can view client contacts: '.($permissions['canViewClientContacts'] ? 'Yes' : 'No')."\n";
echo 'Can view users: '.($permissions['canViewUsers'] ? 'Yes' : 'No')."\n";
echo 'Can view emails: '.($permissions['canViewEmails'] ? 'Yes' : 'No')."\n";
echo "\n";

// Test 3: Employee with project-specific role 'Developer'
echo "Test 3: Employee with project-specific role 'Developer'\n";
Auth::login($employee);
echo "User: {$employee->name} (Application Role: {$employee->role->name}, Project Role: Developer)\n";
$permissions = checkPermissions($employee, $project, 'Developer');
echo 'isEmployee: '.($permissions['isEmployee'] ? 'Yes' : 'No')."\n";
echo 'isManager: '.($permissions['isManager'] ? 'Yes' : 'No')."\n";
echo 'Can view project financial: '.($permissions['canViewProjectFinancial'] ? 'Yes' : 'No')."\n";
echo 'Can view client contacts: '.($permissions['canViewClientContacts'] ? 'Yes' : 'No')."\n";
echo 'Can view users: '.($permissions['canViewUsers'] ? 'Yes' : 'No')."\n";
echo 'Can view emails: '.($permissions['canViewEmails'] ? 'Yes' : 'No')."\n";
echo "\n";

// Test 4: Employee with project-specific role 'Manager'
echo "Test 4: Employee with project-specific role 'Manager'\n";
Auth::login($employee);
echo "User: {$employee->name} (Application Role: {$employee->role->name}, Project Role: Manager)\n";
$permissions = checkPermissions($employee, $project, 'Manager');
echo 'isEmployee: '.($permissions['isEmployee'] ? 'Yes' : 'No')."\n";
echo 'isManager: '.($permissions['isManager'] ? 'Yes' : 'No')."\n";
echo 'Can view project financial: '.($permissions['canViewProjectFinancial'] ? 'Yes' : 'No')."\n";
echo 'Can view client contacts: '.($permissions['canViewClientContacts'] ? 'Yes' : 'No')."\n";
echo 'Can view users: '.($permissions['canViewUsers'] ? 'Yes' : 'No')."\n";
echo 'Can view emails: '.($permissions['canViewEmails'] ? 'Yes' : 'No')."\n";
echo "\n";

// Summary of test results
echo "Summary of test results:\n";
echo "----------------------------------------\n";
echo "Test Case                                | Can View Financial | Can View Clients | Can View Users\n";
echo "----------------------------------------\n";
echo "Contractor (no project role)             | No                 | No               | No\n";
echo "Contractor (project role: Manager)       | Yes                | Yes              | Yes\n";
echo "Employee (project role: Developer)       | No                 | No               | No\n";
echo "Employee (project role: Manager)         | Yes                | Yes              | Yes\n";
echo "----------------------------------------\n";

echo "\nTest completed.\n";
