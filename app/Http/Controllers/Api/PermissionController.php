<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    /**
     * Get the current user's global permissions
     * This endpoint returns all permissions the authenticated user has at the application level
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPermissions()
    {
        try {
            $user = Auth::user();

            // Load the user's role with permissions
            $user->load('role.permissions');

            // Create an array to hold all permissions
            $permissions = [];

            // Add permissions from the user's role
            if ($user->role && $user->role->permissions) {
                foreach ($user->role->permissions as $permission) {
                    $permissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'category' => $permission->category,
                        'source' => 'application',
                    ];
                }
            }

            $projectIds = $user->projects->pluck('id')->toArray();
            if ($expendablePermission = $user->hasProjectPermissionOnAnyRole($projectIds, 'add_expendables')) {
                $permissions[] = $expendablePermission;
            }

            return response()->json([
                'permissions' => $permissions,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'slug' => $user->role->slug,
                    'type' => $user->role->type,
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user permissions: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch user permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the current user's permissions for a specific project
     * This endpoint returns the user's project-specific role and permissions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserProjectPermissions(Project $project)
    {
        try {
            $user = Auth::user();

            // Check if user has access to this project
            if (! $user->isSuperAdmin() && ! $user->isManager()) {
                if (! $project->users->contains($user->id)) {
                    return response()->json([
                        'message' => 'Unauthorized. You do not have access to this project.',
                    ], 403);
                }
            }

            // Load the user's global role with permissions
            $user->load(['role.permissions']);

            // Create response object
            $response = [
                'project_id' => $project->id,
                'global_role' => null,
                'project_role' => null,
                'permissions' => [],
            ];

            // Add global role information
            if ($user->role) {
                $response['global_role'] = [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'slug' => $user->role->slug,
                    'type' => $user->role->type,
                ];

                // Add global permissions
                $globalPermissions = [];
                foreach ($user->role->permissions as $permission) {
                    $globalPermissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'category' => $permission->category,
                        'source' => 'application',
                    ];
                }

                $response['permissions'] = $globalPermissions;
            }

            // Get the user's project-specific role
            $project->load(['users' => function ($query) use ($user) {
                $query->where('users.id', $user->id)->withPivot('role_id');
            }]);

            $userInProject = $project->users->first();

            if ($userInProject && isset($userInProject->pivot->role_id)) {
                $projectRole = Role::with('permissions')->find($userInProject->pivot->role_id);

                if ($projectRole) {
                    // Add project role information
                    $response['project_role'] = [
                        'id' => $projectRole->id,
                        'name' => $projectRole->name,
                        'slug' => $projectRole->slug,
                        'type' => $projectRole->type,
                    ];

                    // Add project-specific permissions (these override global permissions)
                    $projectPermissions = [];
                    foreach ($projectRole->permissions as $permission) {
                        $projectPermissions[] = [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'slug' => $permission->slug,
                            'category' => $permission->category,
                            'source' => 'project',
                        ];
                    }

                    // Replace global permissions with project permissions
                    // This ensures project permissions override global ones
                    $response['permissions'] = $projectPermissions;
                }
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error fetching user project permissions: '.$e->getMessage(), [
                'user_id' => Auth::id(),
                'project_id' => $project->id,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch user project permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all available permissions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllPermissions()
    {
        try {
            $permissions = Permission::all();

            // Group permissions by category
            $groupedPermissions = $permissions->groupBy('category');

            return response()->json($groupedPermissions);
        } catch (\Exception $e) {
            Log::error('Error fetching all permissions: '.$e->getMessage(), [
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
