# Project User Management Authorization Fix

## Issue Description

Users with the `manage_project_users` permission were unable to save users to projects, receiving an "This action is unauthorized" error. The issue was occurring in the ProjectController's `attachUsers` and `detachUsers` methods.

## Investigation

Upon examining the code, I found:

1. In `ProjectController.php`, the `attachUsers` method (line 1032) was using the `attachAnyUser` policy check:
   ```php
   $this->authorize('attachAnyUser', $project);
   ```

2. In `ProjectController.php`, the `detachUsers` method (line 1103) was using the `detachAnyUser` policy check:
   ```php
   $this->authorize('detachAnyUser', $project);
   ```

3. In `ProjectPolicy.php`, the `attachAnyUser` method was correctly checking for the `manage_project_users` permission:
   ```php
   public function attachAnyUser(User $user, Project $project): bool
   {
       return $user->hasPermission('manage_project_users');
   }
   ```

4. However, the `detachAnyUser` method was checking for the `edit_projects` permission instead:
   ```php
   public function detachAnyUser(User $user, Project $project): bool
   {
       // This requires edit_projects permission as it's modifying a project
       return $user->hasPermission('edit_projects');
   }
   ```

This inconsistency meant that users with only the `manage_project_users` permission could attach users to projects but could not detach them.

## Changes Made

I modified the `detachAnyUser` method in `ProjectPolicy.php` to check for the `manage_project_users` permission instead of `edit_projects`:

```php
public function detachAnyUser(User $user, Project $project): bool
{
    // This requires manage_project_users permission as it's modifying project users
    return $user->hasPermission('manage_project_users');
}
```

This change ensures that both attaching and detaching users from projects consistently require the same permission.

## Testing

A simulation test script was created to verify that:

1. Users with the `manage_project_users` permission can both attach and detach users from projects
2. Users without the `manage_project_users` permission cannot attach or detach users from projects

The test confirmed that the policy changes work correctly.

## Impact

With this fix:

1. Users with the `manage_project_users` permission can now both attach and detach users from projects
2. The API no longer returns "This action is unauthorized" errors for these users
3. The permission requirements are now consistent between the two related operations (attaching and detaching users)

## Date of Fix

2025-07-21
