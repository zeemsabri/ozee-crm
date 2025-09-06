<?php

namespace App\Observers;

use App\Models\Email;
use App\Models\Lead;
use App\Notifications\EmailApprovalRequired;
use App\Notifications\EmailApproved;
use App\Helpers\PermissionHelper;
use Illuminate\Support\Facades\Log;

class EmailObserver
{
    /**
     * Handle the Email "created" event.
     *
     * With the new AI workflow, emails are created as 'drafts'.
     * No notifications are needed at this stage. The 'updated' event
     * will handle all notification logic when the status changes.
     *
     * @param  \App\Models\Email  $email
     * @return void
     */
    public function created(Email $email)
    {
        // Update project last_email_received when a received email is created.
        if ($email->type === Email::TYPE_RECEIVED) {
            $project = optional($email->conversation)->project;
            if ($project) {
                $project->update(['last_email_received' => now()]);
            }
        }
    }

    /**
     * Handle the Email "updated" event.
     * This is now the primary location for notification logic.
     *
     * @param  \App\Models\Email  $email
     * @return void
     */
    public function updated(Email $email)
    {
        // Guard clause: Only proceed if the 'status' attribute was actually changed.
        if (!$email->isDirty('status')) {
            return;
        }

        $newStatus = $email->status;
        $originalStatus = $email->getOriginal('status');
        $projectId = optional($email->conversation)->project_id;

        if (!$projectId) {
            return; // Cannot send notifications without a project context.
        }

        // --- SCENARIO 1: AI has flagged an email for manual approval ---
        // The status changes from 'draft' to 'pending_approval'.
        if ($newStatus === Email::STATUS_PENDING_APPROVAL && $originalStatus === Email::STATUS_DRAFT) {
            $this->notifyAdminsForApproval($email, $projectId);
        }

        // --- SCENARIO 2: An email has been successfully sent ---
        if ($newStatus === Email::STATUS_SENT) {
            // ** REFINED LOGIC **
            // Only send the "Email Approved" notification if it was MANUALLY approved.
            // If the AI auto-approved it (draft -> sent), no notification is needed.
            if ($originalStatus === Email::STATUS_PENDING_APPROVAL) {
                $this->notifyUsersOfSentEmail($email, $projectId);
            }

            // Always mark any outstanding approval notifications as read.
            // This handles the case where one admin approves it before another sees the task.
            $this->markApprovalNotificationsAsRead($email, $projectId);
            $this->updateProjectAndLeadTimestamps($email);
        }
    }

    /**
     * Notifies users with approval permissions that an email needs their attention.
     */
    private function notifyAdminsForApproval(Email $email, int $projectId): void
    {
        $permission = $email->type === Email::TYPE_RECEIVED ? Email::APPROVE_RECEIVED_EMAILS_PERMISSION : Email::APPROVE_SENT_EMAIL_PERMISSION;
        $usersToNotify = PermissionHelper::getAllUsersWithPermission($permission, $projectId);

        foreach ($usersToNotify as $user) {
            $user->notify(new EmailApprovalRequired($email));
        }
    }

    /**
     * Notifies relevant users that an email has been approved and sent.
     */
    private function notifyUsersOfSentEmail(Email $email, int $projectId): void
    {
        $approverPermission = $email->type === Email::TYPE_RECEIVED ? Email::APPROVE_RECEIVED_EMAILS_PERMISSION : Email::APPROVE_SENT_EMAIL_PERMISSION;

        // Get users with approval power
        $usersWithApprovalPermission = PermissionHelper::getAllUsersWithPermission($approverPermission, $projectId);

        // Notify them with the "is_approver" flag
        foreach ($usersWithApprovalPermission as $userToNotify) {
            $userToNotify->notify(new EmailApproved($email, true));
        }

        // Get users with general view permissions
        $usersWithViewPermission = PermissionHelper::getAllUsersWithPermission(Email::VIEW_EMAIL_PERMISSION, $projectId);

        // Exclude the approvers so they don't get two notifications
        $usersToNotify = $usersWithViewPermission->diff($usersWithApprovalPermission);

        foreach ($usersToNotify as $userToNotify) {
            $userToNotify->notify(new EmailApproved($email));
        }
    }

    /**
     * Finds and marks all "EmailApprovalRequired" notifications for this email as read.
     * This is the key to solving the persistent notification problem.
     */
    private function markApprovalNotificationsAsRead(Email $email, int $projectId): void
    {
        $permission = $email->type === Email::TYPE_RECEIVED ? Email::APPROVE_RECEIVED_EMAILS_PERMISSION : Email::APPROVE_SENT_EMAIL_PERMISSION;
        $usersWhoReceivedNotification = PermissionHelper::getAllUsersWithPermission($permission, $projectId);

        foreach ($usersWhoReceivedNotification as $user) {
            // Find unread notifications related to THIS specific email approval task
            $user->unreadNotifications
                ->where('type', EmailApprovalRequired::class)
                ->where('data.email.id', $email->id)
                ->markAsRead();
        }

        Log::info('Marked approval notifications as read for email.', ['email_id' => $email->id]);
    }

    /**
     * Updates timestamps on the Project and Lead models after an email is sent.
     */
    private function updateProjectAndLeadTimestamps(Email $email): void
    {
        if ($email->type === Email::TYPE_SENT) {
            // Update project's last_email_sent timestamp
            optional($email->conversation)->project?->update(['last_email_sent' => now()]);

            // If the conversation is with a Lead, update their contacted_at timestamp
            if ($email->conversation?->conversable instanceof Lead) {
                $email->conversation->conversable->update(['contacted_at' => now()]);
            }
        }
    }
}

