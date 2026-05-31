<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_project_invoices');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('view_project_invoices');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_project_invoices');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('edit_project_invoices');
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('approve_project_invoices');
    }

    /**
     * Determine whether the user can void the model.
     */
    public function void(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('void_project_invoices');
    }
}
