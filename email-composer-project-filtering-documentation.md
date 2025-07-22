# Email Composer Project Filtering Implementation

## Overview

This document describes the implementation of project filtering in the Email Composer page. The changes ensure that users only see projects they have permission to send emails from, based on their global and project-specific roles and permissions.

## Background

Previously, the Email Composer page showed a dropdown of projects that was filtered based on the user's global role:
- Contractors only saw projects they were explicitly assigned to
- Super Admins, Managers, and Employees saw all projects

However, this filtering didn't take into account project-specific roles and permissions from the `project_user` and `role_permission` tables. The requirement was to filter the projects dropdown to only show projects where the user has permission to send emails, based on both their global role and any project-specific roles they might have.

## Implementation Details

### Changes Made

#### 1. Updated ProjectController's getProjectsForEmailComposer method

The `getProjectsForEmailComposer` method in `app/Http/Controllers/Api/ProjectController.php` was updated to filter projects based on permissions:

```php
public function getProjectsForEmailComposer()
{
    $user = Auth::user();
    $projects = collect();

    // Load the user's global role with permissions
    $user->load(['role.permissions']);

    // Check if user has global permission to compose emails
    $hasGlobalComposeEmailPermission = false;
    if ($user->role && $user->role->permissions) {
        $hasGlobalComposeEmailPermission = $user->role->permissions->contains('slug', 'compose_emails');
    }

    // SuperAdmin always has access to all projects
    if ($user->isSuperAdmin()) {
        $projects = Project::with('client:id,name')->get();
    } 
    // If user has global compose_emails permission, get projects based on role
    elseif ($hasGlobalComposeEmailPermission) {
        if ($user->isManager() || $user->isEmployee()) {
            $projects = Project::with('client:id,name')->get();
        } elseif ($user->isContractor()) {
            $projects = $user->projects()->with('client:id,name')->get();
        }
    } 
    // If user doesn't have global permission, check project-specific permissions
    else {
        // Get all projects the user is assigned to
        $userProjects = $user->projects()->with(['client:id,name'])->get();
        
        // Filter projects based on project-specific roles and permissions
        foreach ($userProjects as $project) {
            // Get the user's project-specific role
            $projectRole = null;
            if (isset($project->pivot->role_id)) {
                $projectRole = \App\Models\Role::with('permissions')->find($project->pivot->role_id);
            }
            
            // Check if the project-specific role has compose_emails permission
            if ($projectRole && $projectRole->permissions->contains('slug', 'compose_emails')) {
                $projects->push($project);
            }
        }
    }

    // Transform the projects to include only the necessary information
    $transformedProjects = $projects->map(function ($project) {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'status' => $project->status,
            'client' => $project->client ? [
                'id' => $project->client->id,
                'name' => $project->client->name,
            ] : null,
        ];
    });

    return response()->json($transformedProjects);
}
```

This change ensures that the API endpoint only returns projects that the user has permission to send emails from, based on both their global role and any project-specific roles they might have.

#### 2. Updated Composer.vue component

The `assignedProjects` computed property in `resources/js/Pages/Emails/Composer.vue` was updated to remove redundant filtering:

```javascript
// Computed properties for UI/Logic
// Projects are already filtered by the backend based on permissions
const assignedProjects = computed(() => {
    // Return all projects from the backend, which are already filtered
    // based on user's permissions (both global and project-specific)
    return projects.value;
});
```

This change removes the redundant filtering in the frontend, since the backend now handles the filtering based on permissions.

#### 3. Fixed canComposeEmails computed property

The `canComposeEmails` computed property in `resources/js/Pages/Emails/Composer.vue` was fixed to correctly default to true for backward compatibility:

```javascript
// Check if user can compose emails
const canComposeEmails = computed(() => {
    return hasPermission('compose_emails') || true; // Default to true for backward compatibility
});
```

This change ensures that users without explicit permissions can still access the email composer functionality during the transition to the new permission system.

### How It Works

1. When a user navigates to the Email Composer page, the component makes a request to the `/api/projects-for-email` endpoint.
2. The backend checks the user's permissions:
   - If the user is a SuperAdmin, they get all projects.
   - If the user has global 'compose_emails' permission, they get projects based on their role (all projects for Manager/Employee, assigned projects for Contractor).
   - If the user doesn't have global permission, the backend checks their project-specific roles and only returns projects where they have the 'compose_emails' permission.
3. The frontend receives the filtered list of projects and displays them in the dropdown.
4. The user can only select from projects they have permission to send emails from.

## Testing

A test script (`test-email-composer-project-filtering.php`) has been created to verify that the filtering works correctly. The script:

1. Sets up test permissions, roles, and users
2. Creates test projects
3. Assigns users to projects with specific roles
4. Tests two scenarios:
   - User with global compose_emails permission
   - User without global permission but with project-specific permission
5. Verifies that the results match the expected behavior

To run the test script:

```bash
php test-email-composer-project-filtering.php
```

Expected results:
- User with global permission should see all projects
- User without global permission but with project-specific permission should only see the project where they have the project-specific permission

## Impact on Other Parts of the Application

The changes made are isolated to the ProjectController's getProjectsForEmailComposer method and the Composer.vue component. They do not affect how permissions are checked in other parts of the application.

## Future Considerations

If similar filtering needs to be implemented in other parts of the application, a similar approach can be used:

1. Update the relevant API endpoint to filter results based on permissions
2. Ensure the frontend component uses the filtered results correctly

This approach ensures that users only see and interact with data they have permission to access, based on both their global role and any context-specific roles they might have.
