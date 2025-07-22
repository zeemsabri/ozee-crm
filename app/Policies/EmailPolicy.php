<?php

namespace App\Policies;

use App\Models\Email;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EmailPolicy
{
    /**
     * Determine whether the user can view any emails (list emails).
     * Super Admin, Manager, Employee can view all. Contractor views only their own.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_emails');
    }

    /**
     * Determine whether the user can view a specific email.
     * Super Admin, Manager, Employee can view any email. Contractor views only their own.
     */
    public function view(User $user, Email $email): bool
    {
        if ($user->hasPermission('view_emails')) {
            // For contractors, additional check if they are the sender
            if ($user->isContractor()) {
                return $user->id === $email->sender_id;
            }
            return true; // Managers, Employees, and Super Admins can view all emails
        }
        return false;
    }

    /**
     * Determine whether the user can create emails.
     * All authenticated users can create emails (for submission).
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('compose_emails');
    }

    /**
     * Determine whether the user can update an email.
     * Contractors can update their own draft or rejected emails. Super Admin, Manager can update any.
     */
    public function update(User $user, Email $email): bool
    {
        // Managers and Super Admins with edit permission can update any email
        if ($user->hasPermission('edit_emails') && ($user->isSuperAdmin() || $user->isManager())) {
            return true;
        }

        // Users can update their own draft or rejected emails if they have compose permission
        return $user->hasPermission('compose_emails') &&
               $user->id === $email->sender_id &&
               in_array($email->status, ['draft', 'rejected']);
    }

    /**
     * Determine whether the user can resubmit a rejected email.
     * Only the sender can resubmit their own rejected email.
     */
    public function resubmit(User $user, Email $email): bool
    {
        return $user->hasPermission('resubmit_emails') &&
               $user->id === $email->sender_id &&
               $email->status === 'rejected';
    }

    /**
     * Determine whether the user can approve an email.
     * Only Super Admin and Manager can approve emails.
     */
    public function approve(User $user, Email $email): bool
    {
        return $user->hasPermission('approve_emails');
    }

    /**
     * Determine whether the user can edit and approve an email in one step.
     * Only Super Admin and Manager can edit and approve.
     */
    public function editAndApprove(User $user, Email $email): bool
    {
        return $user->hasPermission('approve_emails') && $user->hasPermission('edit_emails');
    }

    /**
     * Determine whether the user can reject an email.
     * Only Super Admin and Manager can reject emails.
     */
    public function reject(User $user, Email $email): bool
    {
        return $user->hasPermission('approve_emails');
    }

    /**
     * Determine whether the user can delete an email.
     * Not implemented for MVP.
     */
    public function delete(User $user, Email $email): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore or force delete an email.
     * Not implemented for MVP.
     */
    public function restore(User $user, Email $email): bool
    {
        return false;
    }

    public function forceDelete(User $user, Email $email): bool
    {
        return false;
    }
}
