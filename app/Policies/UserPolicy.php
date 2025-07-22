<?php

namespace App\Policies;

use App\Models\User; // Make sure to import the User model
use Illuminate\Auth\Access\Response; // Required for explicit responses (though bool is also fine)

class UserPolicy
{
    // The Gate::before method in AuthServiceProvider.php already handles Super Admin bypass.
    // So, for all methods below, you can assume $currentUser is NOT a Super Admin
    // unless you want to explicitly re-check for Super Admin within the method.

    /**
     * Determine whether the user can view any models (list users).
     * Super Admin, Manager, Employee can view all users. Contractor views self only.
     * Note: The actual filtering for Contractors to see only themselves is done in UserController@index for now,
     * but this policy allows them to access the list endpoint.
     */
    public function viewAny(User $currentUser): bool
    {
        // Check if user has permission to view users
        return $currentUser->hasPermission('view_users');
        // Super Admin is covered by Gate::before, so no need for explicit check here.
    }

    /**
     * Determine whether the user can view the model (show a specific user).
     * Super Admin, Manager, Employee can view any user. Contractor views self only.
     */
    public function view(User $currentUser, User $user): bool
    {
        // Check if user has permission to view users
        if ($currentUser->hasPermission('view_users')) {
            return true;
        }

        // Users can always view their own profile
        return $currentUser->id === $user->id;
        // Super Admin is covered by Gate::before.
    }

    /**
     * Determine whether the user can create models.
     * Super Admin can create any user. Manager can create Employee/Contractor.
     */
    public function create(User $currentUser): bool
    {
        // Check if user has permission to create users
        return $currentUser->hasPermission('create_users');
        // Super Admin is covered by Gate::before.
    }

    /**
     * Determine whether the user can update the model.
     * Super Admin can update any user. Manager can update Employee/Contractor roles. Users can update their own profile (excluding role for non-admins).
     */
    public function update(User $currentUser, User $user): bool
    {
        // Check if user has permission to edit users
        if ($currentUser->hasPermission('edit_users')) {
            // Managers should only be able to edit employees and contractors
            if ($currentUser->isManager()) {
                return ($user->isEmployee() || $user->isContractor());
            }
            return true;
        }

        // A user can update their own profile.
        return $currentUser->id === $user->id;
        // Super Admin is covered by Gate::before.
    }

    /**
     * Determine whether the user can delete the model.
     * Only Super Admin can delete users.
     */
    public function delete(User $currentUser, User $user): bool
    {
        // Check if user has permission to delete users
        return $currentUser->hasPermission('delete_users');
        // Super Admin is covered by Gate::before, and self-deletion is prevented in controller.
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $currentUser, User $user): bool
    {
        return false; // Not implementing restore for MVP
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $currentUser, User $user): bool
    {
        return false; // Not implementing force delete for MVP
    }
}
