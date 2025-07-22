# Upload Project Documents Permission Fix

## Issue Description

Users with the "Upload Project Document" permission through a project role were still getting a message "You don't have permission to upload documents" on the frontend.

## Root Cause

The issue was in the `canDo` function in the permissions.js utility. When both a project role and a project ID were provided to the function, it was ignoring the project role and only using the project ID with the permission store's `hasPermission` getter. This caused the permission check to fail if the permission was only in the project role but not in the project permissions store.

## Solution

Modified the `canDo` function in permissions.js to properly handle the case when both a project role and a project ID are provided:

```javascript
const canDo = (permissionSlug, projectRole = null) => {
  return computed(() => {
    // If we have a project role, check it first regardless of whether we have a project ID
    if (projectRole && projectRole.value) {
      // Check if permissions are directly in the role object
      if (projectRole.value.permissions) {
        const projectPermission = projectRole.value.permissions.find(p => p.slug === permissionSlug);
        if (projectPermission) {
          return true;
        }
      }
    }

    // If no permission found in project role or no project role provided, use the store's hasPermission getter
    return permissionStore.hasPermission(permissionSlug, validProjectId);
  });
};
```

The key changes are:
1. Check the project role first, regardless of whether a project ID is provided
2. Only fall back to the store's hasPermission getter if no permission is found in the project role

## Files Changed

- `/Users/zeeshansabri/laravel/email-approval-app/resources/js/Directives/permissions.js`

## Impact

This fix ensures that when `canUploadProjectDocuments` is defined with both the project ID and the user's project role, it will first check if the permission is in the project role's permissions array. If it is, it will return true. If not, it will fall back to checking the project permissions in the store.

Users who have the "Upload Project Document" permission through their project role will now be able to see the document upload section in the ProjectForm component, instead of seeing the error message "You don't have permission to upload documents".
