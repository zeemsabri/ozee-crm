# Permission Loading at Login Time Fix

## Issue Description

Users with the `manage_projects` permission were being redirected from project pages to the dashboard. This occurred because permissions were only being loaded when navigating to the Projects/Show.vue page, not at login time. As a result, when users tried to access project pages directly, the permission check would fail and they would be redirected to the dashboard.

## Investigation

The investigation revealed several key components involved in the permission system:

1. **HandleInertiaRequests Middleware**: This middleware loads global permissions and attaches them to the user object as `global_permissions`. However, this only happens on each request, not specifically at login time.

2. **CheckPermission Middleware**: This middleware checks if a user has the required permissions to access a route. If the permissions aren't loaded yet, the check fails and the user is redirected.

3. **Permission Utilities**: The frontend uses utilities from `resources/js/Directives/permissions.js` to check permissions. These utilities load permissions when components mount, but not immediately after login.

The root cause was that permissions weren't being loaded early enough in the user session. When a user logged in and tried to access a project page directly, the permissions hadn't been loaded yet, causing the permission check to fail.

## Changes Made

### 1. Modified the AuthenticatedSessionController

Updated the `store` method in `app/Http/Controllers/Auth/AuthenticatedSessionController.php` to load permissions immediately after login:

```php
// Load the user's role with permissions to ensure they're available immediately after login
$user = $request->user();
$user->load(['role.permissions']);

// Add global permissions to the user object
$globalPermissions = [];

// For super admin users, load all permissions from the database
if ($user->isSuperAdmin()) {
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
```

### 2. Modified the app.js File

Updated the `resources/js/app.js` file to fetch global permissions immediately after app initialization:

```javascript
// Initialize the app
const mountedApp = app.mount(el);

// Fetch global permissions immediately after app initialization
// This ensures permissions are loaded as soon as the user logs in
if (props.initialPage.props.auth && props.initialPage.props.auth.user) {
    fetchGlobalPermissions().catch(error => {
        console.error('Failed to fetch global permissions:', error);
    });
}

return mountedApp;
```

## Testing

A test script (`test-permission-loading-login.php`) was created to verify that permissions are loaded at login time and that the CheckPermission middleware correctly grants access with the manage_projects permission. The script:

1. Creates a test user with the project_manager role and manage_projects permission
2. Simulates the login process
3. Checks if the user has global_permissions after login
4. Verifies that the manage_projects permission is included
5. Tests if the CheckPermission middleware allows access with the manage_projects permission

## Impact

These changes ensure that permissions are loaded at two critical points:

1. **Server-side**: Immediately after login in the AuthenticatedSessionController
2. **Client-side**: Immediately after app initialization in app.js

This dual approach ensures that permissions are available both for server-side middleware checks and client-side permission utilities. Users with the `manage_projects` permission can now access project pages directly without being redirected to the dashboard.

## Date of Fix

2025-07-21
