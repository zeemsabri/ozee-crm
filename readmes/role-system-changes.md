# Role System Changes

## Issue

The application had an inconsistency in how user roles were stored:

1. Each user had one primary role stored in the `users` table as a string field (`role`).
2. Users could have different roles for different projects, stored in the `project_user` table as a string field (`role`).
3. The application also had a many-to-many relationship between users and roles through the `user_role` table, which was inconsistent with the fact that each user should have only one application-wide role.

## Changes Made

### Database Schema Updates

1. **Added `role_id` to users table**
   - Added a foreign key column `role_id` that references the `roles` table
   - Kept the existing `role` string column for backward compatibility

2. **Added `role_id` to project_user table**
   - Added a foreign key column `role_id` that references the `roles` table
   - Kept the existing `role` string column for backward compatibility

3. **Created a migration to populate role_id fields**
   - Mapped existing role strings to role IDs in the `users` table
   - Mapped project roles to application roles in the `project_user` table

### Model Updates

1. **Updated User model**
   - Added a `belongsTo` relationship to Role via `role_id`
   - Updated permission methods to check the direct role relationship first
   - Kept the existing many-to-many relationship for backward compatibility
   - Updated the `$fillable` and `$casts` properties to include `role_id`

2. **Updated Project model**
   - Updated the `users()` relationship to include `role_id` in the pivot data

## Why This Resolves the Issue

These changes ensure that:

1. **Consistent Role System**: Each user now has a single application-wide role stored as a reference to the `roles` table.
2. **Project-Specific Roles**: Users can still have different roles for different projects, now properly referenced by ID.
3. **Backward Compatibility**: The existing string columns are maintained for backward compatibility.
4. **Improved Performance**: Using foreign keys improves query performance and maintains data integrity.
5. **Simplified Permission Checks**: Permission checks now first look at the direct role relationship before falling back to the many-to-many relationship.

## Testing

A test script was created to verify the changes. The script tests:

1. Role ID values for users with different roles
2. The `role()` relationship to ensure it returns the correct role
3. The `hasPermission()` method to ensure it works with the new role system
4. Project roles to ensure they are correctly stored and retrieved

## Migration Path

The changes were implemented in a way that maintains backward compatibility:

1. Existing code that uses the string `role` column will continue to work
2. New code can use the `role_id` column and the `role()` relationship
3. Permission checks will work with both the old and new systems

Over time, the application can be fully migrated to use only the new role system, and the old columns and relationships can be removed.
