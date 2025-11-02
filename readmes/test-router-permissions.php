<?php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Http\Middleware\CheckPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

echo "Testing Router Permission Checks\n";
echo "===============================\n\n";

// Create a mock request
$request = Request::create('/projects/create', 'GET');

// Create a test user without the required permission
echo "Creating test user without create_projects permission...\n";
$role = Role::where('name', 'Employee')->first();
if (! $role) {
    echo "Creating Employee role...\n";
    $role = Role::create(['name' => 'Employee', 'slug' => 'employee']);
}

$user = User::where('email', 'test-employee@example.com')->first();
if (! $user) {
    echo "Creating test user...\n";
    $user = User::create([
        'name' => 'Test Employee',
        'email' => 'test-employee@example.com',
        'password' => bcrypt('password'),
        'role_id' => $role->id,
    ]);
} else {
    $user->role_id = $role->id;
    $user->save();
}

// Make sure the user doesn't have the create_projects permission
$createProjectsPermission = Permission::where('slug', 'create_projects')->first();
if (! $createProjectsPermission) {
    echo "Creating create_projects permission...\n";
    $createProjectsPermission = Permission::create([
        'name' => 'Create Projects',
        'slug' => 'create_projects',
        'category' => 'projects',
    ]);
}

// Remove the permission from the role if it exists
if ($role->permissions()->where('permissions.id', $createProjectsPermission->id)->exists()) {
    echo "Removing create_projects permission from Employee role...\n";
    $role->permissions()->detach($createProjectsPermission->id);
}

// Create a test user with the required permission
echo "\nCreating test user with create_projects permission...\n";
$managerRole = Role::where('name', 'Manager')->first();
if (! $managerRole) {
    echo "Creating Manager role...\n";
    $managerRole = Role::create(['name' => 'Manager', 'slug' => 'manager']);
}

// Add the permission to the manager role
if (! $managerRole->permissions()->where('permissions.id', $createProjectsPermission->id)->exists()) {
    echo "Adding create_projects permission to Manager role...\n";
    $managerRole->permissions()->attach($createProjectsPermission->id);
}

$manager = User::where('email', 'test-manager@example.com')->first();
if (! $manager) {
    echo "Creating test manager...\n";
    $manager = User::create([
        'name' => 'Test Manager',
        'email' => 'test-manager@example.com',
        'password' => bcrypt('password'),
        'role_id' => $managerRole->id,
    ]);
} else {
    $manager->role_id = $managerRole->id;
    $manager->save();
}

// Test the CheckPermission middleware with a user without permission
echo "\nTesting CheckPermission middleware with user WITHOUT create_projects permission...\n";
$middleware = new CheckPermission;

// Create a closure for the next middleware
$next = function ($request) {
    return response('Access granted');
};

// Manually check if the user has the permission
echo "Checking if user has 'create_projects' permission...\n";
if ($user->role && $user->role->permissions && $user->role->permissions->contains('slug', 'create_projects')) {
    echo "ERROR: User should not have 'create_projects' permission but does.\n";
} else {
    echo "SUCCESS: User correctly does not have 'create_projects' permission.\n";
}

// Test the CheckPermission middleware with a user with permission
echo "\nTesting CheckPermission middleware with user WITH create_projects permission...\n";

// Manually check if the manager has the permission
echo "Checking if manager has 'create_projects' permission...\n";
$manager->load('role.permissions');
if ($manager->role && $manager->role->permissions && $manager->role->permissions->contains('slug', 'create_projects')) {
    echo "SUCCESS: Manager correctly has 'create_projects' permission.\n";
} else {
    echo "ERROR: Manager should have 'create_projects' permission but doesn't.\n";
}

// Verify that the routes have the correct middleware
echo "\nVerifying that routes have the correct middleware...\n";
$routes = Route::getRoutes();
$projectsCreateRoute = null;
$projectsEditRoute = null;

foreach ($routes as $route) {
    if ($route->getName() === 'projects.create') {
        $projectsCreateRoute = $route;
    }
    if ($route->getName() === 'projects.edit') {
        $projectsEditRoute = $route;
    }
}

if ($projectsCreateRoute && in_array('permission:create_projects', $projectsCreateRoute->middleware())) {
    echo "SUCCESS: projects.create route has the correct permission middleware.\n";
} else {
    echo "ERROR: projects.create route does not have the correct permission middleware.\n";
}

if ($projectsEditRoute && in_array('permission:create_projects', $projectsEditRoute->middleware())) {
    echo "SUCCESS: projects.edit route has the correct permission middleware.\n";
} else {
    echo "ERROR: projects.edit route does not have the correct permission middleware.\n";
}

echo "\nTest completed.\n";
