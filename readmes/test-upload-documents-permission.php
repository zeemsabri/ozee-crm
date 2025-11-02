<?php

// This script tests the upload_project_documents permission
// Run this script with: php test-upload-documents-permission.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "Testing upload_project_documents permission...\n\n";

// Check if the permission exists
$permission = Permission::where('slug', 'upload_project_documents')->first();
if (! $permission) {
    echo "Error: upload_project_documents permission does not exist in the database.\n";
    echo "Please run the RolePermissionSeeder first.\n";
    exit(1);
}

echo "Found upload_project_documents permission (ID: {$permission->id}).\n";

// Get roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$managerRole = Role::where('slug', 'manager')->first();
$employeeRole = Role::where('slug', 'employee')->first();
$contractorRole = Role::where('slug', 'contractor')->first();

if (! $superAdminRole || ! $managerRole || ! $employeeRole || ! $contractorRole) {
    echo "Error: One or more roles not found. Please run the RolePermissionSeeder first.\n";
    exit(1);
}

// Check which roles have the permission
$superAdminHasPermission = $superAdminRole->permissions()->where('permissions.id', $permission->id)->exists();
$managerHasPermission = $managerRole->permissions()->where('permissions.id', $permission->id)->exists();
$employeeHasPermission = $employeeRole->permissions()->where('permissions.id', $permission->id)->exists();
$contractorHasPermission = $contractorRole->permissions()->where('permissions.id', $permission->id)->exists();

echo "Role permissions check:\n";
echo '- Super Admin has upload_project_documents permission: '.($superAdminHasPermission ? 'Yes' : 'No')."\n";
echo '- Manager has upload_project_documents permission: '.($managerHasPermission ? 'Yes' : 'No')."\n";
echo '- Employee has upload_project_documents permission: '.($employeeHasPermission ? 'Yes' : 'No')."\n";
echo '- Contractor has upload_project_documents permission: '.($contractorHasPermission ? 'Yes' : 'No')."\n\n";

// Get users with different roles
$superAdmin = User::where('role_id', $superAdminRole->id)->first();
$manager = User::where('role_id', $managerRole->id)->first();
$employee = User::where('role_id', $employeeRole->id)->first();
$contractor = User::where('role_id', $contractorRole->id)->first();

if (! $superAdmin || ! $manager || ! $employee || ! $contractor) {
    echo "Warning: Not all user roles are represented in the database.\n";
    echo "Some tests may be skipped.\n\n";
}

// Test user permissions
echo "User permissions check:\n";

if ($superAdmin) {
    Auth::login($superAdmin);
    $hasPermission = $superAdmin->hasPermission('upload_project_documents');
    echo "- Super Admin user ({$superAdmin->name}) has upload_project_documents permission: ".($hasPermission ? 'Yes' : 'No')."\n";
}

if ($manager) {
    Auth::login($manager);
    $hasPermission = $manager->hasPermission('upload_project_documents');
    echo "- Manager user ({$manager->name}) has upload_project_documents permission: ".($hasPermission ? 'Yes' : 'No')."\n";
}

if ($employee) {
    Auth::login($employee);
    $hasPermission = $employee->hasPermission('upload_project_documents');
    echo "- Employee user ({$employee->name}) has upload_project_documents permission: ".($hasPermission ? 'Yes' : 'No')."\n";
}

if ($contractor) {
    Auth::login($contractor);
    $hasPermission = $contractor->hasPermission('upload_project_documents');
    echo "- Contractor user ({$contractor->name}) has upload_project_documents permission: ".($hasPermission ? 'Yes' : 'No')."\n";
}

echo "\nTest completed.\n";

// Summary
echo "\nSummary:\n";
echo "The upload_project_documents permission has been added to the database and assigned to the appropriate roles.\n";
echo "This permission is used in ProjectForm.vue to control access to the document upload functionality.\n";
echo "Users with the Manager role should be able to upload documents to projects.\n";
echo "Users with the Employee or Contractor roles should not be able to upload documents to projects.\n";
