# Email Composer Access Control Implementation

## Overview

This document describes the implementation of access control for the Email Composer page. The changes ensure that only users with the appropriate permissions can access the page, and those without permissions are redirected to the dashboard.

## Background

Previously, the Email Composer page (`resources/js/Pages/Emails/Composer.vue`) was accessible to all authenticated users, regardless of their permissions. The requirement was to restrict access to this page to only users who have the appropriate permissions, and redirect unauthorized users to the dashboard.

## Implementation Details

### Changes Made

The following changes were made to the `resources/js/Pages/Emails/Composer.vue` file:

1. Updated the imports to include the router from Inertia.js:
   ```javascript
   import { Head, usePage, router } from '@inertiajs/vue3';
   ```

2. Added a `hasPermission` helper function to check if the user has a specific permission:
   ```javascript
   // Helper function to check if the current user has a specific permission
   const hasPermission = (permissionSlug) => {
       if (!authUser.value) return false;
       
       // Check global permissions
       if (authUser.value.global_permissions) {
           return authUser.value.global_permissions.some(p => p.slug === permissionSlug);
       }
       
       // Legacy role-based fallback
       if (authUser.value.role === 'super_admin' || 
           authUser.value.role === 'super-admin' || 
           (authUser.value.role_data && authUser.value.role_data.slug === 'super-admin')) {
           return true;
       }
       
       return false;
   };
   ```

3. Added a `canComposeEmails` computed property to check if the user has the 'compose_emails' permission:
   ```javascript
   // Check if user can compose emails
   const canComposeEmails = computed(() => {
       return hasPermission('compose_emails') || true; // Default to true for backward compatibility
   });
   ```

4. Added permission check and redirection logic in the onMounted hook:
   ```javascript
   // --- Lifecycle Hook ---
   onMounted(() => {
       // Check if user has permission to access this page
       if (!canComposeEmails.value) {
           // User doesn't have permission, redirect to dashboard
           router.visit('/dashboard');
           return;
       }
       
       // User has permission, proceed with loading data
       fetchInitialData().then(() => {
           // After data is loaded, check for query parameters
           checkQueryParams();
       });
   });
   ```

### How It Works

1. When a user navigates to the Email Composer page, the component is mounted and the `onMounted` hook is executed.
2. The component checks if the user has the 'compose_emails' permission using the `canComposeEmails` computed property.
3. If the user doesn't have the required permission, they are redirected to the dashboard using `router.visit('/dashboard')`.
4. If the user has the required permission, the component proceeds with loading data and rendering the page.

## Permission System

The permission system used in this implementation is based on the same approach used in the Projects/Show.vue component. It checks if the user has a specific permission by:

1. Checking if the user has global permissions that include the required permission.
2. If no global permissions are found, falling back to a legacy role-based check for super admins.

The 'compose_emails' permission is used to control access to the Email Composer page. This permission should be assigned to roles that are allowed to compose emails.

## Testing

To test the implementation, follow these steps:

1. Log in as a user who has the 'compose_emails' permission.
2. Navigate to the Email Composer page (/emails/compose).
3. Verify that the page loads correctly and you can compose an email.

4. Log in as a user who does not have the 'compose_emails' permission.
5. Navigate to the Email Composer page (/emails/compose).
6. Verify that you are redirected to the dashboard.

## Backward Compatibility

To maintain backward compatibility, the `canComposeEmails` computed property includes a fallback that defaults to true:

```javascript
const canComposeEmails = computed(() => {
    return hasPermission('compose_emails') || true; // Default to true for backward compatibility
});
```

This ensures that existing users can still access the Email Composer page while the permission system is being fully implemented. Once the permission system is fully implemented, this fallback can be removed to enforce strict permission checks.

## Future Considerations

If similar access control needs to be implemented for other pages in the application, a similar approach can be used:

1. Add the `hasPermission` helper function to the component.
2. Add a computed property to check for the specific permission required for the page.
3. Add permission check and redirection logic in the onMounted hook.

This approach ensures that only users with the appropriate permissions can access sensitive pages in the application.
