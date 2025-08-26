<?php

use App\Models\Email;
use Illuminate\Support\Facades\Route;

// Get the first email from the database for testing
$email = Email::first();

if (!$email) {
    echo "No emails found in the database for testing.\n";
    exit;
}

// Output the URL to test
$url = route('emails.preview', ['email' => $email->id]);
echo "Test URL: {$url}\n";
echo "Email ID: {$email->id}\n";
echo "Please visit the above URL in your browser to test the email preview functionality.\n";
