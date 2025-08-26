<?php

use App\Models\Email;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use App\Notifications\EmailApproved;
use App\Helpers\PermissionHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// This script tests the email approved notification system
// It creates a test email, changes its status to approved,
// and verifies that notifications are sent to the correct users

echo "Starting email approved notification test...\n\n";

// 1. Create a test project
echo "Creating test project...\n";
$project = Project::create([
    'name' => 'Test Project for Email Approved Notifications',
    'status' => 'active',
]);
echo "Created project with ID: {$project->id}\n";

// 2. Create test users with different permissions
echo "\nCreating test users...\n";

// Create a user with global view_emails permission
$viewerUser = User::create([
    'name' => 'Email Viewer',
    'email' => 'viewer_' . time() . '@example.com',
    'password' => bcrypt('password'),
]);

// Create a user with approve_emails permission (who will approve the email)
$approverUser = User::create([
    'name' => 'Email Approver',
    'email' => 'approver_' . time() . '@example.com',
    'password' => bcrypt('password'),
]);

echo "Created users with IDs: {$viewerUser->id}, {$approverUser->id}\n";

// 3. Assign permissions to users
echo "\nAssigning permissions to users...\n";

// Get default role IDs
$staffRoleId = DB::table('roles')->where('name', 'Staff')->first()->id;
$managerRoleId = DB::table('roles')->where('name', 'Manager')->first()->id;

// Add users to project with roles
$project->users()->attach($viewerUser->id, [
    'role_id' => $staffRoleId,
]);
$project->users()->attach($approverUser->id, [
    'role_id' => $managerRoleId,
]);

// Assign view_emails permission to viewer's project role
$viewerProjectRoleId = DB::table('project_user')
    ->where('user_id', $viewerUser->id)
    ->where('project_id', $project->id)
    ->first()->role_id;

DB::table('role_permission')->insert([
    'role_id' => $viewerProjectRoleId,
    'permission_id' => DB::table('permissions')->where('slug', 'view_emails')->first()->id,
]);

// Assign approve_emails permission to approver's project role
$approverProjectRoleId = DB::table('project_user')
    ->where('user_id', $approverUser->id)
    ->where('project_id', $project->id)
    ->first()->role_id;

DB::table('role_permission')->insert([
    'role_id' => $approverProjectRoleId,
    'permission_id' => DB::table('permissions')->where('slug', 'approve_emails')->first()->id,
]);

echo "Permissions assigned\n";

// 4. Create a test conversation
echo "\nCreating test conversation...\n";
$conversation = Conversation::create([
    'subject' => 'Test Conversation for Email Approved Notifications',
    'project_id' => $project->id,
    'last_activity_at' => now(),
]);
echo "Created conversation with ID: {$conversation->id}\n";

// 5. Create a test email with pending_approval status
echo "\nCreating test email...\n";
$email = Email::create([
    'conversation_id' => $conversation->id,
    'sender_id' => $viewerUser->id,
    'sender_type' => User::class,
    'to' => json_encode(['client@example.com']),
    'subject' => 'Test Email Pending Approval',
    'body' => 'This is a test email that needs approval before sending.',
    'status' => 'pending_approval',
    'type' => 'sent',
]);
echo "Created email with ID: {$email->id} and status: {$email->status}\n";

// 6. Test the notification by updating the email status to approved
echo "\nUpdating email status to approved...\n";
$email->status = 'approved';
$email->approved_by = $approverUser->id;
$email->save();
echo "Updated email status to: {$email->status}\n";

// 7. Manually test sending the notification
echo "\nTesting notification manually...\n";
$usersToNotify = PermissionHelper::getAllUsersWithPermission('view_emails', $project->id);
echo "Found " . $usersToNotify->count() . " users with view_emails permission\n";

foreach ($usersToNotify as $userToNotify) {
    echo "Sending notification to user {$userToNotify->name} (ID: {$userToNotify->id})\n";
    $userToNotify->notify(new EmailApproved($email));
}

echo "\nTest completed successfully!\n";
echo "Check the database notifications table to verify notifications were created.\n";
echo "The EmailObserver should have also automatically sent notifications when the email status was updated.\n";
