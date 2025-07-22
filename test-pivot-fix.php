<?php

// This script tests the fix for the "Indirect modification of overloaded property" error
// in the ProjectController's show method

// Import necessary classes
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "Testing fix for pivot property modification issue\n";
echo "------------------------------------------------\n\n";

// Find a test project
$project = Project::first();
if (!$project) {
    echo "No projects found. Please create a project first.\n";
    exit(1);
}

echo "Found project: {$project->name} (ID: {$project->id})\n\n";

// Find a user to test with
$user = User::first();
if (!$user) {
    echo "No users found. Please create a user first.\n";
    exit(1);
}

echo "Found user: {$user->name} (ID: {$user->id})\n\n";

// Log in as the user
Auth::login($user);
echo "Logged in as {$user->name}\n\n";

// Call the ProjectController's show method directly
echo "Calling ProjectController's show method...\n";
try {
    $response = app()->call('\App\Http\Controllers\Api\ProjectController@show', [
        'project' => $project
    ]);

    echo "SUCCESS: ProjectController's show method executed without errors.\n";
    echo "The fix for the 'Indirect modification of overloaded property' error is working correctly!\n\n";

    // Check if the response contains the expected data
    $responseData = $response->getData(true);

    // Check if the users array exists in the response
    if (!isset($responseData['users']) || !is_array($responseData['users'])) {
        echo "WARNING: Response does not contain users array.\n";
    } else {
        echo "Response contains users array with " . count($responseData['users']) . " users.\n";

        // Check if any user has role_data with permissions
        $hasRoleDataWithPermissions = false;
        foreach ($responseData['users'] as $responseUser) {
            if (isset($responseUser['pivot']) &&
                isset($responseUser['pivot']['role_data']) &&
                isset($responseUser['pivot']['role_data']['permissions'])) {
                $hasRoleDataWithPermissions = true;
                echo "Found user with role_data and permissions in the response.\n";
                echo "Number of permissions: " . count($responseUser['pivot']['role_data']['permissions']) . "\n";
                break;
            }
        }

        if (!$hasRoleDataWithPermissions) {
            echo "WARNING: No users with role_data and permissions found in the response.\n";
            echo "This might be expected if no users have project-specific roles with permissions.\n";
        }
    }
} catch (\Exception $e) {
    echo "ERROR: An exception occurred while calling the ProjectController's show method:\n";
    echo $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nTest completed successfully.\n";
