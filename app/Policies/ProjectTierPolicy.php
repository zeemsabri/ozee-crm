<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ProjectTier;
use Illuminate\Auth\Access\Response;

class ProjectTierPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_project_tiers');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProjectTier $projectTier): bool
    {
        return $user->hasPermission('view_project_tiers');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_project_tiers');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProjectTier $projectTier): bool
    {
        return $user->hasPermission('edit_project_tiers');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProjectTier $projectTier): bool
    {
        return $user->hasPermission('delete_project_tiers');
    }

    /**
     * Determine whether the user can assign tiers to projects.
     */
    public function assign(User $user): bool
    {
        return $user->hasPermission('assign_project_tiers');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProjectTier $projectTier): bool
    {
        return false; // Not implemented for MVP
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProjectTier $projectTier): bool
    {
        return false; // Not implemented for MVP
    }
}
