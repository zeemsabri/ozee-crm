# Project-Specific Roles Fix Summary

## Issue Description

Each user has a single global role saved in the users table with `role_id`, which dictates user permissions across the application. Users can also have a separate role per project, which is saved in the `project_user` pivot table with `role_id`.

The issue was that the project-specific roles were not overriding the user's default role permissions on the Projects/Show.vue page. This was because the pivot data in the API response did not properly include the role_data and permissions information needed by the frontend.

## Changes Made

### 1. Updated ProjectController's show method

We modified the `show` method in `app/Http/Controllers/Api/ProjectController.php` to ensure that the role_data property is included in the JSON response:

```php
// Add the project role information to the pivot data with permissions included
$user->pivot->role_data = [
    'id' => $projectRole->id,
    'name' => $projectRole->name,
    'slug' => $projectRole->slug,
    'permissions' => $permissions
];

// Make sure role_data is included in the JSON response
$user->setRelation('pivot', $user->pivot->makeVisible(['role_data']));

// Also set the role property directly for display in the UI
$user->pivot->role = $projectRole->name;
```

We also made sure that the global_permissions property is included in the JSON response:

```php
$user->global_permissions = $globalPermissions;

// Make sure global_permissions is included in the JSON response
$user->makeVisible(['global_permissions']);
```

### 2. How This Fixes the Issue

The issue was that the role_data property was being added to the pivot object dynamically, but it wasn't being included in the JSON response because it wasn't defined as an attribute that should be appended to the pivot.

By using the `makeVisible` method, we explicitly tell Laravel to include these properties in the JSON response, even though they were added dynamically.

This ensures that:

1. The frontend receives the complete role_data object with permissions for project-specific roles
2. The frontend receives the global_permissions array for the user's global role
3. The hasPermission function in Projects/Show.vue can correctly check for project-specific permissions first, and then fall back to global permissions if needed

## Expected Behavior After Fix

After these changes, when a user views a project page:

1. The API response will include both the global role information (in the user's `role` property and `global_permissions` array) and the project-specific role information (in the user's `pivot.role_data` property with permissions).

2. The hasPermission function in Projects/Show.vue will first check for project-specific permissions:
   ```javascript
   // First check project-specific permissions if available
   if (userProjectRole.value && userProjectRole.value.permissions) {
       const projectPermission = userProjectRole.value.permissions.find(p => p.slug === permissionSlug);
       if (projectPermission) return true;
   }
   ```

3. If no project-specific permission is found, it will fall back to global permissions:
   ```javascript
   // Fall back to global permissions if no project-specific permission found
   if (authUser.value.global_permissions) {
       return authUser.value.global_permissions.some(p => p.slug === permissionSlug);
   }
   ```

4. This ensures that project-specific roles correctly override global permissions when viewing the specific project, while maintaining the global permission system for the rest of the application.

## Verification

To verify that the changes are working correctly:

1. Log in as a user who has a project-specific role that is different from their global role
2. Navigate to a project where the user has a project-specific role
3. Open the browser's developer console and check the API response for the project
4. Verify that the response includes both `pivot.role_data` with permissions and `global_permissions`
5. Verify that the user's permissions on the project page are based on their project-specific role, not their global role

These changes should ensure that project-specific roles correctly override global permissions on the Projects/Show.vue page.
