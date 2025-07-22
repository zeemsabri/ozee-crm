# Project-Specific Roles Implementation

## Overview

This document describes the implementation of project-specific roles that override global user permissions on the Projects/Show.vue page.

## Background

In the application, each user has a single global role saved in the users table with role_id, which dictates user permissions across the application. However, users can also have a separate role per project, which is saved in the project_user table. These project-specific roles should override the global permissions, but only on the Projects/Show.vue page.

## Implementation Details

### Changes Made

The following changes were made to the `resources/js/Pages/Projects/Show.vue` file:

1. Updated the `isEmployee` computed property to consider project-specific roles:
   ```javascript
   const isEmployee = computed(() => {
       if (!authUser.value) return false;
       // If user has a project-specific role, don't consider them an employee for this project
       if (hasProjectRole.value && !isProjectManager.value) return false;
       
       return (authUser.value.role_data && authUser.value.role_data.slug === 'employee') ||
              authUser.value.role === 'employee' ||
              authUser.value.role === 'employee-role';
   });
   ```

2. Updated `canManageProjects` to explicitly check for project-specific manager roles:
   ```javascript
   const canManageProjects = computed(() => isSuperAdmin.value || isManager.value || isProjectManager.value);
   ```

3. Restructured all permission check computed properties to first check for project-specific roles:
   ```javascript
   const canViewProjectFinancial = computed(() => {
       // If user has a project-specific Manager role, they can view financial info regardless of global role
       if (isProjectManager.value) return true;
       // Otherwise, fall back to global role permissions
       return isSuperAdmin.value || isManager.value;
   });
   ```

4. Similar changes were made to `canViewProjectTransactions`, `canViewClientContacts`, `canViewClientFinancial`, and `canViewUsers`.

### How It Works

1. When a user views a project page, the application first checks if they have a project-specific role for that project.
2. If they have a project-specific "Manager" role, they are granted all the permissions associated with that role, regardless of their global role.
3. If they don't have a project-specific manager role, the application falls back to checking their global role permissions.

### Testing

A test script (`test-project-specific-roles-fix.php`) was created to verify the logic of the implementation. The script tests two scenarios:

1. A user with a global 'employee' role but a project-specific 'Manager' role - should have access to financial information, client contacts, and user information.
2. A user with a global 'manager' role but a project-specific 'Developer' role - should still have access to all information because of their global manager role.

## Impact on Other Parts of the Application

The changes made are isolated to the Projects/Show.vue component and do not affect how permissions are checked in other parts of the application. Other components will continue to use the global role system as before.

## Future Considerations

If project-specific roles need to be applied to other parts of the application in the future, a similar approach can be used:

1. Check if the user has a project-specific role for the current project
2. If they do, use that role to determine permissions
3. If they don't, fall back to their global role

This approach ensures that project-specific roles only override global permissions when viewing the specific project, while maintaining the global permission system for the rest of the application.
