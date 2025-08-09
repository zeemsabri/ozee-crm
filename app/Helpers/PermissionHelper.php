<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class PermissionHelper
{
    /**
     * Get users with a specific global permission
     *
     * @param string $permissionSlug
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUsersWithGlobalPermission(string $permissionSlug)
    {
        return User::whereHas('role', function ($query) use ($permissionSlug) {
            $query->whereHas('permissions', function ($q) use ($permissionSlug) {
                $q->where('slug', $permissionSlug);
            });
        })->get();
    }

    /**
     * Get users with a specific project permission for a given project
     *
     * @param string $permissionSlug
     * @param int $projectId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUsersWithProjectPermission(string $permissionSlug, int $projectId)
    {
        // Get all users associated with the project
        $projectUsers = User::whereHas('projects', function ($query) use ($projectId) {
            $query->where('projects.id', $projectId);
        })->get();

        // Filter users who have the specified permission through their project role
        return $projectUsers->filter(function ($user) use ($permissionSlug, $projectId) {
            // Get the user's role for this project
            $roleId = $user->getRoleForProject($projectId);
            if (!$roleId) {
                return false;
            }

            // Check if the role has the specified permission
            $role = \App\Models\Role::find($roleId);
            return $role && $role->permissions->contains('slug', $permissionSlug);
        });
    }

    /**
     * Get all users with a specific permission (either global or project-specific)
     *
     * @param string $permissionSlug
     * @param int|null $projectId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllUsersWithPermission(string $permissionSlug, ?int $projectId = null)
    {
        // Get users with global permission
        $usersWithGlobalPermission = self::getUsersWithGlobalPermission($permissionSlug);

        // If project ID is provided, also get users with project-specific permission
        if ($projectId) {
            $usersWithProjectPermission = self::getUsersWithProjectPermission($permissionSlug, $projectId);

            // Merge the collections and remove duplicates
            return $usersWithGlobalPermission->merge($usersWithProjectPermission)->unique('id');
        }

        return $usersWithGlobalPermission;
    }
}
