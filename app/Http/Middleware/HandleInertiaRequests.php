<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // If user is authenticated, load permissions
        if ($user) {
            // Load the user's role with permissions
            $user->load(['role.permissions']);

            // Add global permissions to the user object
            $globalPermissions = [];

            // For super admin users, load all permissions from the database
            if ($user->isSuperAdmin()) {
                // Import the Permission model
                $allPermissions = \App\Models\Permission::all();
                foreach ($allPermissions as $permission) {
                    $globalPermissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'category' => $permission->category
                    ];
                }
            }
            // For regular users, load permissions based on their role
            else if ($user->role && $user->role->permissions) {
                foreach ($user->role->permissions as $permission) {
                    $globalPermissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'category' => $permission->category
                    ];
                }
            }

            $user->global_permissions = $globalPermissions;
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
        ];
    }
}
