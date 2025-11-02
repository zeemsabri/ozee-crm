<?php

// This script tests that the user.pivot.role property is correctly set in the ProjectController's show method
// Run this script to verify that the role name is properly displayed in the UI

// Import necessary classes
require_once __DIR__.'/vendor/autoload.php';

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "Testing user.pivot.role display fix\n";
echo "--------------------------------\n\n";

// Find a test project with users
$project = Project::with(['users' => function ($query) {
    $query->withPivot('role_id');
}])->first();

if (! $project) {
    echo "No projects found. Please create a project first.\n";
    exit(1);
}

echo "Found project: {$project->name} (ID: {$project->id})\n";
echo 'Number of users assigned to project: '.count($project->users)."\n\n";

// Check if there are any users assigned to the project
if (count($project->users) === 0) {
    echo "No users assigned to this project. Please assign users to the project first.\n";
    exit(1);
}

// Find a user with a project-specific role
$userWithProjectRole = null;
foreach ($project->users as $user) {
    if (isset($user->pivot->role_id)) {
        $userWithProjectRole = $user;
        break;
    }
}

if (! $userWithProjectRole) {
    echo "No users with project-specific roles found. Please assign a role to a user in the project first.\n";
    exit(1);
}

echo "Found user with project-specific role: {$userWithProjectRole->name} (ID: {$userWithProjectRole->id})\n";
echo "Project-specific role ID: {$userWithProjectRole->pivot->role_id}\n\n";

// Get the role information for the project-specific role
$projectRole = Role::find($userWithProjectRole->pivot->role_id);
if (! $projectRole) {
    echo "Role with ID {$userWithProjectRole->pivot->role_id} not found.\n";
    exit(1);
}

echo "Project-specific role information:\n";
echo "- ID: {$projectRole->id}\n";
echo "- Name: {$projectRole->name}\n";
echo "- Slug: {$projectRole->slug}\n\n";

// Log in as the user with the project-specific role
Auth::login($userWithProjectRole);
echo "Logged in as {$userWithProjectRole->name}\n\n";

// Call the ProjectController's show method directly
echo "Calling ProjectController's show method...\n";
$response = app()->call('\App\Http\Controllers\Api\ProjectController@show', [
    'project' => $project,
]);

// Check if the response contains the expected data
$responseData = $response->getData(true);

echo "Checking response data...\n";

// Check if the users array exists in the response
if (! isset($responseData['users']) || ! is_array($responseData['users'])) {
    echo "ERROR: Response does not contain users array.\n";
    exit(1);
}

// Find the current user in the response
$currentUserInResponse = null;
foreach ($responseData['users'] as $user) {
    if ($user['id'] === $userWithProjectRole->id) {
        $currentUserInResponse = $user;
        break;
    }
}

if (! $currentUserInResponse) {
    echo "ERROR: Current user not found in response.\n";
    exit(1);
}

echo "Found current user in response.\n";

// Check if the pivot data contains the role_id
if (! isset($currentUserInResponse['pivot']) || ! isset($currentUserInResponse['pivot']['role_id'])) {
    echo "ERROR: Pivot data does not contain role_id.\n";
    exit(1);
}

echo "Pivot data contains role_id: {$currentUserInResponse['pivot']['role_id']}\n";

// Check if the pivot data contains the role property
if (! isset($currentUserInResponse['pivot']['role'])) {
    echo "ERROR: Pivot data does not contain role property. The fix is not working.\n";
    exit(1);
}

echo "Pivot data contains role property: {$currentUserInResponse['pivot']['role']}\n";

// Verify that the role property matches the expected role name
if ($currentUserInResponse['pivot']['role'] !== $projectRole->name) {
    echo "ERROR: Role property in response does not match expected role name.\n";
    echo "Expected: {$projectRole->name}\n";
    echo "Actual: {$currentUserInResponse['pivot']['role']}\n";
    exit(1);
}

echo "SUCCESS: Role property in response matches expected role name.\n";
echo "The fix is working correctly!\n\n";

// Check if the role_data is also present (from previous fixes)
if (! isset($currentUserInResponse['pivot']['role_data'])) {
    echo "WARNING: Pivot data does not contain role_data. This might be an issue with previous fixes.\n";
} else {
    echo "Pivot data also contains role_data (from previous fixes):\n";
    echo "- ID: {$currentUserInResponse['pivot']['role_data']['id']}\n";
    echo "- Name: {$currentUserInResponse['pivot']['role_data']['name']}\n";
    echo "- Slug: {$currentUserInResponse['pivot']['role_data']['slug']}\n";
}

echo "\nTest completed successfully.\n";
echo "The role name should now be displayed correctly in the UI at line 393 of Show.vue:\n";
echo "<p><strong class=\"text-gray-900\">{{ user.name }}</strong> ({{ user.pivot.role }})</p>\n";
