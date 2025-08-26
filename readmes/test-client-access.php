<?php

// This is a simple test script to verify that the client access changes work correctly
// Run this script with: php test-client-access.php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;

echo "Testing client access for different user roles...\n\n";

// Get roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$managerRole = Role::where('slug', 'manager')->first();
$employeeRole = Role::where('slug', 'employee')->first();
$contractorRole = Role::where('slug', 'contractor')->first();

// Find users with different roles using the role_id column
$superAdmin = User::where('role_id', $superAdminRole->id)->first();
$manager = User::where('role_id', $managerRole->id)->first();
$employee = User::where('role_id', $employeeRole->id)->first();
$contractor = User::where('role_id', $contractorRole->id)->first();

// If we don't have users with these roles, create them
if (!$superAdmin) {
    echo "Super Admin not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

if (!$manager) {
    echo "Manager not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

if (!$employee) {
    echo "Employee not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

if (!$contractor) {
    echo "Contractor not found. Please run the RolePermissionSeeder first.\n";
    exit;
}

// Get a client
$client = Client::first();
if (!$client) {
    echo "No clients found in the database. Please create a client first.\n";
    exit;
}

// Get a project
$project = Project::first();
if (!$project) {
    echo "No projects found in the database. Please create a project first.\n";
    exit;
}

// Make sure the project has a client
if (!$project->client_id) {
    $project->client_id = $client->id;
    $project->save();
    echo "Updated project with client ID: {$client->id}\n";
}

// Make sure the contractor is assigned to the project
if (!$contractor->projects->contains($project->id)) {
    $contractor->projects()->attach($project->id, ['role_id' => $contractorRole->id]);
    echo "Assigned contractor to project ID: {$project->id}\n";
}

// Test super admin access
Auth::login($superAdmin);
echo "Testing Super Admin access...\n";
echo "Has view_clients permission: " . ($superAdmin->hasPermission('view_clients') ? "Yes" : "No") . "\n";
echo "Can access all clients: Yes (by role)\n";

// Test manager access
Auth::login($manager);
echo "\nTesting Manager access...\n";
echo "Has view_clients permission: " . ($manager->hasPermission('view_clients') ? "Yes" : "No") . "\n";
echo "Can access all clients: Yes (by permission)\n";

// Test employee access
Auth::login($employee);
echo "\nTesting Employee access...\n";
echo "Has view_clients permission: " . ($employee->hasPermission('view_clients') ? "Yes" : "No") . "\n";
echo "Can access all clients: " . ($employee->hasPermission('view_clients') ? "Yes (by permission)" : "No") . "\n";

// Test contractor access
Auth::login($contractor);
echo "\nTesting Contractor access...\n";
echo "Has view_clients permission: " . ($contractor->hasPermission('view_clients') ? "Yes" : "No") . "\n";
echo "Can access clients through projects: " . ($contractor->clients->count() > 0 ? "Yes" : "No") . "\n";
echo "Number of accessible clients: " . $contractor->clients->count() . "\n";
echo "Client IDs accessible to contractor: " . implode(", ", $contractor->clients->pluck('id')->toArray()) . "\n";
echo "Project's client ID: " . $project->client_id . "\n";

echo "\nTest completed.\n";
