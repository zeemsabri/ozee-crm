<?php

// This script tests the project-specific roles override functionality
// Run this script to verify that project-specific roles correctly override global permissions

// Mock a user with a global 'employee' role but a project-specific 'Manager' role
$mockUser = [
    'id' => 1,
    'name' => 'Test User',
    'email' => 'test@example.com',
    'role' => 'employee', // Global role is employee
    'role_data' => [
        'slug' => 'employee',
        'name' => 'Employee'
    ]
];

// Mock a project with the user assigned as a Manager
$mockProject = [
    'id' => 1,
    'name' => 'Test Project',
    'users' => [
        [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'pivot' => [
                'role' => 'Manager' // Project-specific role is Manager
            ]
        ]
    ]
];

echo "Testing project-specific role override functionality\n";
echo "---------------------------------------------------\n";
echo "User global role: {$mockUser['role']}\n";
echo "User project-specific role: {$mockProject['users'][0]['pivot']['role']}\n";
echo "\n";

// In the Vue component, this would be handled by the computed properties
// Here we're simulating the logic to verify it works correctly

// Check if user has a project-specific role
$userProjectRole = null;
foreach ($mockProject['users'] as $user) {
    if ($user['id'] === $mockUser['id']) {
        $userProjectRole = $user['pivot']['role'];
        break;
    }
}

$hasProjectRole = !is_null($userProjectRole);
$isProjectManager = $userProjectRole === 'Manager' || $userProjectRole === 'Project Manager';

// Global role checks
$isSuperAdmin = $mockUser['role'] === 'super_admin' || $mockUser['role'] === 'super-admin' ||
                ($mockUser['role_data'] && $mockUser['role_data']['slug'] === 'super-admin');
$hasManagerRole = $mockUser['role'] === 'manager' || $mockUser['role'] === 'manager-role' ||
                 ($mockUser['role_data'] && $mockUser['role_data']['slug'] === 'manager');
$isManager = $hasManagerRole || $isProjectManager;

// Permission checks with project-specific role override
$canViewProjectFinancial = $isProjectManager || $isSuperAdmin || $hasManagerRole;
$canViewClientContacts = $isProjectManager || $isSuperAdmin || $hasManagerRole;
$canViewUsers = $isProjectManager || $isSuperAdmin || $hasManagerRole;

echo "Permission check results:\n";
echo "------------------------\n";
echo "Has project-specific role: " . ($hasProjectRole ? "Yes" : "No") . "\n";
echo "Is project manager: " . ($isProjectManager ? "Yes" : "No") . "\n";
echo "Is super admin (global): " . ($isSuperAdmin ? "Yes" : "No") . "\n";
echo "Has manager role (global): " . ($hasManagerRole ? "Yes" : "No") . "\n";
echo "Is manager (combined): " . ($isManager ? "Yes" : "No") . "\n";
echo "\n";
echo "Can view project financial: " . ($canViewProjectFinancial ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($canViewClientContacts ? "Yes" : "No") . "\n";
echo "Can view users: " . ($canViewUsers ? "Yes" : "No") . "\n";
echo "\n";

// Expected results:
// - User has employee global role, so normally wouldn't have these permissions
// - But user has Manager project-specific role, so should have these permissions
echo "Expected results:\n";
echo "----------------\n";
echo "User should have financial, client, and user viewing permissions: " .
     (($canViewProjectFinancial && $canViewClientContacts && $canViewUsers) ? "PASS" : "FAIL") . "\n";

// Now test the reverse case - user with manager global role but non-manager project role
echo "\n\nTesting with manager global role but non-manager project role\n";
echo "--------------------------------------------------------\n";

// Update mock data
$mockUser['role'] = 'manager';
$mockUser['role_data']['slug'] = 'manager';
$mockProject['users'][0]['pivot']['role'] = 'Developer'; // Non-manager project role

echo "User global role: {$mockUser['role']}\n";
echo "User project-specific role: {$mockProject['users'][0]['pivot']['role']}\n";
echo "\n";

// Recalculate
$userProjectRole = $mockProject['users'][0]['pivot']['role'];
$hasProjectRole = !is_null($userProjectRole);
$isProjectManager = $userProjectRole === 'Manager' || $userProjectRole === 'Project Manager';

$isSuperAdmin = $mockUser['role'] === 'super_admin' || $mockUser['role'] === 'super-admin' ||
                ($mockUser['role_data'] && $mockUser['role_data']['slug'] === 'super-admin');
$hasManagerRole = $mockUser['role'] === 'manager' || $mockUser['role'] === 'manager-role' ||
                 ($mockUser['role_data'] && $mockUser['role_data']['slug'] === 'manager');
$isManager = $hasManagerRole || $isProjectManager;

$canViewProjectFinancial = $isProjectManager || $isSuperAdmin || $hasManagerRole;
$canViewClientContacts = $isProjectManager || $isSuperAdmin || $hasManagerRole;
$canViewUsers = $isProjectManager || $isSuperAdmin || $hasManagerRole;

echo "Permission check results:\n";
echo "------------------------\n";
echo "Has project-specific role: " . ($hasProjectRole ? "Yes" : "No") . "\n";
echo "Is project manager: " . ($isProjectManager ? "Yes" : "No") . "\n";
echo "Is super admin (global): " . ($isSuperAdmin ? "Yes" : "No") . "\n";
echo "Has manager role (global): " . ($hasManagerRole ? "Yes" : "No") . "\n";
echo "Is manager (combined): " . ($isManager ? "Yes" : "No") . "\n";
echo "\n";
echo "Can view project financial: " . ($canViewProjectFinancial ? "Yes" : "No") . "\n";
echo "Can view client contacts: " . ($canViewClientContacts ? "Yes" : "No") . "\n";
echo "Can view users: " . ($canViewUsers ? "Yes" : "No") . "\n";
echo "\n";

// In this case, the user should still have permissions due to global manager role
echo "Expected results:\n";
echo "----------------\n";
echo "User should still have permissions due to global manager role: " .
     (($canViewProjectFinancial && $canViewClientContacts && $canViewUsers) ? "PASS" : "FAIL") . "\n";
