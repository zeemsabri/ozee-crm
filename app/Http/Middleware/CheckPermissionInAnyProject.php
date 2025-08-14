<?php

namespace App\Http\Middleware;

use App\Exceptions\PermissionDeniedException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckPermissionInAnyProject
{
    /**
     * Handle an incoming request.
     *
     * Usage: middleware('permissionInAnyProject:permission_slug')
     * This will authorize the user if they either:
     *  - have the given permission globally (via primary role), or
     *  - have the given permission in any of the projects they are assigned to (via project role)
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = Auth::user();

        if (!$user) {
            Log::warning('Unauthenticated user attempted to access route requiring permission in any project', [
                'permission' => $permission,
                'route' => $request->path(),
            ]);
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Super admins have all permissions
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $next($request);
        }

        // First allow if user has global permission via primary role
        if ($this->userHasGlobalPermission($user, $permission)) {
            return $next($request);
        }

        // Get all project IDs the user is assigned to
        $projectIds = $user->projects()->pluck('projects.id')->toArray();

        // If user has the permission on any project role, allow
        if (!empty($projectIds) && method_exists($user, 'hasProjectPermissionOnAnyRole')) {
            if ($user->hasProjectPermissionOnAnyRole($projectIds, $permission)) {
                return $next($request);
            }
        }

        Log::warning('User attempted to access route without required permission in any project', [
            'user_id' => $user->id,
            'permission' => $permission,
            'route' => $request->path(),
        ]);

        throw new PermissionDeniedException(
            $permission,
            null,
            'Forbidden. You do not have the required permission in any project: ' . $permission
        );
    }

    private function userHasGlobalPermission($user, string $permission): bool
    {
        // Load the user's role with permissions if not already loaded
        if (!$user->relationLoaded('role') || ($user->role && !$user->role->relationLoaded('permissions'))) {
            $user->load('role.permissions');
        }

        if ($user->role && $user->role->permissions) {
            return $user->role->permissions->contains('slug', $permission);
        }

        return false;
    }
}
