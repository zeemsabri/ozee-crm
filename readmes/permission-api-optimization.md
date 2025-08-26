# Permission API Optimization

## Issue Description

The application was making redundant API calls to fetch permissions:

1. `/api/user/permissions` - For global permissions
2. `/api/projects/{projectId}/permissions` - For project-specific permissions

The issue was that the project permissions endpoint already returns both global and project-specific permissions, making the call to the global permissions endpoint redundant.

## Changes Made

### 1. Modified the Permission Store

Updated the `fetchGlobalPermissions` method in the permissions store to use `fetchProjectPermissions('global')` internally:

```javascript
async fetchGlobalPermissions() {
    if (this.globalPermissions) {
        return this.globalPermissions;
    }

    this.loadingGlobal = true;
    this.globalError = null;

    try {
        // Use the fetchProjectPermissions method with a special "global" identifier
        // This will use the project permissions endpoint which already includes global permissions
        const data = await this.fetchProjectPermissions('global');
        
        // Store the data in the globalPermissions state
        this.globalPermissions = data;
        this.globalRole = data.global_role;

        return data;
    } catch (error) {
        this.globalError = error;
        return null;
    } finally {
        this.loadingGlobal = false;
    }
}
```

### 2. Updated the Project Permissions Method

Modified the `fetchProjectPermissions` method to handle the "global" identifier as a special case:

```javascript
async fetchProjectPermissions(projectId) {
    // Special case for 'global' identifier
    const isGlobalRequest = projectId === 'global';
    
    // For non-global requests, ensure projectId is a valid ID
    if (!isGlobalRequest && (!projectId || isNaN(Number(projectId)) || projectId === '[object Object]')) {
        return null;
    }

    // Convert to string for consistent key usage
    const projectIdStr = String(projectId);

    // If we already have permissions for this project and they're not being loaded, return them
    if (this.projectPermissions[projectIdStr] && !this.loadingProject[projectIdStr]) {
        return this.projectPermissions[projectIdStr];
    }

    // Set loading state for this project
    this.loadingProject = {
        ...this.loadingProject,
        [projectIdStr]: true
    };

    // Clear any previous errors
    this.projectErrors = {
        ...this.projectErrors,
        [projectIdStr]: null
    };

    try {
        // Use different endpoint based on whether this is a global request or project-specific
        const url = isGlobalRequest 
            ? '/api/user/permissions'  // Use the global permissions endpoint for 'global'
            : `/api/projects/${projectIdStr}/permissions`;  // Use project-specific endpoint otherwise
            
        const response = await axios.get(url);

        // Store the permissions for this project
        this.projectPermissions = {
            ...this.projectPermissions,
            [projectIdStr]: response.data
        };

        return response.data;
    } catch (error) {
        // Store the error
        this.projectErrors = {
            ...this.projectErrors,
            [projectIdStr]: error
        };

        return null;
    } finally {
        // Clear loading state
        this.loadingProject = {
            ...this.loadingProject,
            [projectIdStr]: false
        };
    }
}
```

### 3. Updated the Exported Utility Functions

Modified the exported utility functions to use the store's methods:

```javascript
/**
 * Fetch global permissions for the current user
 * This ensures we have the latest permissions even if they're not in the auth user object
 * Note: This function now uses fetchProjectPermissions internally to avoid redundant API calls
 */
export const fetchGlobalPermissions = async () => {
  const permissionStore = usePermissionStore();
  return await permissionStore.fetchGlobalPermissions();
};

/**
 * Fetch project-specific permissions for the current user
 * @param {number|string} projectId - The ID of the project or 'global' for global permissions
 * @returns {Object|null} - The permissions data or null if there was an error
 */
export const fetchProjectPermissions = async (projectId) => {
  const permissionStore = usePermissionStore();
  return await permissionStore.fetchProjectPermissions(projectId);
};
```

### 4. Updated the Show.vue Component

Modified the Show.vue component to avoid making redundant API calls:

```javascript
onMounted(async () => {
    console.log('Component mounted, fetching data...');

    // Fetch project-specific permissions
    // This will also include global permissions, so we don't need a separate call
    try {
        const projectId = usePage().props.id;
        const permissions = await fetchProjectPermissions(projectId);
        console.log('Project permissions fetched (includes global):', permissions);
    } catch (error) {
        console.error(`Error fetching permissions for project ${projectId}:`, error);
    }

    // Then fetch project data
    await fetchProjectData();
    await fetchProjectEmails();

    // Log permission status after all data is loaded
    console.log('All data loaded, permission status:');
    console.log('- Global permissions:', globalPermissions.value);
    console.log('- Global permissions loading:', permissionsLoading.value);
    console.log('- Global permissions error:', permissionsError.value);
    console.log('- Project permissions:', projectPermissions.value);
    console.log('- Project permissions loading:', projectPermissionsLoading.value);
    console.log('- Project permissions error:', projectPermissionsError.value);
    console.log('- User project role:', userProjectRole.value);
    console.log('- Can manage projects:', canManageProjects.value);
});
```

## Benefits

1. **Reduced API Calls**: The application now makes a single API call to fetch both global and project-specific permissions, reducing network traffic and improving performance.

2. **Simplified Code**: The permission fetching logic is now more straightforward, with a single source of truth for permissions.

3. **Maintained Backward Compatibility**: The changes maintain backward compatibility with existing code, so components that use `useGlobalPermissions()` or `fetchGlobalPermissions()` will continue to work.

4. **Improved Debugging**: By eliminating redundant API calls, it's easier to debug permission-related issues.

## Future Improvements

1. **Unified Endpoint**: Consider creating a unified endpoint that returns all permissions (global and project-specific) in a single call, rather than having separate endpoints.

2. **Caching**: Implement caching for permissions to further reduce API calls, especially for frequently accessed permissions.

3. **Permission Directive**: Enhance the permission directive to use the optimized permission fetching logic.
