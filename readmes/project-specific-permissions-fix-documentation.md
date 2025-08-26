# Project-Specific Permissions Fix Documentation

## Issue Description

Users with project-specific `manage_project_users` permission were unable to attach or detach users from projects, receiving an "This action is unauthorized" error. The issue occurred because the ProjectPolicy was only checking for global permissions, not project-specific permissions.

## Investigation

Upon examining the codebase, I found:

1. In `ProjectPolicy.php`, the `attachAnyUser` and `detachAnyUser` methods were only checking for global permissions using `$user->hasPermission('manage_project_users')`.

2. The `CheckPermission` middleware had a comprehensive implementation for checking project-specific permissions in its `userHasProjectPermission` method, but this logic wasn't being used in the ProjectPolicy.

3. The User model had methods for retrieving project-specific roles, but no method for checking project-specific permissions.

## Changes Made

I modified the `ProjectPolicy.php` file to check for both global and project-specific permissions:

1. Updated the `attachAnyUser` method to check for both global and project-specific permissions:
   ```php
   public function attachAnyUser(User $user, Project $project): bool
   {
       // Check if user has global permission
       if ($user->hasPermission('manage_project_users')) {
           return true;
       }

       // Check project-specific permission
       return $this->userHasProjectPermission($user, 'manage_project_users', $project->id);
   }
   ```

2. Updated the `detachAnyUser` method with the same logic:
   ```php
   public function detachAnyUser(User $user, Project $project): bool
   {
       // Check if user has global permission
       if ($user->hasPermission('manage_project_users')) {
           return true;
       }

       // Check project-specific permission
       return $this->userHasProjectPermission($user, 'manage_project_users', $project->id);
   }
   ```

3. Added a private `userHasProjectPermission` method to check for project-specific permissions:
   ```php
   private function userHasProjectPermission($user, $permission, $projectId)
   {
       // Load the user's project with the pivot data
       $project = Project::with(['users' => function ($query) use ($user) {
           $query->where('users.id', $user->id)->withPivot('role_id');
       }])->find($projectId);

       if (!$project) {
           return false;
       }

       $userInProject = $project->users->first();

       if (!$userInProject || !isset($userInProject->pivot->role_id)) {
           return false;
       }

       // Load the project-specific role with permissions
       $projectRole = Role::with('permissions')->find($userInProject->pivot->role_id);

       if (!$projectRole) {
           return false;
       }

       // Check if the project-specific role has the permission
       return $projectRole->permissions->contains('slug', $permission);
   }
   ```

4. Added the missing Role import:
   ```php
   use App\Models\Role;
   ```

## Testing

A simulation test script was created to verify that:

1. Users with global `manage_project_users` permission can attach and detach users from any project.
2. Users with project-specific `manage_project_users` permission can only attach and detach users from that specific project.
3. Users with no `manage_project_users` permission cannot attach or detach users from any project.

The test confirmed that the policy changes work correctly.

## Impact

With this fix:

1. Users with global `manage_project_users` permission can still attach and detach users from any project.
2. Users with project-specific `manage_project_users` permission can now attach and detach users from that specific project.
3. The API no longer returns "This action is unauthorized" errors for users with project-specific permissions.
4. The permission system is now more granular, allowing for better access control at the project level.

## Date of Fix

2025-07-21
