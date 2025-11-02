<?php

use App\Models\Email;
use App\Models\User;

// Get a sent email for testing
$sentEmail = Email::where('type', 'sent')->first();
if (! $sentEmail) {
    echo "No sent emails found in the database for testing.\n";
} else {
    echo "Found sent email with ID: {$sentEmail->id}\n";
    echo "Project ID: {$sentEmail->conversation->project_id}\n";
    echo "Sender ID: {$sentEmail->sender_id}\n";
}

// Get a received email for testing
$receivedEmail = Email::where('type', 'received')->first();
if (! $receivedEmail) {
    echo "No received emails found in the database for testing.\n";
} else {
    echo "Found received email with ID: {$receivedEmail->id}\n";
    echo "Project ID: {$receivedEmail->conversation->project_id}\n";
}

// Get users with different roles for testing
$superAdmin = User::whereHas('role', function ($query) {
    $query->where('name', 'Super Admin');
})->first();

$manager = User::whereHas('role', function ($query) {
    $query->where('name', 'Manager');
})->first();

$contractor = User::whereHas('role', function ($query) {
    $query->where('name', 'Contractor');
})->first();

echo "\nUsers for testing:\n";
echo 'Super Admin ID: '.($superAdmin ? $superAdmin->id : 'Not found')."\n";
echo 'Manager ID: '.($manager ? $manager->id : 'Not found')."\n";
echo 'Contractor ID: '.($contractor ? $contractor->id : 'Not found')."\n";

// Output the URLs to test
if ($sentEmail) {
    $url = route('emails.preview', ['email' => $sentEmail->id]);
    echo "\nTest URL for sent email: {$url}\n";
    echo "To test with different users, log in as each user and visit the URL.\n";
}

if ($receivedEmail) {
    $url = route('emails.preview', ['email' => $receivedEmail->id]);
    echo "\nTest URL for received email: {$url}\n";
    echo "To test with different users, log in as each user and visit the URL.\n";
}

echo "\nExpected behavior:\n";
echo "1. For sent emails:\n";
echo "   - The sender should be able to view the email\n";
echo "   - Users with 'approve_emails' permission globally should be able to view the email\n";
echo "   - Users with 'approve_emails' permission for the project should be able to view the email\n";
echo "   - Other users should get a 403 Unauthorized response\n";
echo "\n2. For received emails:\n";
echo "   - Users with 'approve_received_emails' permission globally should be able to view the email\n";
echo "   - Users with 'approve_received_emails' permission for the project should be able to view the email\n";
echo "   - Other users should get a 403 Unauthorized response\n";
