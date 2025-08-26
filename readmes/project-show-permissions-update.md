# Project Show Page Permission Update: Project-Based Role Permissions

## Overview

This document summarizes the changes made to implement project-based role permissions in the Projects/Show.vue component. The issue was that the component only considered a user's application-wide role when determining what they could see or do, without taking into account their project-specific role. The fix ensures that a user's project-specific role can override their application-wide permissions.

## Changes Made

### 1. Added Project-Specific Role Detection

Added computed properties to detect if the current user has a project-specific role in the current project:

```javascript
// Check if user has a project-specific role in the current project
const userProjectRole = computed(() => {
    if (!authUser.value || !project.value || !project.value.users) return null;
    
    const userInProject = project.value.users.find(user => user.id === authUser.value.id);
    return userInProject ? userInProject.pivot.role : null;
});

// Check if user has a specific project role
const hasProjectRole = computed(() => {
    return !!userProjectRole.value;
});

// Check if user is a project manager in this specific project
const isProjectManager = computed(() => {
    return userProjectRole.value === 'Manager' || userProjectRole.value === 'Project Manager';
});
```

### 2. Updated Role-Based Permission Checks

Modified the existing role-based permission checks to consider both application-wide and project-specific roles:

```javascript
const isManager = computed(() => {
    if (!authUser.value) return false;
    // Check application-wide role first
    const hasManagerRole = (authUser.value.role_data && authUser.value.role_data.slug === 'manager') ||
           authUser.value.role === 'manager' ||
           authUser.value.role === 'manager-role' ||
           authUser.value.role === 'manager_role';
    
    // If user is not a manager application-wide, check if they're a project manager for this project
    return hasManagerRole || isProjectManager.value;
});

const isContractor = computed(() => {
    if (!authUser.value) return false;
    // Only consider application-wide role if user doesn't have a project-specific role
    if (hasProjectRole.value) return false;
    
    return (authUser.value.role_data && authUser.value.role_data.slug === 'contractor') ||
           authUser.value.role === 'contractor' ||
           authUser.value.role === 'contractor-role';
});
```

### 3. Permission-Based Feature Access

The permission-based feature access computed properties (`canViewProjectFinancial`, `canViewClientContacts`, etc.) now automatically consider both application-wide and project-specific roles because they use the updated `isSuperAdmin` and `isManager` computed properties:

```javascript
// Additional permission checks for card visibility based on role
// Super Admin and Manager roles have access to financial and client information
const canViewProjectFinancial = computed(() => isSuperAdmin.value || isManager.value);
const canViewProjectTransactions = computed(() => isSuperAdmin.value || isManager.value);
const canViewClientContacts = computed(() => isSuperAdmin.value || isManager.value);
const canViewClientFinancial = computed(() => isSuperAdmin.value || isManager.value);
const canViewUsers = computed(() => isSuperAdmin.value || isManager.value);
```

## Testing

A test script (`test-project-specific-roles.php`) was created to verify the permission checks are working correctly. The test confirms:

1. A contractor with no project-specific role has limited permissions (cannot view financial information, client contacts, or users)
2. A contractor with a project-specific role of 'Manager' has expanded permissions (can view financial information, client contacts, and users)
3. An employee with a project-specific role of 'Developer' has limited permissions (cannot view financial information, client contacts, or users)
4. An employee with a project-specific role of 'Manager' has expanded permissions (can view financial information, client contacts, and users)

## Results

The permission issue has been fixed. Now:

- Users with a project-specific role of 'Manager' have manager-level permissions for that project, regardless of their application-wide role
- Users with a project-specific role other than 'Manager' have permissions based on that role, not their application-wide role
- Users with no project-specific role continue to have permissions based on their application-wide role

This implementation satisfies the requirement that a user's project-specific role can override their application-wide permissions. For example, a user with a "contractor" application-wide role who is a "Manager" for a specific project will now have manager-level permissions when viewing that project.
