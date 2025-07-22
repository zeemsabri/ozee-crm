# Client Authorization Changes

## Issue
The application was experiencing unauthorized errors when fetching clients. The issue was caused by the use of `authorizeResource(Client::class, 'client')` in the ClientController constructor, which automatically applies policy checks to all resource controller methods.

The problem was that contractors need to access clients associated with their projects, but they don't have the 'view_clients' permission according to the role-permission system.

## Changes Made

1. **Removed `authorizeResource` from ClientController constructor**
   - The automatic policy checks were preventing contractors from accessing clients
   - Replaced with manual authorization in each method

2. **Updated authorization logic in each controller method**
   - `index()`: Allow users with 'view_clients' permission to see all clients, and contractors to see clients associated with their projects
   - `show()`: Allow users with 'view_clients' permission to see any client, and contractors to see only clients associated with their projects
   - `store()`: Only allow users with 'create_clients' permission
   - `update()`: Only allow users with 'edit_clients' permission
   - `destroy()`: Only allow users with 'delete_clients' permission
   - `getEmail()`: Allow users with 'view_clients' permission to get any client's email, and contractors to get emails only for clients associated with their projects

3. **Fixed the User model's `getClientsAttribute` method**
   - Updated to return an Eloquent Collection instead of a Support Collection
   - Improved the query to properly handle the relationship between users, projects, and clients

## Why This Resolves the Issue

The changes allow contractors to access clients associated with their projects without requiring the 'view_clients' permission. This is achieved by:

1. **Role-based access control**: Each method now checks the user's role and permissions separately
2. **Project-based access for contractors**: Contractors can only access clients that are associated with their assigned projects
3. **Maintaining security**: Users still need appropriate permissions to perform actions like creating, updating, or deleting clients

## Testing

A test script was created to verify the changes. The results confirm that:

- Super Admins and Managers have the 'view_clients' permission and can access all clients
- Employees don't have the 'view_clients' permission (in our current setup) and cannot access clients
- Contractors don't have the 'view_clients' permission but can access clients associated with their projects

This approach maintains the security of the application while allowing contractors the limited access they need to perform their work.
