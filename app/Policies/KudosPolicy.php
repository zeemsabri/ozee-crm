<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Kudo;
use Illuminate\Auth\Access\Response;

class KudosPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Users can view kudos if they have either view_kudos or view_own_kudos permission
        return $user->hasPermission('view_kudos') || $user->hasPermission('view_own_kudos');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Kudo $kudos): bool
    {
        // Users with view_all_kudos can view any kudos
        if ($user->hasPermission('view_all_kudos')) {
            return true;
        }

        // Users with view_kudos can view kudos
        if ($user->hasPermission('view_kudos')) {
            return true;
        }

        // Users with view_own_kudos can only view their own kudos (sent or received)
        if ($user->hasPermission('view_own_kudos')) {
            return $kudos->sender_id === $user->id || $kudos->recipient_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_kudos');
    }

    /**
     * Determine whether the user can update the model.
     * Only the sender can update their own kudos before it's approved
     */
    public function update(User $user, Kudo $kudos): bool
    {
        // Only the sender can update their own kudos and only if it's not yet approved
        return $kudos->sender_id === $user->id && !$kudos->is_approved;
    }

    /**
     * Determine whether the user can delete the model.
     * Only the sender can delete their own kudos before it's approved
     */
    public function delete(User $user, Kudo $kudos): bool
    {
        // Only the sender can delete their own kudos and only if it's not yet approved
        return $kudos->sender_id === $user->id && !$kudos->is_approved;
    }

    /**
     * Determine whether the user can approve or reject kudos.
     */
    public function approve(User $user, Kudo $kudos): bool
    {
        return $user->hasPermission('approve_kudos');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Kudo $kudos): bool
    {
        return false; // Not implemented for MVP
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Kudo $kudos): bool
    {
        return false; // Not implemented for MVP
    }
}
