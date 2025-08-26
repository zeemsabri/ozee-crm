# Project Section Permissions Fix

## Issue Description

When a user with view or manage access to project clients and users opens the ProjectForm.vue and goes to the Clients and Users tab, the API endpoint `http://localhost:8000/api/projects/2/sections/clients-users` was returning an empty array for users, even though the user had the appropriate permissions:

- view_project_clients
- manage_project_clients
- view_project_users
- manage_project_users

## Root Cause

The issue was in the `ProjectSectionController.php` file, specifically in the `canViewUsers` and `canViewClientContacts` methods. These methods were only checking for the permissions `view_users` and `view_client_contacts`, respectively, but not for the project-specific permissions mentioned in the issue description.

## Solution

The solution was to update the `canViewUsers` and `canViewClientContacts` methods to check for the additional permissions:

1. Updated `canViewUsers` to check for:
   - view_users (original)
   - view_project_users (added)
   - manage_project_users (added)

2. Updated `canViewClientContacts` to check for:
   - view_client_contacts (original)
   - view_project_clients (added)
   - manage_project_clients (added)

These changes ensure that users with any of these permissions can view the respective data in the ProjectForm.

## Changes Made

### canViewUsers Method

```php
private function canViewUsers($user, $project)
{
    // Check for super admin role
    if ($user->isSuperAdmin()) {
        return true;
    }

    // Get the user's project-specific role
    $projectUser = $project->users()->where('users.id', $user->id)->first();
    if ($projectUser && isset($projectUser->pivot->role_id)) {
        $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
        if ($projectRole) {
            // Check for view_users, view_project_users, or manage_project_users permissions
            if ($projectRole->permissions->contains('slug', 'view_users') || 
                $projectRole->permissions->contains('slug', 'view_project_users') || 
                $projectRole->permissions->contains('slug', 'manage_project_users')) {
                return true;
            }
        }
    }

    // Check global permissions
    return $user->hasPermission('view_users') || 
           $user->hasPermission('view_project_users') || 
           $user->hasPermission('manage_project_users');
}
```

### canViewClientContacts Method

```php
private function canViewClientContacts($user, $project)
{
    // Check for super admin role
    if ($user->isSuperAdmin()) {
        return true;
    }

    // Get the user's project-specific role
    $projectUser = $project->users()->where('users.id', $user->id)->first();
    if ($projectUser && isset($projectUser->pivot->role_id)) {
        $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
        if ($projectRole) {
            // Check for view_client_contacts, view_project_clients, or manage_project_clients permissions
            if ($projectRole->permissions->contains('slug', 'view_client_contacts') || 
                $projectRole->permissions->contains('slug', 'view_project_clients') || 
                $projectRole->permissions->contains('slug', 'manage_project_clients')) {
                return true;
            }
        }
    }

    // Check global permissions
    return $user->hasPermission('view_client_contacts') || 
           $user->hasPermission('view_project_clients') || 
           $user->hasPermission('manage_project_clients');
}
```

## Expected Outcome

With these changes, users with any of the specified permissions should now be able to see the clients and users data in the ProjectForm. The API endpoint `http://localhost:8000/api/projects/2/sections/clients-users` should return the appropriate data based on the user's permissions.
