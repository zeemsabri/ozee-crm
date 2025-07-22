<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Client;
use Illuminate\Auth\Access\Response;

class ClientPolicy
{
    /**
     * Determine whether the user can view any models.
     * Manager, Employee, Super Admin can view all. Contractor can view clients related to their projects.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_clients');
    }

    /**
     * Determine whether the user can view the model.
     * Manager, Employee, Super Admin can view any client.
     * Contractor can view if the client is associated with one of their projects.
     */
    public function view(User $user, Client $client): bool
    {
        // Check if user has permission to view clients
        if ($user->hasPermission('view_clients')) {
            // For contractors, additional check if the client is related to their projects
            if ($user->isContractor()) {
                return $user->projects()->where('client_id', $client->id)->exists();
            }
            return true; // Managers, Employees, and Super Admins can view all clients
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     * Only Super Admin and Manager can create clients.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_clients');
    }

    /**
     * Determine whether the user can update the model.
     * Only Super Admin and Manager can update clients.
     */
    public function update(User $user, Client $client): bool
    {
        return $user->hasPermission('edit_clients');
    }

    /**
     * Determine whether the user can delete the model.
     * Only Super Admin and Manager can delete clients.
     */
    public function delete(User $user, Client $client): bool
    {
        return $user->hasPermission('delete_clients');
    }

    // Restore and forceDelete methods are less common for MVP, can be left as false/not implemented
    public function restore(User $user, Client $client): bool { return false; }
    public function forceDelete(User $user, Client $client): bool { return false; }
}
