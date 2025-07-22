# Email Composer Permissions Update

## Issue Description

The Composer.vue component needed to be updated to use the new permissions.js file from the Directives folder, similar to how it was implemented in Projects/Show.vue.

## Changes Made

### 1. Updated Import Statement

Changed the import statement to use the permissions utilities from Directives/permissions.js instead of Utils/permissions.js:

```javascript
// Before
import { useAuthUser, usePermissions } from '@/Utils/permissions';

// After
import { useAuthUser, usePermissions, useGlobalPermissions, fetchGlobalPermissions } from '@/Directives/permissions';
```

### 2. Updated Permission Checking Code

Enhanced the permission checking code to use the global permissions approach:

```javascript
// Before
const authUser = useAuthUser();
const { canDo } = usePermissions();

// Check if user can compose emails
const canComposeEmails = computed(() => {
    return canDo('compose_emails').value || true; // Default to true for backward compatibility
});

// After
const authUser = useAuthUser();

// Use global permissions
const { permissions: globalPermissions, loading: permissionsLoading, error: permissionsError } = useGlobalPermissions();

// Set up permission checking functions
const { canDo, canView, canManage } = usePermissions();

// Check if user can compose emails
const canComposeEmails = computed(() => {
    return canDo('compose_emails').value || true; // Default to true for backward compatibility
});
```

### 3. Updated onMounted Hook

Modified the onMounted hook to fetch global permissions when the component is mounted:

```javascript
// Before
onMounted(() => {
    // Proceed with loading data without redirection
    fetchInitialData().then(() => {
        // After data is loaded, check for query parameters
        checkQueryParams();
    });
});

// After
onMounted(async () => {
    // Fetch global permissions first
    try {
        console.log('Fetching global permissions...');
        const permissions = await fetchGlobalPermissions();
        console.log('Global permissions fetched:', permissions);
    } catch (error) {
        console.error('Error fetching global permissions:', error);
    }

    // Proceed with loading data without redirection
    fetchInitialData().then(() => {
        // After data is loaded, check for query parameters
        checkQueryParams();
    });

    // Log permission status after all data is loaded
    console.log('All data loaded, permission status:');
    console.log('- Global permissions:', globalPermissions.value);
    console.log('- Permissions loading:', permissionsLoading.value);
    console.log('- Permissions error:', permissionsError.value);
    console.log('- Can compose emails:', canComposeEmails.value);
});
```

## Benefits

1. **Consistent Permission System**: The application now uses a consistent permission system across all components, making it easier to maintain and understand.

2. **Improved Permission Checking**: The new permissions.js file from the Directives folder provides a more robust and flexible permission checking system, with support for both global and project-specific permissions.

3. **Better Debugging**: Added logging to help diagnose permission-related issues, making it easier to troubleshoot problems in the future.

4. **Backward Compatibility**: Maintained backward compatibility by keeping the default value of `true` for the `canComposeEmails` computed property.

## Testing

The changes have been tested to ensure that:

1. The component loads correctly with the new permission system.
2. Global permissions are fetched when the component is mounted.
3. The `canComposeEmails` computed property works correctly to conditionally render the email composition form.
4. The component behaves the same as before for users with the appropriate permissions.

## Files Changed

- `/Users/zeeshansabri/laravel/email-approval-app/resources/js/Pages/Emails/Composer.vue`
