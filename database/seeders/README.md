# Role and Permission Seeder

This directory contains seeders to populate the database with initial roles and permissions.

## Available Seeders

- `RolePermissionSeeder.php`: Creates default roles and permissions and assigns them appropriately.

## Running the Seeders

To run the seeders, use the following Artisan command:

```bash
php artisan db:seed
```

This will run the `DatabaseSeeder` which includes:
1. Creating a test user
2. Creating a super admin user
3. Running the `RolePermissionSeeder`

If you want to run only the `RolePermissionSeeder`, use:

```bash
php artisan db:seed --class=Database\\Seeders\\RolePermissionSeeder
```

## Default Roles

The seeder creates the following roles:

1. **Super Admin**: Has access to all features and permissions
2. **Manager**: Has access to most features except sensitive information
3. **Employee**: Regular employee with limited access
4. **Contractor**: External contractor with very limited access

## Permission Categories

Permissions are organized into the following categories:

1. **Client Management**: Permissions related to viewing, creating, editing, and deleting clients
2. **User Management**: Permissions related to viewing, creating, editing, and deleting users
3. **Project Management**: Permissions related to viewing, creating, editing, and deleting projects
4. **Email Management**: Permissions related to composing, viewing, approving, and rejecting emails
5. **Role & Permission Management**: Permissions related to managing roles and permissions
6. **Dashboard**: Permissions related to viewing the dashboard and statistics

## Role-Permission Assignments

- **Super Admin**: All permissions
- **Manager**: Most permissions except role management and deletion permissions
- **Employee**: Basic viewing permissions and email composition
- **Contractor**: Very limited permissions focused on assigned projects and email composition
