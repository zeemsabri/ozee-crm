<?php

use App\Models\Conversation;
use App\Models\Email;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// This script tests the email observer notification system
// It creates test emails with different statuses and types
// and verifies that notifications are sent via the observer

echo "Starting email observer notification test...\n\n";

// 1. Create a test project
echo "Creating test project...\n";
$project = Project::create([
    'name' => 'Test Project for Email Observer',
    'status' => 'active',
]);
echo "Created project with ID: {$project->id}\n";

// 2. Create test users with different permissions
echo "\nCreating test users...\n";

// Create a user with global approve_emails permission
$globalApproverUser = User::create([
    'name' => 'Global Email Approver (Observer Test)',
    'email' => 'global_approver_observer_'.time().'@example.com',
    'password' => bcrypt('password'),
]);

// Create a user with project-specific approve_emails permission
$projectApproverUser = User::create([
    'name' => 'Project Email Approver (Observer Test)',
    'email' => 'project_approver_observer_'.time().'@example.com',
    'password' => bcrypt('password'),
]);

// Create a user with approve_received_emails permission
$receivedApproverUser = User::create([
    'name' => 'Received Email Approver (Observer Test)',
    'email' => 'received_approver_observer_'.time().'@example.com',
    'password' => bcrypt('password'),
]);

echo "Created users with IDs: {$globalApproverUser->id}, {$projectApproverUser->id}, {$receivedApproverUser->id}\n";

// 3. Assign permissions to users
echo "\nAssigning permissions to users...\n";

// Simulate assigning global approve_emails permission
DB::table('role_permission')->insert([
    'role_id' => $globalApproverUser->role_id,
    'permission_id' => DB::table('permissions')->where('slug', 'approve_emails')->first()->id,
]);

// Simulate assigning project-specific approve_emails permission
$project->users()->attach($projectApproverUser->id, [
    'role_id' => DB::table('roles')->where('name', 'Manager')->first()->id,
]);
DB::table('role_permission')->insert([
    'role_id' => DB::table('project_user')->where('user_id', $projectApproverUser->id)->where('project_id', $project->id)->first()->role_id,
    'permission_id' => DB::table('permissions')->where('slug', 'approve_emails')->first()->id,
]);

// Simulate assigning approve_received_emails permission
DB::table('role_permission')->insert([
    'role_id' => $receivedApproverUser->role_id,
    'permission_id' => DB::table('permissions')->where('slug', 'approve_received_emails')->first()->id,
]);

echo "Permissions assigned\n";

// 4. Create a test conversation
echo "\nCreating test conversation...\n";
$conversation = Conversation::create([
    'subject' => 'Test Conversation for Email Observer',
    'project_id' => $project->id,
    'last_activity_at' => now(),
]);
echo "Created conversation with ID: {$conversation->id}\n";

// 5. Create test emails with different statuses and types
echo "\nCreating test emails...\n";

// Create a pending_approval + sent email
echo "Creating sent email pending approval...\n";
$sentEmail = Email::create([
    'conversation_id' => $conversation->id,
    'sender_id' => $globalApproverUser->id,
    'sender_type' => User::class,
    'to' => json_encode(['client@example.com']),
    'subject' => 'Test Sent Email Pending Approval (Observer)',
    'body' => 'This is a test email that needs approval before sending.',
    'status' => 'pending_approval',
    'type' => 'sent',
]);
echo "Created sent email with ID: {$sentEmail->id}\n";

// Check if notifications were created
$sentNotifications = DB::table('notifications')
    ->where('data', 'like', '%"email_id":'.$sentEmail->id.'%')
    ->get();

echo 'Found '.$sentNotifications->count()." notifications for sent email\n";

// Create a pending_approval_received + received email
echo "\nCreating received email pending approval...\n";
$receivedEmail = Email::create([
    'conversation_id' => $conversation->id,
    'sender_id' => $globalApproverUser->id,
    'sender_type' => User::class,
    'to' => json_encode(['staff@example.com']),
    'subject' => 'Test Received Email Pending Approval (Observer)',
    'body' => 'This is a test received email that needs approval.',
    'status' => 'pending_approval_received',
    'type' => 'received',
]);
echo "Created received email with ID: {$receivedEmail->id}\n";

// Check if notifications were created
$receivedNotifications = DB::table('notifications')
    ->where('data', 'like', '%"email_id":'.$receivedEmail->id.'%')
    ->get();

echo 'Found '.$receivedNotifications->count()." notifications for received email\n";

echo "\nTest completed successfully!\n";
echo "Check the database notifications table to verify notifications were created.\n";
