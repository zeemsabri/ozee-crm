<?php

// This script tests the simplified projects API endpoint for the dashboard
// It verifies that the endpoint returns only the required fields

echo "Testing Simplified Projects API for Dashboard\n";
echo "-------------------------------------------\n\n";

// Import necessary classes
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;

// Find a user to authenticate
$user = User::first();
if (!$user) {
    echo "No users found in the database. Please create a user first.\n";
    exit(1);
}

// Authenticate the user
Auth::login($user);
echo "Authenticated as user: {$user->name} (ID: {$user->id})\n\n";

// Test the original projects endpoint
echo "Testing original /api/projects endpoint...\n";

// Make a request to the original endpoint
$originalResponse = app()->call('\App\Http\Controllers\Api\ProjectController@index');
$originalProjects = $originalResponse->getData(true);

// Check if the response is an array
if (!is_array($originalProjects)) {
    echo "Error: Original response is not an array.\n";
    exit(1);
}

echo "Received " . count($originalProjects) . " projects from original endpoint.\n";

// If there are no projects, we can't test further
if (count($originalProjects) === 0) {
    echo "No projects found. Please create some projects first.\n";
    exit(1);
}

// Get the first project to examine its structure
$originalProject = $originalProjects[0];
echo "Original project has " . count((array)$originalProject) . " fields.\n";
echo "Fields: " . implode(', ', array_keys((array)$originalProject)) . "\n\n";

// Test the simplified projects endpoint
echo "Testing new /api/projects-simplified endpoint...\n";

// Make a request to the simplified endpoint
$simplifiedResponse = app()->call('\App\Http\Controllers\Api\ProjectController@getProjectsSimplified');
$simplifiedProjects = $simplifiedResponse->getData(true);

// Check if the response is an array
if (!is_array($simplifiedProjects)) {
    echo "Error: Simplified response is not an array.\n";
    exit(1);
}

echo "Received " . count($simplifiedProjects) . " projects from simplified endpoint.\n";

// If there are no projects, we can't test further
if (count($simplifiedProjects) === 0) {
    echo "No projects found in simplified endpoint.\n";
    exit(1);
}

// Get the first project to examine its structure
$simplifiedProject = $simplifiedProjects[0];
echo "Simplified project has " . count((array)$simplifiedProject) . " fields.\n";
echo "Fields: " . implode(', ', array_keys((array)$simplifiedProject)) . "\n\n";

// Check that only the required fields are present
$requiredFields = ['id', 'name', 'status'];
$missingFields = array_diff($requiredFields, array_keys((array)$simplifiedProject));
$extraFields = array_diff(array_keys((array)$simplifiedProject), $requiredFields);

if (count($missingFields) > 0) {
    echo "Error: Missing required fields: " . implode(', ', $missingFields) . "\n";
} else {
    echo "All required fields are present.\n";
}

if (count($extraFields) > 0) {
    echo "Warning: Extra fields found: " . implode(', ', $extraFields) . "\n";
    echo "The API should only return id, name, and status fields.\n";
} else {
    echo "No extra fields found. The API is correctly returning only the required fields.\n";
}

// Compare the data size
$originalSize = strlen(json_encode($originalProjects));
$simplifiedSize = strlen(json_encode($simplifiedProjects));
$sizeDifference = $originalSize - $simplifiedSize;
$percentReduction = round(($sizeDifference / $originalSize) * 100, 2);

echo "\nData size comparison:\n";
echo "Original response size: " . $originalSize . " bytes\n";
echo "Simplified response size: " . $simplifiedSize . " bytes\n";
echo "Size reduction: " . $sizeDifference . " bytes (" . $percentReduction . "%)\n";

echo "\nTest completed.\n";
