<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Kudo;

class KudosPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_kudos') || $user->hasPermission('view_own_kudos');
    }

    public function view(User $user, Kudo $kudos): bool
    {
        if ($user->hasPermission('view_all_kudos') || $user->hasPermission('view_kudos')) {
            return true;
        }

        if ($user->hasPermission('view_own_kudos')) {
            return $kudos->sender_id === $user->id || $kudos->recipient_id === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
        return $user->hasPermission('create_kudos');
    }

    public function update(User $user, Kudo $kudos): bool
    {
        return $kudos->sender_id === $user->id && !$kudos->is_approved;
    }

    public function delete(User $user, Kudo $kudos): bool
    {
        return $kudos->sender_id === $user->id && !$kudos->is_approved;
    }

    public function approve(User $user, Kudo $kudos): bool
    {
        // Global permission OR project-specific permission on this kudo's project
        return $user->hasPermission('approve_kudos') || $this->userHasProjectPermission($user, 'approve_kudos', $kudos->project_id);
    }

    private function userHasProjectPermission(User $user, string|array $permission, $projectId): bool
    {
        $project = \App\Models\Project::with(['users' => function ($query) use ($user) {
            $query->where('users.id', $user->id)->withPivot('role_id');
        }])->find($projectId);

        if (!$project || !$userInProject = $project->users->first()) {
            return false;
        }

        $projectRole = \App\Models\Role::with('permissions')->find($userInProject->pivot->role_id);
        if (!$projectRole) {
            return false;
        }

        if (is_array($permission)) {
            return (bool) $projectRole->permissions->whereIn('slug', $permission)->count();
        }

        return $projectRole->permissions->contains('slug', $permission);
    }

    public function restore(User $user, Kudo $kudos): bool
    {
        return false;
    }

    public function forceDelete(User $user, Kudo $kudos): bool
    {
        return false;
    }
}
