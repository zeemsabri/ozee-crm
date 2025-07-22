<?php

// This script tests the simplified project emails API endpoint
// It verifies that the endpoint returns only the required fields

echo "Testing Simplified Project Emails API\n";
echo "-----------------------------------\n\n";

// Import necessary classes
require_once __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;

// Find a user to authenticate
$user = User::where('role_id', 1)->first(); // Super Admin
if (!$user) {
    echo "No super admin users found in the database. Please create a super admin user first.\n";
    exit(1);
}

// Authenticate the user
Auth::login($user);
echo "Authenticated as user: {$user->name} (ID: {$user->id})\n\n";

// Find a project to test with
$project = Project::first();
if (!$project) {
    echo "No projects found in the database. Please create a project first.\n";
    exit(1);
}

echo "Using project: {$project->name} (ID: {$project->id})\n\n";

// Test the simplified project emails endpoint
echo "Testing /api/projects/{$project->id}/emails-simplified endpoint...\n";

// Make a request to the endpoint
$response = app()->call('\App\Http\Controllers\Api\EmailController@getProjectEmailsSimplified', ['projectId' => $project->id]);
$emails = $response->getData(true);

// Check if the response is an array
if (!is_array($emails)) {
    echo "Error: Response is not an array.\n";
    exit(1);
}

echo "Received " . count($emails) . " emails.\n\n";

// If there are no emails, we can't test further
if (count($emails) === 0) {
    echo "No emails found for this project. Please create some emails first.\n";
    exit(1);
}

// Check the structure of the first email
$email = $emails[0];
echo "Checking structure of first email (ID: {$email['id']})...\n";

// Check that only the required fields are present
$requiredFields = ['id', 'subject', 'sender', 'created_at', 'status', 'body', 'rejection_reason', 'approver', 'sent_at'];
$missingFields = array_diff($requiredFields, array_keys($email));
$extraFields = array_diff(array_keys($email), $requiredFields);

if (count($missingFields) > 0) {
    echo "Error: Missing required fields: " . implode(', ', $missingFields) . "\n";
} else {
    echo "All required fields are present.\n";
}

if (count($extraFields) > 0) {
    echo "Warning: Extra fields found: " . implode(', ', $extraFields) . "\n";
    echo "The API should only return the required fields.\n";
} else {
    echo "No extra fields found. The API is correctly returning only the required fields.\n";
}

// Verify that the 'to' field is not present
if (isset($email['to'])) {
    echo "Error: 'to' field is still present in the response.\n";
} else {
    echo "Success: 'to' field is not present in the response.\n";
}

// Check that sender has the correct structure
if (isset($email['sender']) && is_array($email['sender'])) {
    echo "Sender field has correct structure: " . (isset($email['sender']['id']) && isset($email['sender']['name']) ? "Yes" : "No") . "\n";
} else {
    echo "Error: Sender field is missing or has incorrect structure.\n";
}

// Print the email data
echo "\nEmail data:\n";
echo "- ID: {$email['id']}\n";
echo "- Subject: {$email['subject']}\n";
echo "- Sender: " . ($email['sender'] ? "{$email['sender']['name']} (ID: {$email['sender']['id']})" : "N/A") . "\n";
echo "- Created At: {$email['created_at']}\n";
echo "- Status: {$email['status']}\n";
echo "- Body: " . (strlen($email['body']) > 50 ? substr($email['body'], 0, 50) . "..." : $email['body']) . "\n";

// Test the original endpoint to compare
echo "\nTesting original /api/projects/{$project->id}/emails endpoint for comparison...\n";

// Make a request to the original endpoint
$originalResponse = app()->call('\App\Http\Controllers\Api\EmailController@getProjectEmails', ['projectId' => $project->id]);
$originalEmails = $originalResponse->getData(true);

// Check if the response is an array
if (!is_array($originalEmails)) {
    echo "Error: Original response is not an array.\n";
    exit(1);
}

echo "Received " . count($originalEmails) . " emails from original endpoint.\n\n";

// If there are no emails, we can't compare
if (count($originalEmails) === 0) {
    echo "No emails found for this project in the original endpoint.\n";
    exit(1);
}

// Compare the number of fields in the first email from each endpoint
$originalEmail = $originalEmails[0];
echo "Original email has " . count((array)$originalEmail) . " fields.\n";
echo "Simplified email has " . count((array)$email) . " fields.\n";
echo "Difference: " . (count((array)$originalEmail) - count((array)$email)) . " fields removed.\n";

echo "\nTest completed.\n";
