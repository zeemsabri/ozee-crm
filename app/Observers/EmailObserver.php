<?php

namespace App\Observers;

use App\Models\Email;
use App\Notifications\EmailApprovalRequired;
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
}
