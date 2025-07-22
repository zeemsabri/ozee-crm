<?php

// This script tests the RolePermissionSeeder to verify that roles are created with the correct types
// Run this script with: php test-role-types.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use Illuminate\Support\Facades\DB;

echo "Testing RolePermissionSeeder role types...\n\n";

// Check if the roles table has the 'type' column
$hasTypeColumn = DB::getSchemaBuilder()->hasColumn('roles', 'type');
if (!$hasTypeColumn) {
    echo "Error: The 'type' column does not exist in the roles table.\n";
    echo "Please run the migration to add the 'type' column first.\n";
    exit(1);
}

// Get all roles grouped by type
$applicationRoles = Role::where('type', 'application')->get();
$clientRoles = Role::where('type', 'client')->get();
$projectRoles = Role::where('type', 'project')->get();
$nullTypeRoles = Role::whereNull('type')->get();

echo "Roles by type:\n";
echo "-------------\n";

echo "\nApplication Roles (" . count($applicationRoles) . "):\n";
foreach ($applicationRoles as $role) {
    echo "- {$role->name} (slug: {$role->slug})\n";
}

echo "\nClient Roles (" . count($clientRoles) . "):\n";
foreach ($clientRoles as $role) {
    echo "- {$role->name} (slug: {$role->slug})\n";
}

echo "\nProject Roles (" . count($projectRoles) . "):\n";
foreach ($projectRoles as $role) {
    echo "- {$role->name} (slug: {$role->slug})\n";
}

if (count($nullTypeRoles) > 0) {
    echo "\nRoles with NULL type (" . count($nullTypeRoles) . "):\n";
    foreach ($nullTypeRoles as $role) {
        echo "- {$role->name} (slug: {$role->slug})\n";
    }
    echo "\nWarning: There are roles with NULL type. These should be updated to have a type.\n";
}

echo "\nExpected roles after running the updated RolePermissionSeeder:\n";
echo "--------------------------------------------------------\n";
echo "Application Roles: Super Admin, Manager, Employee, Contractor\n";
echo "Client Roles: Client Admin, Client User, Client Viewer\n";
echo "Project Roles: Project Manager, Project Member, Project Viewer\n";

echo "\nTo run the updated seeder, use the following command:\n";
echo "php artisan db:seed --class=RolePermissionSeeder\n";

echo "\nNote: Running the seeder will truncate the permissions, roles, and role_permission tables.\n";
echo "Make sure you have a backup of your data before running the seeder in production.\n";

echo "\nTest completed.\n";
