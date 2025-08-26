# Policy Updates Documentation

## Overview

This document outlines the changes made to the policy files in the Email Approval App to replace hardcoded role checks with dynamic permission-based authorization. The goal was to make the authorization system more flexible and maintainable by leveraging the existing role-permission system.

## Changes Made

### 1. UserPolicy.php

- Updated `viewAny()` to check for `view_users` permission instead of hardcoded roles
- Updated `view()` to check for `view_users` permission while maintaining the rule that users can view their own profile
- Updated `create()` to check for `create_users` permission
- Updated `update()` to check for `edit_users` permission while maintaining role-specific restrictions for managers
- Updated `delete()` to check for `delete_users` permission

### 2. ProjectPolicy.php

- Updated `view()` to check for `view_projects` permission while maintaining the rule that contractors can only view projects they're assigned to
- Updated `create()` to check for `create_projects` permission
- Updated `update()` to check for `edit_projects` permission
- Updated `delete()` to check for `delete_projects` permission
- Updated `attachAnyUser()` and `detachAnyUser()` to check for `edit_projects` permission

### 3. ClientPolicy.php

- Updated `viewAny()` to check for `view_clients` permission
- Updated `view()` to check for `view_clients` permission while maintaining the rule that contractors can only view clients related to their projects
- Updated `create()` to check for `create_clients` permission
- Updated `update()` to check for `edit_clients` permission
- Updated `delete()` to check for `delete_clients` permission

### 4. EmailPolicy.php

- Updated `viewAny()` to check for `view_emails` permission
- Updated `view()` to check for `view_emails` permission while maintaining the rule that contractors can only view their own emails
- Updated `create()` to check for `compose_emails` permission
- Updated `update()` to check for permissions based on user role and email status
- Updated `resubmit()` to check for `resubmit_emails` permission
- Updated `approve()` and `reject()` to check for `approve_emails` permission
- Updated `editAndApprove()` to check for both `approve_emails` and `edit_emails` permissions

## Recommendations for Future Improvements

1. **Consistency in Role Slugs**: There's an inconsistency between how roles are stored in the database (with hyphens, e.g., 'super-admin') and how they're checked in the code (with underscores, e.g., 'super_admin'). This should be standardized to avoid confusion.

2. **Middleware Improvements**: The `CheckPermission` middleware has been updated to remove the hardcoded check for `isSuperAdmin()` and now relies solely on the permission system. It now assumes that super admins are handled by the Gate::before method in AuthServiceProvider.

3. **AuthServiceProvider Updates**: The `Gate::before` method in AuthServiceProvider.php uses `isSuperAdmin()` which is a hardcoded role check. Consider updating it to use a more flexible approach, such as checking for a special permission like 'bypass_policies'.

4. **Complete Policy Registration**: The `$policies` array in AuthServiceProvider.php has been updated to register all policies, including UserPolicy and EmailPolicy which were previously missing. This ensures that all policies are properly used by Laravel's authorization system.

5. **Testing**: Implement comprehensive tests for the authorization system to ensure that the permission-based checks work correctly for all user roles.

6. **Frontend Updates**: Review the frontend components to ensure they're using the permission system consistently for showing/hiding UI elements.

7. **Documentation**: Keep this documentation updated as the authorization system evolves.

## Conclusion

By replacing hardcoded role checks with dynamic permission-based authorization, the application is now more flexible and maintainable. Permissions can be assigned to roles as needed without changing the code, making it easier to adjust access control as requirements change.
