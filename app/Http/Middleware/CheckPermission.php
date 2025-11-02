<?php

namespace App\Http\Middleware;

use App\Exceptions\PermissionDeniedException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = Auth::user();

        $routeName = $request->route()->getName();

        if (! $user) {
            Log::warning('Unauthenticated user attempted to access route requiring permission', [
                'permission' => $permission,
                'route' => $request->path(),
            ]);

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Super admins have all permissions
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if ($routeName === 'roles.index' && $request->input('type')) {
            return $next($request);
        }

        // Check if this is a project-specific route
        $projectId = $this->getProjectIdFromRequest($request);

        if ($projectId) {
            // Check project-specific permissions
            if ($this->userHasProjectPermission($user, $permission, $projectId)) {
                return $next($request);
            }
        } else {
            // Check global permissions
            if ($this->userHasGlobalPermission($user, $permission)) {
                return $next($request);
            }
        }

        Log::warning('User attempted to access route without required permission', [
            'user_id' => $user->id,
            'permission' => $permission,
            'route' => $request->path(),
            'project_id' => $projectId,
        ]);

        throw new PermissionDeniedException(
            $permission,
            $projectId,
            'Forbidden. You do not have the required permission: '.$permission
        );
    }

    /**
     * Get the project ID from the request if it exists
     *
     * @return int|null
     */
    private function getProjectIdFromRequest(Request $request)
    {
        // Check route parameters for project ID
        if ($request->route('project')) {
            return $request->route('project')->id;
        }

        // Check request parameters for project ID
        if ($request->has('project_id')) {
            return $request->input('project_id');
        }

        return null;
    }

    /**
     * Check if the user has a global permission
     *
     * @param  \App\Models\User  $user
     * @param  string  $permission
     * @return bool
     */
    private function userHasGlobalPermission($user, $permission)
    {
        // Load the user's role with permissions if not already loaded
        if (! $user->relationLoaded('role') || ($user->role && ! $user->role->relationLoaded('permissions'))) {
            $user->load('role.permissions');
        }

        // Check if the user's role has the permission
        if ($user->role && $user->role->permissions) {
            return $user->role->permissions->contains('slug', $permission);
        }

        return false;
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
        // First check if the user has the global permission
        if ($this->userHasGlobalPermission($user, $permission)) {
            return true;
        }

        // Load the user's project with the pivot data
        $project = \App\Models\Project::with(['users' => function ($query) use ($user) {
            $query->where('users.id', $user->id)->withPivot('role_id');
        }])->find($projectId);

        if (! $project) {
            return false;
        }

        $userInProject = $project->users->first();

        if (! $userInProject || ! isset($userInProject->pivot->role_id)) {
            return false;
        }

        // Load the project-specific role with permissions
        $projectRole = \App\Models\Role::with('permissions')->find($userInProject->pivot->role_id);

        if (! $projectRole) {
            return false;
        }

        // Check if the project-specific role has the permission
        return $projectRole->permissions->contains('slug', $permission);
    }
}
