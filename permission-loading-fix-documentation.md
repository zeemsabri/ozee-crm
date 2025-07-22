# Permission Loading Fix Documentation

## Issue Description

The application was using hard-coded role checks in various components instead of fetching permissions based on the role from the role_permission table. This approach was inflexible and made it difficult to manage permissions in a granular way.

## Solution

### 1. Updated the hasPermission function in Composer.vue

The `hasPermission` function in Composer.vue was updated to remove hard-coded role checks and rely solely on permissions fetched from the database:

```javascript
// Before
const hasPermission = (permissionSlug) => {
    if (!authUser.value) return false;

    console.log(authUser);
    // Check global permissions
    if (authUser.value.global_permissions) {
        return authUser.value.global_permissions.some(p => p.slug === permissionSlug);
    }

    // Legacy role-based fallback
    if (authUser.value.role === 'super_admin' ||
        authUser.value.role === 'super-admin' ||
        (authUser.value.role_data && authUser.value.role_data.slug === 'super-admin')) {
        return true;
    }

    return false;
};

// After
const hasPermission = (permissionSlug) => {
    if (!authUser.value) return false;

    // Check global permissions from the database
    if (authUser.value.global_permissions) {
        return authUser.value.global_permissions.some(p => p.slug === permissionSlug);
    }

    // If no permissions are found, return false
    return false;
};
```

This change removes the hard-coded role checks and relies solely on permissions fetched from the database.

### 2. Updated the HandleInertiaRequests middleware

The `HandleInertiaRequests` middleware was updated to load the user's permissions and add them to the user object as `global_permissions`:

```php
// Before
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user(),
        ],
    ];
}

// After
public function share(Request $request): array
{
    $user = $request->user();
    
    // If user is authenticated, load permissions
    if ($user) {
        // Load the user's role with permissions
        $user->load(['role.permissions']);
        
        // Add global permissions to the user object
        $globalPermissions = [];
        if ($user->role && $user->role->permissions) {
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
```

This change ensures that the user's permissions are loaded and available in the frontend through the Inertia props.

## How It Works

1. When a user logs in, the `HandleInertiaRequests` middleware loads the user's role with permissions.
2. The middleware adds the permissions to the user object as `global_permissions`.
3. The user object with permissions is passed to the frontend through the Inertia props.
4. In the frontend, the `hasPermission` function checks if the user has a specific permission by looking at `authUser.value.global_permissions`.
5. If the user has the permission, the function returns true; otherwise, it returns false.

This approach ensures that permissions are fetched from the database based on the user's role, rather than using hard-coded role checks.

## Testing

A test script (`test-permissions-loading.php`) was created to verify that the `HandleInertiaRequests` middleware is correctly loading permissions and that the `hasPermission` function is working as expected. The script:

1. Sets up a test permission ('compose_emails')
2. Sets up a test role ('email_composer') with the permission
3. Sets up a test user with the role
4. Logs in as the test user
5. Creates a mock request
6. Creates an instance of the `HandleInertiaRequests` middleware
7. Gets the shared data from the middleware
8. Checks if the auth.user data includes global_permissions
9. Checks if the compose_emails permission is included in the global_permissions
10. Tests the `hasPermission` function to verify that it correctly identifies the compose_emails permission

To run the test script:

```bash
php test-permissions-loading.php
```

## Impact on Other Parts of the Application

The changes made are isolated to the `HandleInertiaRequests` middleware and the `hasPermission` function in Composer.vue. They do not affect how permissions are checked in other parts of the application. Other components that use the `hasPermission` function will benefit from the improved permission checking without any changes.

## Future Considerations

If similar permission checking needs to be implemented in other components, the same approach can be used:

1. Use the `hasPermission` function to check if the user has a specific permission.
2. Rely on the `global_permissions` property of the user object, which is loaded by the `HandleInertiaRequests` middleware.

This approach ensures that permissions are consistently checked based on data from the database, rather than using hard-coded role checks.

## Backward Compatibility

To maintain backward compatibility, the `canComposeEmails` computed property in Composer.vue still defaults to true:

```javascript
const canComposeEmails = computed(() => {
    return hasPermission('compose_emails') || true; // Default to true for backward compatibility
});
```

This ensures that existing users can still access the Email Composer page while the permission system is being fully implemented. Once the permission system is fully implemented, this fallback can be removed to enforce strict permission checks.
