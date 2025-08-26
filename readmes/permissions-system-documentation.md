# Permissions System Documentation

## Overview

This document describes the centralized permissions system implemented in the application. The system provides a unified way to check user permissions across the application, handling both global and project-specific permissions.

## Key Features

- Centralized permission checking logic in a single utility file
- Support for both global and project-specific permissions
- Project-specific permissions override global permissions when available
- Super admin users automatically receive all permissions
- Helper functions for common permission checks (view/manage)
- Composable functions for use in Vue components

## Implementation Details

### Backend Changes

1. The `HandleInertiaRequests` middleware was updated to load permissions for users:
   - For regular users, it loads permissions based on their role
   - For super admin users, it loads all permissions from the database

```php
// For super admin users, load all permissions from the database
if ($user->isSuperAdmin()) {
    $allPermissions = \App\Models\Permission::all();
    foreach ($allPermissions as $permission) {
        $globalPermissions[] = [
            'id' => $permission->id,
            'name' => $permission->name,
            'slug' => $permission->slug,
            'category' => $permission->category
        ];
    }
} 
// For regular users, load permissions based on their role
else if ($user->role && $user->role->permissions) {
    foreach ($user->role->permissions as $permission) {
        $globalPermissions[] = [
            'id' => $permission->id,
            'name' => $permission->name,
            'slug' => $permission->slug,
            'category' => $permission->category
        ];
    }
}
```

### Frontend Utility

The permissions utility is located at `resources/js/Utils/permissions.js` and provides the following functions:

1. `useAuthUser()` - Get the authenticated user from Inertia shared props
2. `useProjectRole(project)` - Get the user's project-specific role for a given project
3. `hasPermission(permissionSlug, projectRole)` - Check if the user has a specific permission
4. `usePermissions()` - A composable function that provides permission checking utilities:
   - `checkPermission(permissionSlug, projectRole)` - Direct permission check
   - `canDo(permissionSlug, projectRole)` - Creates a computed property for permission check
   - `canView(resource, projectRole)` - Helper for view permissions
   - `canManage(resource, projectRole)` - Helper for manage permissions

## How to Use

### Basic Usage (Global Permissions)

```javascript
import { usePermissions } from '@/Utils/permissions';

// In your component setup
const { canDo, canView, canManage } = usePermissions();

// Check if user can view clients
const canViewClients = canView('clients');

// Check if user can manage projects
const canManageProjects = canManage('projects');

// Use in template
<div v-if="canViewClients.value">
  <!-- Client viewing UI -->
</div>
```

### With Project Context (Project-Specific Permissions)

```javascript
import { ref } from 'vue';
import { useProjectRole, usePermissions } from '@/Utils/permissions';

// In your component setup
const project = ref(/* project data */);
const userProjectRole = useProjectRole(project);
const { canDo, canView, canManage } = usePermissions();

// Check if user can view project documents
const canViewProjectDocuments = canView('project_documents', userProjectRole);

// Check if user can manage project users
const canManageProjectUsers = canManage('project_users', userProjectRole);

// Use in template
<div v-if="canViewProjectDocuments.value">
  <!-- Project documents UI -->
</div>
```

### Legacy Role Checks

If you need to maintain backward compatibility with role-based checks, you can still use them alongside the permission-based checks:

```javascript
import { computed } from 'vue';
import { useAuthUser, useProjectRole, usePermissions } from '@/Utils/permissions';

// In your component setup
const authUser = useAuthUser();
const project = ref(/* project data */);
const userProjectRole = useProjectRole(project);
const { canDo, canView, canManage } = usePermissions();

// Legacy role check
const isSuperAdmin = computed(() => {
    if (!authUser.value) return false;
    return (authUser.value.role_data && authUser.value.role_data.slug === 'super-admin') ||
           authUser.value.role === 'super_admin' ||
           authUser.value.role === 'super-admin';
});

// Combined check
const canViewProjectFinancial = computed(() => {
    return canView('project_financial', userProjectRole).value || isSuperAdmin.value;
});
```

## Permission Hierarchy

The system follows this hierarchy when checking permissions:

1. Project-specific permissions (if in a project context)
2. Global permissions (if no project-specific permission is found)

This means that project-specific permissions override global permissions when both are present.

## Super Admin Handling

Super admin users automatically receive all permissions from the backend. This eliminates the need to update the super admin role every time a new permission is added to the system.

## Best Practices

1. Always use the centralized permissions utility instead of implementing custom permission checks
2. Use the appropriate helper function for the type of permission you're checking:
   - `canView` for view permissions
   - `canManage` for manage permissions
   - `canDo` for other types of permissions
3. When working with project-specific permissions, always pass the project role to the permission check
4. Remember that permission checks return computed properties, so use `.value` when accessing them in script

## Examples

### Showing/Hiding UI Elements Based on Permissions

```vue
<template>
  <div>
    <h1>Project Details</h1>
    
    <!-- Only show if user can view project documents -->
    <div v-if="canViewProjectDocuments.value" class="documents-section">
      <h2>Documents</h2>
      <!-- Documents UI -->
    </div>
    
    <!-- Only show if user can manage project users -->
    <div v-if="canManageProjectUsers.value" class="users-section">
      <h2>Users</h2>
      <button @click="addUser">Add User</button>
      <!-- Users UI -->
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useProjectRole, usePermissions } from '@/Utils/permissions';

const project = ref(/* project data */);
const userProjectRole = useProjectRole(project);
const { canView, canManage } = usePermissions();

const canViewProjectDocuments = canView('project_documents', userProjectRole);
const canManageProjectUsers = canManage('project_users', userProjectRole);

function addUser() {
  // Add user logic
}
</script>
```

### Conditional API Calls Based on Permissions

```javascript
const fetchData = async () => {
  try {
    // Only fetch financial data if user has permission
    if (canViewProjectFinancial.value) {
      const financialResponse = await axios.get(`/api/projects/${projectId}/financial`);
      financialData.value = financialResponse.data;
    }
    
    // Always fetch basic project data
    const projectResponse = await axios.get(`/api/projects/${projectId}`);
    projectData.value = projectResponse.data;
  } catch (error) {
    console.error('Error fetching data:', error);
  }
};
```

## Troubleshooting

### Permission Not Working as Expected

1. Check if the permission slug is correct
2. Verify that the user has the permission in their role
3. If using project-specific permissions, ensure the project role is being passed correctly
4. Check the browser console for any errors
5. Use Vue DevTools to inspect the computed properties and user data

### Adding New Permissions

1. Add the permission to the database using the admin interface
2. Assign the permission to the appropriate roles
3. Use the permission in your code with the correct slug

Remember that super admin users will automatically get all permissions, but you'll need to assign new permissions to other roles manually.
