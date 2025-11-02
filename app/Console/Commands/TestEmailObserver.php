<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Email;
use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestEmailObserver extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email-observer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Email observer notification system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting email observer notification test...\n");

        // 1. Create a test project
        $this->info('Creating test project...');
        $project = Project::create([
            'name' => 'Test Project for Email Observer',
            'status' => 'active',
        ]);
        $this->info("Created project with ID: {$project->id}");

        // 2. Create test users with different permissions
        $this->info("\nCreating test users...");

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

        $this->info("Created users with IDs: {$globalApproverUser->id}, {$projectApproverUser->id}, {$receivedApproverUser->id}");

        // 3. Assign permissions to users
        $this->info("\nAssigning permissions to users...");

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

        $this->info('Permissions assigned');

        // 4. Create a test conversation
        $this->info("\nCreating test conversation...");
        $conversation = Conversation::create([
            'subject' => 'Test Conversation for Email Observer',
            'project_id' => $project->id,
            'last_activity_at' => now(),
        ]);
        $this->info("Created conversation with ID: {$conversation->id}");

        // 5. Create test emails with different statuses and types
        $this->info("\nCreating test emails...");

        // Create a pending_approval + sent email
        $this->info('Creating sent email pending approval...');
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
        $this->info("Created sent email with ID: {$sentEmail->id}");

        // Check if notifications were created
        $sentNotifications = DB::table('notifications')
            ->where('data', 'like', '%"email_id":'.$sentEmail->id.'%')
            ->get();

        $this->info('Found '.$sentNotifications->count().' notifications for sent email');

        // Create a pending_approval_received + received email
        $this->info("\nCreating received email pending approval...");
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
        $this->info("Created received email with ID: {$receivedEmail->id}");

        // Check if notifications were created
        $receivedNotifications = DB::table('notifications')
            ->where('data', 'like', '%"email_id":'.$receivedEmail->id.'%')
            ->get();

        $this->info('Found '.$receivedNotifications->count().' notifications for received email');

        $this->info("\nTest completed successfully!");
        $this->info('Check the database notifications table to verify notifications were created.');
    }
}
