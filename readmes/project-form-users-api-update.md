# ProjectForm Users API Update

## Issue Description

The ProjectForm component was using the old API endpoint `/api/users` to fetch all users, regardless of permissions. We needed to update it to use the new project-specific endpoint `/api/projects/{project}/users` that returns users based on permissions:

- Super admins and users with `manage_project_users` permission get all users
- Users with `view_project_users` permission get all users in the current project
- Other users only get themselves

## Changes Made

### 1. Updated `fetchUsers` Function

Modified the `fetchUsers` function in ProjectForm.vue to use the project-specific endpoint when a project ID is available:

```javascript
// Before
const fetchUsers = async () => {
    try {
        const response = await window.axios.get('/api/users');
        users.value = response.data;
    } catch (error) {
        console.error('Error fetching users:', error);
        generalError.value = 'Failed to load users.';
    }
};

// After
const fetchUsers = async () => {
    try {
        // If we have a project ID, use the project-specific endpoint
        if (projectId.value) {
            const response = await window.axios.get(`/api/projects/${projectId.value}/users`);
            users.value = response.data;
        } else {
            // Fall back to the global endpoint if no project ID is available (e.g., when creating a new project)
            const response = await window.axios.get('/api/users');
            users.value = response.data;
        }
    } catch (error) {
        console.error('Error fetching users:', error);
        generalError.value = 'Failed to load users.';
    }
};
```

### 2. Updated Watch on projectId

Modified the watch on projectId to also call `fetchUsers` when the project ID changes:

```javascript
// Before
// Watch for changes to project ID and fetch project-specific permissions
watch(projectId, (newProjectId, oldProjectId) => {
    if (newProjectId && newProjectId !== oldProjectId) {
        fetchProjectPermissions(newProjectId)
            .then(permissions => {
                // Success - no logging needed
            })
            .catch(error => {
                // Error handled by the permissions utility
            });
    }
});

// After
// Watch for changes to project ID and fetch project-specific permissions and users
watch(projectId, (newProjectId, oldProjectId) => {
    if (newProjectId && newProjectId !== oldProjectId) {
        // Fetch project-specific permissions
        fetchProjectPermissions(newProjectId)
            .then(permissions => {
                // Success - no logging needed
            })
            .catch(error => {
                // Error handled by the permissions utility
            });
            
        // Fetch users based on the new project ID and permissions
        fetchUsers();
    }
});
```

## Testing

A test script (`test-project-form-users-api.js`) has been created to verify that the changes work as expected. The script:

1. Mocks the axios library to intercept API calls and verify that the correct endpoints are being used
2. Tests the `fetchUsers` function with and without a project ID
3. Tests the watch on projectId to ensure that it calls both `fetchProjectPermissions` and `fetchUsers`

### Manual Testing Instructions

1. Open the ProjectForm component in the application
2. Open the browser console
3. Create a new project and check the network tab for a request to `/api/users`
4. Edit an existing project and check the network tab for a request to `/api/projects/{id}/users`
5. Switch between projects and verify that the users dropdown is updated with the correct users

## Benefits

1. **Improved Security**: Users only see the users they have permission to see
2. **Better User Experience**: Dropdowns only show relevant users
3. **Reduced Data Transfer**: Only necessary data is sent to the client
4. **Consistent Permissions**: Uses the same permission-based logic as other parts of the application

## Implementation Details

The changes were made to the ProjectForm.vue component:

1. The `fetchUsers` function now checks if a project ID is available and uses the appropriate endpoint
2. The watch on projectId now calls `fetchUsers` when the project ID changes
3. The component still works when creating a new project (when no project ID is available)

The backend endpoint `/api/projects/{project}/users` was already implemented in the ProjectSectionController and returns users based on permissions.
