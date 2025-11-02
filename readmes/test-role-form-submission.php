<?php

// This is a simple test script to verify that the role update form submission works correctly
// Run this script with: php test-role-form-submission.php

require __DIR__.'/vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Api\RoleController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "Testing role form submission...\n\n";

// Find a role to update
$role = Role::first();

if (! $role) {
    echo "No roles found. Please run the RolePermissionSeeder first.\n";
    exit;
}

echo 'Found role: '.$role->name.' (ID: '.$role->id.")\n";

// Find a user with manage_roles permission
$user = User::whereHas('role.permissions', function ($query) {
    $query->where('slug', 'manage_roles');
})->first();

if (! $user) {
    echo "No users with manage_roles permission found. Please create a user with appropriate permissions first.\n";
    exit;
}

echo 'Found user: '.$user->name.' (ID: '.$user->id.")\n";

// Log in as the user
Auth::login($user);
echo 'Logged in as: '.Auth::user()->name."\n";

// Simulate a form submission by creating a request with the form data
$request = Request::create('/api/roles/'.$role->id, 'PUT', [
    'name' => $role->name,
    'description' => $role->description,
    'type' => $role->type ?? 'application',
    'permissions' => $role->permissions->pluck('id')->toArray(),
]);

// Set the authenticated user for the request
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Create an instance of the RoleController
$controller = new RoleController;

// Call the update method
$response = $controller->update($request, $role);

// Check the response
echo 'Response status code: '.$response->getStatusCode()."\n";
echo 'Response content: '.$response->getContent()."\n";

// Check if the status code is 200 OK
if ($response->getStatusCode() === 200) {
    echo "SUCCESS: The role update API returns a 200 OK response\n";
} else {
    echo 'FAILURE: The role update API returns a '.$response->getStatusCode()." response\n";
}

// Now simulate what happens in the Vue component
echo "\nSimulating Vue component form submission...\n";

// In the Vue component, we're using axios.put() to make a direct API call
// Here we'll simulate that by making a similar request
$request = Request::create('/api/roles/'.$role->id, 'PUT', [
    'name' => $role->name,
    'description' => $role->description,
    'type' => $role->type ?? 'application',
    'permissions' => $role->permissions->pluck('id')->toArray(),
]);

// Set the request to expect JSON (like axios would)
$request->headers->set('Accept', 'application/json');
$request->headers->set('Content-Type', 'application/json');

// Set the authenticated user for the request
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Call the update method
$response = $controller->update($request, $role);

// Check the response
echo 'Response status code: '.$response->getStatusCode()."\n";
echo 'Response content: '.$response->getContent()."\n";

// Check if the status code is 200 OK
if ($response->getStatusCode() === 200) {
    echo "SUCCESS: The simulated Vue component form submission returns a 200 OK response\n";
} else {
    echo 'FAILURE: The simulated Vue component form submission returns a '.$response->getStatusCode()." response\n";
}

echo "\nTest completed.\n";
