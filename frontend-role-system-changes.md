# Frontend Role System Changes

## Issue

The frontend was throwing errors because it was still using the old role structure, while the backend had been updated to use a new role system with `role_id` references to the `roles` table instead of string-based roles.

## Changes Made

### Backend Changes

1. **Updated User Model**
   - Added `protected $with = ['role']` to always load the role relationship
   - Added `protected $appends = ['role_data']` to include role data in JSON serialization
   - Added `getRoleDataAttribute()` method to provide structured role data including id, name, and slug

### Frontend Changes

1. **AuthenticatedLayout.vue**
   - Updated role checks to use both `role_data.slug` and the legacy `role` string
   - Updated role display in navigation dropdown to use `role_data.name` with fallback to legacy format
   - Updated mobile view role display to use `role_data.name` with fallback

2. **Users/Index.vue**
   - Updated permission checks to use both `role_data.slug` and the legacy `role` string
   - Updated role display in users table to use `role_data.name` with fallback
   - Updated role check in Edit button visibility to use both role systems
   - Updated role display in delete confirmation modal
   - Updated `fetchRoles` function to use `role_id` as the value for dropdown options
   - Updated `userForm` to include `role_id`
   - Updated `openCreateModal` and `openEditModal` functions to set both `role_id` and `role`
   - Updated role dropdowns in create and edit forms to use `role_id`
   - Added `updateRoleString` function to keep the role string in sync with `role_id`

## Why This Resolves the Issue

These changes ensure that:

1. **Backward Compatibility**: The frontend can work with both the new role system (using `role_id` and `role_data`) and the legacy system (using `role` string)
2. **Consistent Role Display**: Role names are displayed consistently throughout the application
3. **Proper Role Selection**: When creating or editing users, the correct role is selected and sent to the backend
4. **Proper Permission Checks**: Role-based permission checks work correctly with both role systems

## Testing

The changes have been tested to ensure:

1. The navigation bar correctly displays the user's role
2. Permission-based UI elements (like menu items) are correctly shown/hidden
3. The users table correctly displays role information
4. Creating and editing users works with the new role system
5. All role-based checks function correctly

## Migration Path

The changes were implemented in a way that maintains backward compatibility:

1. Existing code that uses the string `role` property will continue to work
2. New code can use the `role_data` object and `role_id` property
3. Over time, the application can be fully migrated to use only the new role system
