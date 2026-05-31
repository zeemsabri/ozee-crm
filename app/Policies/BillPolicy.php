<?php

namespace App\Policies;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BillPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_project_bills');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Bill $bill): bool
    {
        return $user->hasPermission('view_project_bills');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_project_bills');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Bill $bill): bool
    {
        return $user->hasPermission('edit_project_bills');
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, Bill $bill): bool
    {
        return $user->hasPermission('approve_project_bills');
    }

    /**
     * Determine whether the user can void the model.
     */
    public function void(User $user, Bill $bill): bool
    {
        return $user->hasPermission('void_project_bills');
    }
}
