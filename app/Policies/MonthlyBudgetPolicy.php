<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MonthlyBudget;
use Illuminate\Auth\Access\Response;

class MonthlyBudgetPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_monthly_budgets');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MonthlyBudget $monthlyBudget): bool
    {
        return $user->hasPermission('view_monthly_budgets');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_monthly_budgets');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MonthlyBudget $monthlyBudget): bool
    {
        return $user->hasPermission('manage_monthly_budgets');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MonthlyBudget $monthlyBudget): bool
    {
        return $user->hasPermission('manage_monthly_budgets');
    }

    /**
     * Determine whether the user can view points ledger.
     */
    public function viewPointsLedger(User $user): bool
    {
        return $user->hasPermission('view_points_ledger');
    }

    /**
     * Determine whether the user can manage points.
     */
    public function managePoints(User $user): bool
    {
        return $user->hasPermission('manage_points');
    }

    /**
     * Determine whether the user can view monthly points.
     */
    public function viewMonthlyPoints(User $user): bool
    {
        return $user->hasPermission('view_monthly_points');
    }

    /**
     * Determine whether the user can view their own points.
     */
    public function viewOwnPoints(User $user): bool
    {
        return $user->hasPermission('view_own_points');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MonthlyBudget $monthlyBudget): bool
    {
        return false; // Not implemented for MVP
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MonthlyBudget $monthlyBudget): bool
    {
        return false; // Not implemented for MVP
    }
}
