# Project Authorization Changes

## Overview
This document summarizes the changes made to fix permission issues on the Projects/Index.vue page. The issue was that users who shouldn't have access to the Projects page (specifically contractors) were able to load it. The fix ensures that unauthorized users are redirected back to the dashboard.

## Changes Made

### 1. Updated Permission Checks in Projects/Index.vue

Updated the role-based permission checks to use the new role system with `role_data`:

```javascript
// Permission checks
const isSuperAdmin = computed(() => {
    if (!authUser.value) return false;
    return authUser.value.role_data?.slug === 'super-admin' ||
           authUser.value.role === 'super_admin' ||
           authUser.value.role === 'super-admin';
});
const isManager = computed(() => {
    if (!authUser.value) return false;
    return authUser.value.role_data?.slug === 'manager' ||
           authUser.value.role === 'manager' ||
           authUser.value.role === 'manager-role' ||
           authUser.value.role === 'manager_role';
});
const isEmployee = computed(() => {
    if (!authUser.value) return false;
    return authUser.value.role_data?.slug === 'employee' ||
           authUser.value.role === 'employee' ||
           authUser.value.role === 'employee-role';
});
const isContractor = computed(() => {
    if (!authUser.value) return false;
    return authUser.value.role_data?.slug === 'contractor' ||
           authUser.value.role === 'contractor' ||
           authUser.value.role === 'contractor-role';
});
```

### 2. Added New Permission Check for Projects Access

Added a new computed property to determine who has access to the Projects page:

```javascript
const hasAccessToProjects = computed(() => isSuperAdmin.value || isManager.value || isEmployee.value);
```

### 3. Added Redirect Logic in onMounted Hook

Added code to redirect unauthorized users to the dashboard when they try to access the Projects page:

```javascript
onMounted(() => {
    // Check if user has access to projects page
    if (!hasAccessToProjects.value) {
        // Redirect to dashboard if user doesn't have access
        window.location.href = route('dashboard');
        return;
    }
    
    fetchInitialData();
});
```

## Testing

Created a test script (`test-project-permissions.php`) to verify the permission checks are working correctly. The test confirms:

1. Super admins have access to the Projects page
2. Managers have access to the Projects page
3. Employees have access to the Projects page
4. Contractors do not have access and will be redirected to the dashboard

## Results

The permission issue has been fixed. Now:

- Super admins, managers, and employees can access the Projects page
- Contractors are redirected to the dashboard when they try to access the Projects page

This aligns with the backend permission checks in ProjectController.php, which already had proper permission handling for API requests.
