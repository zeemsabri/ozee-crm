<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Client;
use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use App\Models\Role; // Assuming Role model is accessible

trait HasProjectPermissions
{
    /**
     * Check if user has general access to the project.
     * Super Admin and Manager can view all projects.
     * Employees and Contractors can only view projects they're assigned to.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canAccessProject(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin() || $user->isManager()) {
            return true;
        }

        if ($user->isEmployee() || $user->isContractor()) {
            return $project->users->contains($user->id);
        }

        return false;
    }

    /**
     * Get the project-specific role for a user.
     *
     * @param User $user
     * @param Project $project
     * @return Role|null
     */
    protected function getUserProjectRole(User|Client $user, Project $project, $permission = true): ?Role
    {
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            if($permission) {
                return Role::with('permissions')->find($projectUser->pivot->role_id);
            }
            return Role::find($projectUser->pivot->role_id);

        }
        return null;
    }

    protected function getProjectRoleName(User|Client $user, Project $project)
    {
        return $this->getUserProjectRole($user, $project, false)?->name ?? 'Staff';
    }

    /**
     * Check if user has permission to view client contacts.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canViewClientContacts(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_client_contacts')) {
            return true;
        }

        return $user->hasPermission('view_client_contacts');
    }

    protected function canViewClients(User $user, Project $project) : bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_clients')) {
            return true;
        }

        return $user->hasPermission('view_clients');
    }

    /**
     * Check if user has permission to view client financial.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canViewClientFinancial(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_client_financial')) {
            return true;
        }

        return $user->hasPermission('view_client_financial');
    }

    /**
     * Check if user has permission to view users (team members).
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canViewUsers(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_users')) {
            return true;
        }

        return $user->hasPermission('view_users');
    }

    /**
     * Check if user has permission to view project services and payments.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canViewProjectServicesAndPayments(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_financial')) {
            return true;
        }

        return $user->hasPermission('view_project_financial');
    }

    /**
     * Check if user has permission to manage project services and payments.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canManageProjectServicesAndPayments(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_financial')) {
            return true;
        }

        return $user->hasPermission('manage_project_financial');
    }

    /**
     * Check if user has permission to view project transactions.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canViewProjectTransactions(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_transactions')) {
            return true;
        }

        return $user->hasPermission('view_project_transactions');
    }

    /**
     * Check if user has permission to manage project expenses.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canManageProjectExpenses(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_expenses')) {
            return true;
        }

        return $user->hasPermission('manage_project_expenses');
    }

    /**
     * Check if user has permission to manage project income.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canManageProjectIncome(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_income')) {
            return true;
        }

        return $user->hasPermission('manage_project_income');
    }

    /**
     * Check if user has permission to view project documents.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canViewProjectDocuments(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_documents')) {
            return true;
        }

        return $user->hasPermission('view_project_documents');
    }

    /**
     * Check if user has permission to view project notes.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canViewProjectNotes(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_notes')) {
            return true;
        }

        return $user->hasPermission('view_project_notes');
    }

    /**
     * Check if user has permission to add project notes.
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    protected function canAddProjectNotes(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'add_project_notes')) {
            return true;
        }

        return $user->hasPermission('add_project_notes');
    }

    /**
     * Check if user has permission to manage projects (general update/delete).
     *
     * @param User $user
     * @param Project $project
     * @return bool
     */
    public function canManageProjects(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $projectRole = $this->getUserProjectRole($user, $project);
        if ($projectRole && $projectRole->permissions->contains('slug', 'manage_projects')) {
            return true;
        }

        return $user->hasPermission('manage_projects');
    }
}
