<?php

// This script tests the simplified rejected emails API endpoint
// It verifies that the endpoint returns only the required fields

echo "Testing Simplified Rejected Emails API\n";
echo "-------------------------------------\n\n";

// Import necessary classes
require_once __DIR__.'/vendor/autoload.php';

use App\Models\Email;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Find a user to authenticate
$user = User::first();
if (! $user) {
    echo "No users found in the database. Please create a user first.\n";
    exit(1);
}

// Authenticate the user
Auth::login($user);
echo "Authenticated as user: {$user->name} (ID: {$user->id})\n\n";

// Test the simplified rejected emails endpoint
echo "Testing /api/emails/rejected-simplified endpoint...\n";

// Make a request to the endpoint
$response = app()->call('\App\Http\Controllers\Api\EmailController@rejectedSimplified');
$emails = $response->getData(true);

// Check if the response is an array
if (! is_array($emails)) {
    echo "Error: Response is not an array.\n";
    exit(1);
}

echo 'Received '.count($emails)." rejected emails.\n\n";

// If there are no rejected emails, create a test one
if (count($emails) === 0) {
    echo "No rejected emails found. Creating a test rejected email...\n";

    // Find an existing email or create a new one
    $email = Email::first();
    if (! $email) {
        echo "No emails found in the database. Please create an email first.\n";
        exit(1);
    }

    // Update the email to be rejected
    $email->update([
        'status' => 'rejected',
        'rejection_reason' => 'Test rejection reason',
    ]);

    echo "Created test rejected email with ID: {$email->id}\n";

    // Make another request to the endpoint
    $response = app()->call('\App\Http\Controllers\Api\EmailController@rejectedSimplified');
    $emails = $response->getData(true);

    echo 'Received '.count($emails)." rejected emails after creating test email.\n\n";
}

// Check the structure of the first email
if (count($emails) > 0) {
    $email = $emails[0];
    echo "Checking structure of first rejected email (ID: {$email['id']})...\n";

    // Check that only the required fields are present
    $requiredFields = ['id', 'subject', 'body', 'rejection_reason', 'created_at'];
    $missingFields = array_diff($requiredFields, array_keys($email));
    $extraFields = array_diff(array_keys($email), $requiredFields);

    if (count($missingFields) > 0) {
        echo 'Error: Missing required fields: '.implode(', ', $missingFields)."\n";
    } else {
        echo "All required fields are present.\n";
    }

    if (count($extraFields) > 0) {
        echo 'Warning: Extra fields found: '.implode(', ', $extraFields)."\n";
        echo "The API should only return the required fields (id, subject, body, rejection_reason, created_at).\n";
    } else {
        echo "No extra fields found. The API is correctly returning only the required fields.\n";
    }

    // Print the email data
    echo "\nEmail data:\n";
    echo "- ID: {$email['id']}\n";
    echo "- Subject: {$email['subject']}\n";
    echo '- Body: '.(strlen($email['body']) > 50 ? substr($email['body'], 0, 50).'...' : $email['body'])."\n";
    echo "- Rejection Reason: {$email['rejection_reason']}\n";
    echo "- Created At: {$email['created_at']}\n";
} else {
    echo "No rejected emails found to check structure.\n";
}

echo "\nTest completed.\n";
