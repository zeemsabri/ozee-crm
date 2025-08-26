<?php

// This script tests the updated project-specific roles API functionality
// It verifies that the ProjectController's show method correctly includes role information for project-specific role_ids

// Import necessary classes
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

echo "Testing project-specific roles API fix\n";
echo "-------------------------------------\n\n";

// Find a test project with users
$project = Project::with(['users' => function ($query) {
    $query->withPivot('role_id');
}])->first();

if (!$project) {
    echo "No projects found. Please create a project first.\n";
    exit(1);
}

echo "Found project: {$project->name} (ID: {$project->id})\n";
echo "Number of users assigned to project: " . count($project->users) . "\n\n";

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

if (!$userWithProjectRole) {
    echo "No users with project-specific roles found. Please assign a role to a user in the project first.\n";
    exit(1);
}

echo "Found user with project-specific role: {$userWithProjectRole->name} (ID: {$userWithProjectRole->id})\n";
echo "Project-specific role ID: {$userWithProjectRole->pivot->role_id}\n\n";

// Get the role information for the project-specific role
$projectRole = Role::find($userWithProjectRole->pivot->role_id);
if (!$projectRole) {
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
    'project' => $project
]);

// Check if the response contains the expected data
$responseData = $response->getData(true);

echo "Checking response data...\n";

// Check if the users array exists in the response
if (!isset($responseData['users']) || !is_array($responseData['users'])) {
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

if (!$currentUserInResponse) {
    echo "ERROR: Current user not found in response.\n";
    exit(1);
}

echo "Found current user in response.\n";

// Check if the pivot data contains the role_id
if (!isset($currentUserInResponse['pivot']) || !isset($currentUserInResponse['pivot']['role_id'])) {
    echo "ERROR: Pivot data does not contain role_id.\n";
    exit(1);
}

echo "Pivot data contains role_id: {$currentUserInResponse['pivot']['role_id']}\n";

// Check if the pivot data contains the role_data
if (!isset($currentUserInResponse['pivot']['role_data'])) {
    echo "ERROR: Pivot data does not contain role_data. The fix is not working.\n";
    exit(1);
}

echo "Pivot data contains role_data:\n";
echo "- ID: {$currentUserInResponse['pivot']['role_data']['id']}\n";
echo "- Name: {$currentUserInResponse['pivot']['role_data']['name']}\n";
echo "- Slug: {$currentUserInResponse['pivot']['role_data']['slug']}\n\n";

// Verify that the role_data matches the expected role information
if ($currentUserInResponse['pivot']['role_data']['id'] !== $projectRole->id ||
    $currentUserInResponse['pivot']['role_data']['name'] !== $projectRole->name ||
    $currentUserInResponse['pivot']['role_data']['slug'] !== $projectRole->slug) {
    echo "ERROR: Role data in response does not match expected role information.\n";
    exit(1);
}

echo "SUCCESS: Role data in response matches expected role information.\n";
echo "The fix is working correctly!\n\n";

// Check if the user has a global role loaded
if (!isset($currentUserInResponse['role'])) {
    echo "WARNING: User's global role is not loaded in the response.\n";
} else {
    echo "User's global role is loaded in the response:\n";
    echo "- ID: {$currentUserInResponse['role']['id']}\n";
    echo "- Name: {$currentUserInResponse['role']['name']}\n";
    echo "- Slug: {$currentUserInResponse['role']['slug']}\n\n";
}

echo "Test completed successfully.\n";
