<?php

// This script tests the polymorphic relationship for the sender in the Email model
// It verifies that:
// 1. When creating an email from the frontend, sender_type is automatically set to 'App\Models\User'
// 2. We can create an email with a different sender type (e.g., 'App\Models\Client')

echo "Testing polymorphic relationship for sender in Email model\n";
echo "------------------------------------------------------\n\n";

// Import necessary classes
require_once __DIR__.'/vendor/autoload.php';

use App\Models\Client;
use App\Models\Conversation;
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

// Find a conversation to use for the test
$conversation = Conversation::first();
if (! $conversation) {
    echo "No conversations found in the database. Please create a conversation first.\n";
    exit(1);
}

echo "Using conversation: ID {$conversation->id}, Project: {$conversation->project_id}, Client: {$conversation->client_id}\n\n";

// Test 1: Create an email with a user as sender (simulating frontend)
echo "Test 1: Create an email with a user as sender (simulating frontend)\n";
echo "----------------------------------------------------------------\n";

$email1 = new Email([
    'conversation_id' => $conversation->id,
    'sender_id' => $user->id, // This should trigger the setter to set sender_type to 'App\Models\User'
    'to' => json_encode(['test@example.com']),
    'subject' => 'Test Email from User',
    'body' => 'This is a test email from a user.',
    'status' => 'draft',
]);

$email1->save();

echo "Email created with ID: {$email1->id}\n";
echo "Sender ID: {$email1->sender_id}\n";
echo "Sender Type: {$email1->sender_type}\n";
echo "Expected Sender Type: App\\Models\\User\n";
echo 'Result: '.($email1->sender_type === 'App\\Models\\User' ? 'PASS' : 'FAIL')."\n\n";

// Test 2: Create an email with a client as sender (simulating receiving an email)
echo "Test 2: Create an email with a client as sender (simulating receiving an email)\n";
echo "-----------------------------------------------------------------------\n";

// Find a client to use as sender
$client = Client::first();
if (! $client) {
    echo "No clients found in the database. Please create a client first.\n";
    exit(1);
}

$email2 = new Email([
    'conversation_id' => $conversation->id,
    'sender_id' => $client->id,
    'sender_type' => 'App\\Models\\Client', // Explicitly set sender_type to Client
    'to' => json_encode([$user->email]),
    'subject' => 'Test Email from Client',
    'body' => 'This is a test email from a client.',
    'status' => 'received',
]);

$email2->save();

echo "Email created with ID: {$email2->id}\n";
echo "Sender ID: {$email2->sender_id}\n";
echo "Sender Type: {$email2->sender_type}\n";
echo "Expected Sender Type: App\\Models\\Client\n";
echo 'Result: '.($email2->sender_type === 'App\\Models\\Client' ? 'PASS' : 'FAIL')."\n\n";

// Test 3: Verify that the sender relationship works correctly
echo "Test 3: Verify that the sender relationship works correctly\n";
echo "-------------------------------------------------------\n";

// Reload the emails to ensure relationships are fresh
$email1 = Email::find($email1->id);
$email2 = Email::find($email2->id);

// Check sender for email1 (should be a User)
$sender1 = $email1->sender;
echo "Email 1 Sender:\n";
echo '- Type: '.get_class($sender1)."\n";
echo "- ID: {$sender1->id}\n";
echo "- Name: {$sender1->name}\n";
echo 'Result: '.($sender1 instanceof User ? 'PASS' : 'FAIL')."\n\n";

// Check sender for email2 (should be a Client)
$sender2 = $email2->sender;
echo "Email 2 Sender:\n";
echo '- Type: '.get_class($sender2)."\n";
echo "- ID: {$sender2->id}\n";
echo "- Name: {$sender2->name}\n";
echo 'Result: '.($sender2 instanceof Client ? 'PASS' : 'FAIL')."\n\n";

// Test 4: Verify that a conversation can have a null contractor_id
echo "Test 4: Verify that a conversation can have a null contractor_id\n";
echo "-----------------------------------------------------------\n";

// Create a conversation with null contractor_id
$conversation2 = new Conversation([
    'subject' => 'Test Conversation with Null Contractor',
    'project_id' => $conversation->project_id,
    'client_id' => $client->id,
    'contractor_id' => null, // Set contractor_id to null
    'last_activity_at' => now(),
]);

$conversation2->save();

echo "Conversation created with ID: {$conversation2->id}\n";
echo 'Contractor ID: '.($conversation2->contractor_id === null ? 'NULL' : $conversation2->contractor_id)."\n";
echo "Expected Contractor ID: NULL\n";
echo 'Result: '.($conversation2->contractor_id === null ? 'PASS' : 'FAIL')."\n\n";

// Clean up test data
echo "Cleaning up test data...\n";
$email1->delete();
$email2->delete();
$conversation2->delete();
echo "Test data cleaned up.\n\n";

echo "All tests completed.\n";
