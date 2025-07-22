# Edit Button Visibility Fix Documentation

## Issue Description

When saving a project on the Projects/Show.vue page, the "Edit Project" button would disappear after the modal closed. However, the button would reappear if the page was refreshed. This inconsistent behavior created a confusing user experience.

## Root Cause

The issue was in the `handleProjectSubmit` function in the Projects/Show.vue component. After saving a project, the function would update the project data with the data returned from the ProjectForm component:

```javascript
const handleProjectSubmit = (updatedProject) => {
    project.value = updatedProject;
    showEditModal.value = false;
    alert('Project updated successfully!');
};
```

The problem was that the `updatedProject` object returned from the ProjectForm component didn't contain the complete project data structure, particularly the `users` array with permission information that's needed for the `canManageProjects` computed property to work correctly.

The `canManageProjects` computed property is used to conditionally render the "Edit Project" button:

```javascript
const canManageProjects = computed(() => {
    return hasPermission('manage_projects') || isSuperAdmin.value;
});
```

The `hasPermission` function relies on user permissions data that was missing from the `updatedProject` object:

```javascript
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

When the page was refreshed, the `fetchProjectData` function was called, which fetched the complete project data from the API, including the users array with permissions, which is why the button reappeared.

## Solution

The solution was to modify the `handleProjectSubmit` function to call `fetchProjectData` after saving the project:

```javascript
const handleProjectSubmit = (updatedProject) => {
    // First update the project with the returned data
    project.value = updatedProject;
    // Close the modal
    showEditModal.value = false;
    // Show success message
    alert('Project updated successfully!');
    // Fetch the complete project data to ensure we have all necessary information
    fetchProjectData();
};
```

This ensures that after saving a project and closing the modal, the complete project data is fetched from the API, including the users array with permissions. This keeps the "Edit Project" button visible after saving, as the `canManageProjects` computed property will have access to the necessary permission information.

## Verification

To verify that the fix works correctly:

1. Log in to the application
2. Navigate to a project page where you have permission to edit the project
3. Verify that the "Edit Project" button is visible
4. Click the "Edit Project" button to open the edit modal
5. Make a change to the project and save it
6. Verify that the "Edit Project" button is still visible after the modal closes

## Impact on Other Parts of the Application

The changes made are isolated to the Projects/Show.vue component and do not affect other parts of the application. The fix ensures that the "Edit Project" button remains visible after saving a project, providing a more consistent user experience.

## Future Considerations

If similar issues arise in other parts of the application where data is updated and UI elements disappear unexpectedly, consider:

1. Checking if the updated data contains all the necessary information for computed properties that control UI elements
2. Fetching complete data from the API after updates to ensure all necessary information is available
3. Using Vue DevTools to inspect the component state before and after updates to identify missing data
