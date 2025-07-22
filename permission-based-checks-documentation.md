# Permission-Based Checks Implementation

## Overview

This document describes the implementation of permission-based checks that replace hard-coded role-based checks in the application. The new system fetches permissions from the database through the role_permission table, allowing for more flexible and granular access control.

## Background

Previously, the application used hard-coded role-based checks to determine user permissions. For example, the Projects/Show.vue component had checks like:

```javascript
const canViewProjectFinancial = computed(() => {
    if (isProjectManager.value) return true;
    return isSuperAdmin.value || isManager.value;
});
```

These checks were based on role names/slugs rather than actual permissions from the database. The new implementation fetches permissions from the role_permission table and uses them to determine user access.

## Database Structure

The permission system uses the following tables:

1. `roles` - Stores role information (id, name, slug, description, type)
2. `permissions` - Stores permission information (id, name, slug, description, category)
3. `role_permission` - Pivot table that associates roles with permissions

Each role can have multiple permissions, and the permissions determine what actions a user with that role can perform.

## Implementation Details

### Changes Made

#### 1. Updated ProjectController's show method

The ProjectController's show method was updated to include permissions data for both global and project-specific roles:

```php
// Load role information for each user's project-specific role
$project->users->each(function ($user) {
    // Load the user's global role information with permissions
    $user->load(['role.permissions']);

    // Get the project-specific role information with permissions
    if (isset($user->pivot->role_id)) {
        $projectRole = \App\Models\Role::with('permissions')->find($user->pivot->role_id);
        if ($projectRole) {
            // Add the project role information to the pivot data
            $user->pivot->role_data = [
                'id' => $projectRole->id,
                'name' => $projectRole->name,
                'slug' => $projectRole->slug
            ];
            
            // Add permissions to the role_data
            $permissions = [];
            foreach ($projectRole->permissions as $permission) {
                $permissions[] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'category' => $permission->category
                ];
            }
            $user->pivot->role_data['permissions'] = $permissions;
        }
    }
    
    // Add global role permissions to the user data if available
    if ($user->role) {
        $globalPermissions = [];
        foreach ($user->role->permissions as $permission) {
            $globalPermissions[] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'category' => $permission->category
            ];
        }
        $user->global_permissions = $globalPermissions;
    }
});
```

This change ensures that the API response includes permissions data for both global and project-specific roles.

#### 2. Updated Projects/Show.vue component

The Projects/Show.vue component was updated to use permission-based checks instead of role-based checks:

```javascript
// Helper function to check if the current user has a specific permission
const hasPermission = (permissionSlug) => {
    if (!authUser.value) return false;
    
    // First check project-specific permissions if available
    if (userProjectRole.value && userProjectRole.value.permissions) {
        const projectPermission = userProjectRole.value.permissions.find(p => p.slug === permissionSlug);
        if (projectPermission) return true;
    }
    
    // Fall back to global permissions if no project-specific permission found
    if (authUser.value.global_permissions) {
        return authUser.value.global_permissions.some(p => p.slug === permissionSlug);
    }
    
    return false;
};
```

This helper function checks if the current user has a specific permission, first checking project-specific permissions and then falling back to global permissions if needed.

The permission-based computed properties were updated to use this helper function:

```javascript
const canManageProjects = computed(() => {
    return hasPermission('manage_projects') || isSuperAdmin.value;
});

const canViewProjectFinancial = computed(() => {
    return hasPermission('view_project_financial') || isSuperAdmin.value;
});

// ... other permission checks
```

The legacy role-based checks were kept for backward compatibility and as a fallback in case the permissions data is not available.

### How It Works

1. When a user views a project page, the application fetches the project data from the API, which includes permissions data for both global and project-specific roles.
2. The Projects/Show.vue component uses the `hasPermission` helper function to check if the user has specific permissions.
3. The helper function first checks if the user has a project-specific permission for the requested action.
4. If no project-specific permission is found, it falls back to checking the user's global permissions.
5. If the user has the required permission (either project-specific or global), they are granted access to the corresponding feature.

## Standard Permission Slugs

The following permission slugs are used in the application:

- `view_project_financial` - Permission to view project financial information
- `view_project_transactions` - Permission to view project transactions
- `view_client_contacts` - Permission to view client contacts
- `view_client_financial` - Permission to view client financial information
- `view_users` - Permission to view users assigned to a project
- `manage_projects` - Permission to manage projects (edit, delete, etc.)
- `view_emails` - Permission to view emails
- `compose_emails` - Permission to compose emails

## Usage Examples

### Checking if a user has a specific permission

```javascript
if (hasPermission('view_project_financial')) {
    // User has permission to view project financial information
    // Show financial information
}
```

### Using permission checks in computed properties

```javascript
const canViewProjectFinancial = computed(() => {
    return hasPermission('view_project_financial') || isSuperAdmin.value;
});
```

### Using permission checks in templates

```html
<div v-if="canViewProjectFinancial" class="financial-info">
    <!-- Financial information content -->
</div>
```

## Testing

A test script (`test-permission-based-checks.php`) has been created to verify that the changes work correctly. The script:

1. Sets up test permissions, roles, and users
2. Assigns permissions to roles
3. Tests API responses for both global and project-specific permissions
4. Verifies that all expected permissions are present in the responses

To run the test script:

```bash
php test-permission-based-checks.php
```

## Impact on Other Parts of the Application

The changes made are isolated to the ProjectController's show method and the Projects/Show.vue component. They do not affect how permissions are checked in other parts of the application. Other components will continue to use the existing permission checking mechanisms.

## Future Considerations

If permission-based checks need to be applied to other parts of the application in the future, a similar approach can be used:

1. Update the relevant API endpoints to include permissions data
2. Add a `hasPermission` helper function to the component
3. Update permission checks to use the helper function

This approach ensures that permissions are correctly checked based on data from the database rather than hard-coded role checks, allowing for more flexible and granular access control.

## Backward Compatibility

The implementation maintains backward compatibility by:

1. Keeping the legacy role-based checks for backward compatibility
2. Adding fallbacks to the permission-based checks (e.g., `|| isSuperAdmin.value`)
3. Defaulting email permissions to true for backward compatibility

This ensures that existing code that relies on the old role-based checks will continue to work while new code can use the more flexible permission-based approach.
