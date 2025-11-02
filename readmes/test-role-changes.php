<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

// Check if 'role' column exists in project_user table
echo "Checking if 'role' column exists in project_user table...\n";
$roleColumnExists = Schema::hasColumn('project_user', 'role');
echo $roleColumnExists ? "FAIL: 'role' column still exists\n" : "PASS: 'role' column has been removed\n";

// Check if 'role_id' column exists in project_user table
echo "Checking if 'role_id' column exists in project_user table...\n";
$roleIdColumnExists = Schema::hasColumn('project_user', 'role_id');
echo $roleIdColumnExists ? "PASS: 'role_id' column exists\n" : "FAIL: 'role_id' column does not exist\n";

// Test User model
echo "\nTesting User model...\n";
$user = User::first();
if ($user) {
    echo "User found: {$user->name}\n";

    // Check if 'role' property exists
    echo "Checking if 'role' property exists...\n";
    $rolePropertyExists = isset($user->role);
    echo $rolePropertyExists ? "PASS: 'role' relationship exists\n" : "FAIL: 'role' relationship does not exist\n";

    // Check if 'role_id' property exists
    echo "Checking if 'role_id' property exists...\n";
    $roleIdPropertyExists = isset($user->role_id);
    echo $roleIdPropertyExists ? "PASS: 'role_id' property exists\n" : "FAIL: 'role_id' property does not exist\n";

    // Test projects relationship
    echo "\nTesting projects relationship...\n";
    $projects = $user->projects;

    // If no projects exist, create a test project and attach the user
    if (count($projects) == 0) {
        echo "No projects found. Creating a test project...\n";

        try {
            // Get or create a client
            $client = Client::first();
            if (! $client) {
                echo "No clients found. Creating a test client...\n";
                $client = new Client;
                $client->name = 'Test Client';
                $client->email = 'test@example.com';
                $client->save();
                echo "Test client created with ID: {$client->id}\n";
            } else {
                echo "Using existing client with ID: {$client->id}\n";
            }

            // Create a test project
            $project = new Project;
            $project->name = 'Test Project';
            $project->description = 'Test project for role_id verification';
            $project->status = 'active';
            $project->payment_type = 'one_off';
            $project->client_id = $client->id;
            $project->save();

            echo "Test project created with ID: {$project->id}\n";

            // Attach the user to the project with a role_id
            $user->projects()->attach($project->id, ['role_id' => $user->role_id]);
            echo "User attached to project with role_id: {$user->role_id}\n";

            // Refresh the user to get the updated projects
            $user = $user->fresh();
            $projects = $user->projects;

            echo 'Projects after creation: '.count($projects)."\n";
        } catch (\Exception $e) {
            echo 'Error creating test project: '.$e->getMessage()."\n";
        }
    }

    if (count($projects) > 0) {
        echo 'Projects found: '.count($projects)."\n";

        // Check first project pivot
        $firstProject = $projects->first();
        echo "Checking project pivot...\n";

        // Check if 'role' exists in pivot
        echo "Checking if 'role' exists in pivot...\n";
        $pivotRoleExists = isset($firstProject->pivot->role);
        echo $pivotRoleExists ? "FAIL: 'role' still exists in pivot\n" : "PASS: 'role' has been removed from pivot\n";

        // Check if 'role_id' exists in pivot
        echo "Checking if 'role_id' exists in pivot...\n";
        $pivotRoleIdExists = isset($firstProject->pivot->role_id);
        echo $pivotRoleIdExists ? "PASS: 'role_id' exists in pivot\n" : "FAIL: 'role_id' does not exist in pivot\n";

        // Clean up test project if it was created in this test
        if ($firstProject->name == 'Test Project' && $firstProject->description == 'Test project for role_id verification') {
            echo "\nCleaning up test project...\n";
            $firstProject->delete();
            echo "Test project deleted\n";
        }
    } else {
        echo "No projects found for this user after attempted creation\n";
    }
} else {
    echo "No users found in the database\n";
}

echo "\nTest completed.\n";
