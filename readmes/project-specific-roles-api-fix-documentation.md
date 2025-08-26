# Project-Specific Roles API Fix Documentation

## Issue Description

Each user has a single global role saved in the users table with `role_id`, which dictates user permissions across the application. However, users can also have a separate role per project, which is saved in the `project_user` pivot table with `role_id`.

The issue was that the pivot data in the API response only contained the `role_id` (numeric value), but not the actual role information (name, slug, etc.) that would be needed to determine permissions in the frontend. The Projects/Show.vue component was trying to use `userInProject.pivot.role` (expecting a string like "Manager"), but the actual data only had `userInProject.pivot.role_id` (a numeric ID).

## Solution

### 1. Updated ProjectController's show method

We modified the `show` method in `app/Http/Controllers/Api/ProjectController.php` to include the role information for the project-specific role_ids:

```php
// Load project relationships
$project->load(['clients', 'users' => function ($query) {
    $query->withPivot('role_id');
}, 'transactions', 'notes']);

// Decrypt note content
$project->notes->each(function ($note) {
    $note->content = Crypt::decryptString($note->content);
});

// Load role information for each user's project-specific role
$project->users->each(function ($user) {
    // Load the user's global role information
    $user->load('role');
    
    // Get the project-specific role information
    if (isset($user->pivot->role_id)) {
        $projectRole = \App\Models\Role::find($user->pivot->role_id);
        if ($projectRole) {
            // Add the project role information to the pivot data
            $user->pivot->role_data = [
                'id' => $projectRole->id,
                'name' => $projectRole->name,
                'slug' => $projectRole->slug
            ];
        }
    }
});

return response()->json($project);
```

This change ensures that the API response includes both the global role information (in the user's `role` property) and the project-specific role information (in the user's `pivot.role_data` property).

### 2. Updated Projects/Show.vue component

We modified the `userProjectRole` and `isProjectManager` computed properties in `resources/js/Pages/Projects/Show.vue` to use the new data structure:

```javascript
// Check if user has a project-specific role in the current project
const userProjectRole = computed(() => {
    if (!authUser.value || !project.value || !project.value.users) return null;

    const userInProject = project.value.users.find(user => user.id === authUser.value.id);
    if (!userInProject || !userInProject.pivot) return null;
    
    // Log the pivot data for debugging
    console.log('User project pivot data:', userInProject.pivot);
    
    // Return the project-specific role data if available
    return userInProject.pivot.role_data || null;
});

// Check if user has a specific project role
const hasProjectRole = computed(() => {
    return !!userProjectRole.value;
});

// Check if user is a project manager in this specific project
const isProjectManager = computed(() => {
    if (!userProjectRole.value) return false;
    
    // Check if the project-specific role is a manager role
    const roleName = userProjectRole.value.name;
    const roleSlug = userProjectRole.value.slug;
    
    return roleName === 'Manager' || 
           roleName === 'Project Manager' || 
           roleSlug === 'manager' || 
           roleSlug === 'project-manager';
});
```

These changes ensure that the component correctly uses the project-specific role information that we're now including in the API response.

## Expected API Response Structure

After these changes, the API response for a project should include the following structure for each user:

```json
{
  "id": 2,
  "name": "Test Project",
  "users": [
    {
      "id": 3,
      "name": "Employee User",
      "email": "employee@example.com",
      "role_id": 3,
      "role_data": {
        "id": 3,
        "name": "Employee",
        "slug": "employee"
      },
      "pivot": {
        "project_id": 2,
        "user_id": 3,
        "role_id": 8,
        "role_data": {
          "id": 8,
          "name": "Manager",
          "slug": "manager"
        }
      },
      "role": {
        "id": 3,
        "name": "Employee",
        "slug": "employee",
        "description": "Regular employee with limited access",
        "type": "application"
      }
    }
  ]
}
```

## Verification

To verify that the changes are working correctly:

1. Log in as a user who has a project-specific role that is different from their global role
2. Navigate to a project where the user has a project-specific role
3. Open the browser's developer console and check the log message "User project pivot data:"
4. Verify that the log shows the pivot data with both `role_id` and `role_data`
5. Verify that the user's permissions on the project page are based on their project-specific role, not their global role

## Impact on Other Parts of the Application

The changes made are isolated to the ProjectController's show method and the Projects/Show.vue component. They do not affect how permissions are checked in other parts of the application. Other components will continue to use the global role system as before.

## Future Considerations

If project-specific roles need to be applied to other parts of the application in the future, a similar approach can be used:

1. Ensure that the API response includes the role information for project-specific role_ids
2. Update the frontend components to use this role information correctly

This approach ensures that project-specific roles correctly override global permissions when viewing the specific project, while maintaining the global permission system for the rest of the application.
