<?php

// This script tests the simplified pending approval API endpoint
// It verifies that the endpoint returns only the required fields

echo "Testing Simplified Pending Approval API\n";
echo "-------------------------------------\n\n";

// Import necessary classes
require_once __DIR__.'/vendor/autoload.php';

use App\Models\Email;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Find a user to authenticate
$user = User::where('role_id', 1)->first(); // Super Admin
if (! $user) {
    echo "No super admin users found in the database. Please create a super admin user first.\n";
    exit(1);
}

// Authenticate the user
Auth::login($user);
echo "Authenticated as user: {$user->name} (ID: {$user->id})\n\n";

// Test the simplified pending approval endpoint
echo "Testing /api/emails/pending-approval-simplified endpoint...\n";

// Make a request to the endpoint
$response = app()->call('\App\Http\Controllers\Api\EmailController@pendingApprovalSimplified');
$emails = $response->getData(true);

// Check if the response is an array
if (! is_array($emails)) {
    echo "Error: Response is not an array.\n";
    exit(1);
}

echo 'Received '.count($emails)." pending approval emails.\n\n";

// If there are no pending approval emails, create a test one
if (count($emails) === 0) {
    echo "No pending approval emails found. Creating a test pending approval email...\n";

    // Find an existing email or create a new one
    $email = Email::first();
    if (! $email) {
        echo "No emails found in the database. Please create an email first.\n";
        exit(1);
    }

    // Update the email to be pending approval
    $email->update([
        'status' => 'pending_approval',
    ]);

    echo "Created test pending approval email with ID: {$email->id}\n";

    // Make another request to the endpoint
    $response = app()->call('\App\Http\Controllers\Api\EmailController@pendingApprovalSimplified');
    $emails = $response->getData(true);

    echo 'Received '.count($emails)." pending approval emails after creating test email.\n\n";
}

// Check the structure of the first email
if (count($emails) > 0) {
    $email = $emails[0];
    echo "Checking structure of first pending approval email (ID: {$email['id']})...\n";

    // Check that only the required fields are present
    $requiredFields = ['id', 'project', 'client', 'subject', 'sender', 'created_at', 'body'];
    $missingFields = array_diff($requiredFields, array_keys($email));

    if (count($missingFields) > 0) {
        echo 'Error: Missing required fields: '.implode(', ', $missingFields)."\n";
    } else {
        echo "All required fields are present.\n";
    }

    // Check that project and client have the correct structure
    if (isset($email['project']) && is_array($email['project'])) {
        echo 'Project field has correct structure: '.(isset($email['project']['id']) && isset($email['project']['name']) ? 'Yes' : 'No')."\n";
    } else {
        echo "Error: Project field is missing or has incorrect structure.\n";
    }

    if (isset($email['client']) && is_array($email['client'])) {
        echo 'Client field has correct structure: '.(isset($email['client']['id']) && isset($email['client']['name']) ? 'Yes' : 'No')."\n";
    } else {
        echo "Error: Client field is missing or has incorrect structure.\n";
    }

    if (isset($email['sender']) && is_array($email['sender'])) {
        echo 'Sender field has correct structure: '.(isset($email['sender']['id']) && isset($email['sender']['name']) ? 'Yes' : 'No')."\n";
    } else {
        echo "Error: Sender field is missing or has incorrect structure.\n";
    }

    // Print the email data
    echo "\nEmail data:\n";
    echo "- ID: {$email['id']}\n";
    echo '- Project: '.($email['project'] ? "{$email['project']['name']} (ID: {$email['project']['id']})" : 'N/A')."\n";
    echo '- Client: '.($email['client'] ? "{$email['client']['name']} (ID: {$email['client']['id']})" : 'N/A')."\n";
    echo "- Subject: {$email['subject']}\n";
    echo '- Sender: '.($email['sender'] ? "{$email['sender']['name']} (ID: {$email['sender']['id']})" : 'N/A')."\n";
    echo "- Created At: {$email['created_at']}\n";
    echo '- Body: '.(strlen($email['body']) > 50 ? substr($email['body'], 0, 50).'...' : $email['body'])."\n";
} else {
    echo "No pending approval emails found to check structure.\n";
}

echo "\nTest completed.\n";
