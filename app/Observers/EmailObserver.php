<?php

namespace App\Observers;

use App\Models\Email;
use App\Notifications\EmailApprovalRequired;
use App\Notifications\EmailApproved;
use App\Helpers\PermissionHelper;
use Illuminate\Support\Facades\Log;

class EmailObserver
{
    /**
     * Handle the Email "created" event.
     *
     * @param  \App\Models\Email  $email
     * @return void
     */
    public function created(Email $email)
    {
        // Send notifications if email status is pending_approval and type is sent
        if ($email->status === 'pending_approval' && $email->type === 'sent') {
            // Find users with approve_emails permission
            $projectId = $email->conversation->project_id;
            $usersToNotify = PermissionHelper::getAllUsersWithPermission('approve_emails', $projectId);

            // Send notification to each user
            foreach ($usersToNotify as $userToNotify) {
                $userToNotify->notify(new EmailApprovalRequired($email));
            }

            Log::info('Email approval notifications sent', [
                'email_id' => $email->id,
                'project_id' => $projectId,
                'notification_count' => $usersToNotify->count()
            ]);
        }

        // Send notifications if email status is pending_approval_received and type is received
        if ($email->status === 'pending_approval_received' && $email->type === 'received') {
            $projectId = $email->conversation->project_id;

            // Get users with approve_received_emails permission for this project
            $usersToNotify = PermissionHelper::getAllUsersWithPermission('approve_received_emails', $projectId);

            // Send notification to each user
            foreach ($usersToNotify as $userToNotify) {
                $userToNotify->notify(new EmailApprovalRequired($email));
            }

            Log::info('Email approval notifications sent for received email', [
                'email_id' => $email->id,
                'project_id' => $projectId,
                'notification_count' => $usersToNotify->count()
            ]);
        }
    }

    /**
     * Handle the Email "updated" event.
     *
     * @param  \App\Models\Email  $email
     * @return void
     */
    public function updated(Email $email)
    {
        // Check if the email status was changed to 'approved'
        if ($email->isDirty('status') && $email->status === 'approved') {
            $projectId = $email->conversation->project_id;

            // Get all users with view_emails permission for this project
            $usersToNotify = PermissionHelper::getAllUsersWithPermission('view_emails', $projectId);

            // Send notification to each user
            foreach ($usersToNotify as $userToNotify) {
                $userToNotify->notify(new EmailApproved($email));
            }

            // --- NEW LOGIC TO MARK OLD NOTIFICATIONS AS READ ---
            // This ensures they don't reappear on refresh for any user.
            $correlationId = 'email_approval_' . $email->id;

            // Find all users who would have received the original notification
            $usersWithApprovalPermission = PermissionHelper::getAllUsersWithPermission('approve_emails', $projectId)
                ->merge(PermissionHelper::getAllUsersWithPermission('approve_received_emails', $projectId));

            foreach ($usersWithApprovalPermission as $user) {
                $user->unreadNotifications
                    ->where('data.correlation_id', $correlationId)
                    ->where('data.task_type', 'email_approval')
                    ->markAsRead();
            }
        }
    }
}
