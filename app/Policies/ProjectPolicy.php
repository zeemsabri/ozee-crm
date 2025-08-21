<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     * All roles can view projects, but access to specific projects will be handled by 'view' method.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can at least see a list (filtered by 'view' for contractors)
    }

    /**
     * Determine whether the user can view the model.
     * Super Admin, Manager, Employee can view any project.
     * Contractor can view if they are assigned to the project.
     */
    public function view(User $user, Project $project): bool
    {
        // Check if user has permission to view projects
        if ($user->hasPermission('view_projects')) {
            // For contractors, additional check if they are assigned to the project
            if ($user->isContractor()) {
                return $project->users->contains($user->id);
            }
            return true; // Managers, Employees, and Super Admins can view all projects
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     * Only Super Admin and Manager can create projects.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_projects');
    }


    public function getClientsAndUsers(User $user, Project $project)
    {
        // The user must be able to add expendables OR view the project.
        return $user->can('addExpendables', $project) || $user->can('viewProject', $project);
    }

    /**
     * Determine whether the user can update the model.
     * Only Super Admin and Manager can update projects.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('edit_projects');
    }

    /**
     * Determine whether the user can delete the model.
     * Only Super Admin and Manager can delete projects.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('delete_projects');
    }

    // Policies for attaching/detaching users to projects
    public function attachAnyUser(User $user, Project $project): bool
    {
        // Check if user has global permission
        if ($user->hasPermission('manage_project_users')) {
            return true;
        }

        // Check project-specific permission
        return $this->userHasProjectPermission($user, 'manage_project_users', $project->id);
    }

    public function detachAnyUser(User $user, Project $project): bool
    {
        // Check if user has global permission
        if ($user->hasPermission('manage_project_users')) {
            return true;
        }

        // Check project-specific permission
        return $this->userHasProjectPermission($user, 'manage_project_users', $project->id);
    }

    public function attachAnyClient(User $user, Project $project): bool
    {
        // Check if user has global permission
        if ($user->hasPermission('manage_project_clients')) {
            return true;
        }

        // Check project-specific permission
        return $this->userHasProjectPermission($user, 'manage_project_clients', $project->id);
    }

    /**
     * Check if the user has a project-specific permission
     *
     * @param  \App\Models\User  $user
     * @param  string  $permission
     * @param  int  $projectId
     * @return bool
     */
    private function userHasProjectPermission($user, $permission, $projectId)
    {
        // Load the user's project with the pivot data
        $project = Project::with(['users' => function ($query) use ($user) {
            $query->where('users.id', $user->id)->withPivot('role_id');
        }])->find($projectId);

        if (!$project) {
            return false;
        }

        $userInProject = $project->users->first();

        if (!$userInProject || !isset($userInProject->pivot->role_id)) {
            return false;
        }

        // Load the project-specific role with permissions
        $projectRole = Role::with('permissions')->find($userInProject->pivot->role_id);

        if (!$projectRole) {
            return false;
        }

        // Check if the project-specific role has the permission
        return $projectRole->permissions->contains('slug', $permission);
    }

    public function viewProject($user, $project)
    {
        if($this->userHasProjectPermission($user, 'view_projects', $project->id)) {
            return true;
        }

        return $user->hasPermission('manage_projects');
    }

    /**
     * Determine whether the user can add notes to the project.
     * Users with add_project_notes permission can add notes.
     */
    public function addNotes(User $user, Project $project): bool
    {
        // Check if user has global permission
        if ($user->hasPermission('add_project_notes')) {
            return true;
        }

        // Check project-specific permission
        return $this->userHasProjectPermission($user, 'add_project_notes', $project->id);
    }

    public function viewNotes(User $user, Project $project): bool
    {
        // Check if user has global permission
        if ($user->hasPermission('view_project_notes')) {
            return true;
        }

        // Check project-specific permission
        return $this->userHasProjectPermission($user, 'view_project_notes', $project->id);
    }

    // restore and forceDelete can be left as false/not implemented for MVP
    public function restore(User $user, Project $project): bool { return false; }
    public function forceDelete(User $user, Project $project): bool { return false; }

    public function addExpendables(User $user, Project $project)
    {

        if($this->userHasProjectPermission($user, 'add_expendables', $project->id)) {
            return true;
        }

        return $user->hasPermission('add_expendables');
    }


    public function manageTransactions(User $user, Project $project)
    {
        // Check if user has global permission
        if ($user->hasPermission('manage_project_financial')) {
            return true;
        }

        // Check project-specific permission
        return $this->userHasProjectPermission($user, 'manage_project_financial', $project->id);
    }



}
