<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        //        DB::table('permissions')->truncate();
        //        DB::table('roles')->truncate();
        //        DB::table('role_permission')->truncate();

        // Create application roles
        $superAdminRole = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Super Administrator with full access to all features',
            'type' => 'application',
        ]);

        $managerRole = Role::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Manager with access to most features except sensitive information',
            'type' => 'application',
        ]);

        $employeeRole = Role::create([
            'name' => 'Employee',
            'slug' => 'employee',
            'description' => 'Regular employee with limited access',
            'type' => 'application',
        ]);

        $contractorRole = Role::create([
            'name' => 'Contractor',
            'slug' => 'contractor',
            'description' => 'External contractor with very limited access',
            'type' => 'application',
        ]);

        // Create client roles
        $clientAdminRole = Role::create([
            'name' => 'Client Admin',
            'slug' => 'client-admin',
            'description' => 'Client administrator with full access to client features',
            'type' => 'client',
        ]);

        $clientUserRole = Role::create([
            'name' => 'Client User',
            'slug' => 'client-user',
            'description' => 'Regular client user with limited access',
            'type' => 'client',
        ]);

        $clientViewerRole = Role::create([
            'name' => 'Client Viewer',
            'slug' => 'client-viewer',
            'description' => 'Client with view-only access',
            'type' => 'client',
        ]);

        // Create project roles
        $projectManagerRole = Role::create([
            'name' => 'Project Manager',
            'slug' => 'project-manager',
            'description' => 'Project manager with full access to project features',
            'type' => 'project',
        ]);

        $projectMemberRole = Role::create([
            'name' => 'Project Member',
            'slug' => 'project-member',
            'description' => 'Regular project member with edit access',
            'type' => 'project',
        ]);

        $projectViewerRole = Role::create([
            'name' => 'Project Viewer',
            'slug' => 'project-viewer',
            'description' => 'Project member with view-only access',
            'type' => 'project',
        ]);

        // Create permission categories and permissions
        $permissionsByCategory = [
            'Client Management' => [
                'view_clients' => 'View client list and details',
                'create_clients' => 'Create new clients',
                'edit_clients' => 'Edit client information',
                'delete_clients' => 'Delete clients',
                'view_client_financial' => 'View client financial information',
                'view_client_contacts' => 'View client contact details',
            ],
            'User Management' => [
                'view_users' => 'View user list and details',
                'create_users' => 'Create new users',
                'edit_users' => 'Edit user information',
                'delete_users' => 'Delete users',
                'assign_roles' => 'Assign roles to users',
            ],
            'Project Management' => [
                'view_projects' => 'View project list and details',
                'create_projects' => 'Create new projects',
                'edit_projects' => 'Edit project information',
                'delete_projects' => 'Delete projects',
                'view_project_financial' => 'View project financial information',
                'view_project_transactions' => 'View project transactions',
                'manage_projects' => 'Manage projects',
                'view_project_documents' => 'View project documents',
                'upload_project_documents' => 'Upload documents to projects',
                'manage_project_expenses' => 'Manage project expenses',
                'manage_project_income' => 'Manage project income',
                'manage_project_services_and_payments' => 'Manage project services and payments',
                'view_project_services_and_payments' => 'View project services and payments',
                'add_project_notes' => 'Add notes to projects',
                'view_project_notes' => 'View project notes',
                'manage_project_users' => 'Manage users assigned to projects',
                'view_project_users' => 'View users assigned to projects',
                'manage_project_clients' => 'Manage clients assigned to projects',
                'view_project_clients' => 'View clients assigned to projects',
            ],
            'Email Management' => [
                'compose_emails' => 'Compose new emails',
                'view_emails' => 'View emails',
                'approve_emails' => 'Approve or reject emails',
                'view_rejected_emails' => 'View rejected emails',
                'resubmit_emails' => 'Resubmit rejected emails',
            ],
            'Role & Permission Management' => [
                'manage_roles' => 'Manage roles and permissions',
                'view_roles' => 'View roles',
                'create_roles' => 'Create new roles',
                'edit_roles' => 'Edit roles',
                'delete_roles' => 'Delete roles',
                'assign_permissions' => 'Assign permissions to roles',
            ],
            'Dashboard' => [
                'view_dashboard' => 'View dashboard',
                'view_statistics' => 'View statistics and reports',
            ],
        ];

        // Create permissions
        $allPermissions = [];
        foreach ($permissionsByCategory as $category => $permissions) {
            foreach ($permissions as $slug => $description) {
                $name = Str::title(str_replace('_', ' ', $slug));
                $permission = Permission::create([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'category' => $category,
                ]);
                $allPermissions[$slug] = $permission;
            }
        }

        // Assign permissions to roles

        // Super Admin gets all permissions
        foreach ($allPermissions as $permission) {
            $superAdminRole->assignPermission($permission);
        }

        // Manager permissions
        $managerPermissions = [
            // Client Management
            'view_clients', 'create_clients', 'edit_clients',
            'view_client_financial', 'view_client_contacts',

            // User Management
            'view_users', 'create_users', 'edit_users',

            // Project Management
            'view_projects', 'create_projects', 'edit_projects',
            'view_project_financial', 'view_project_transactions',
            'manage_projects', 'view_project_documents', 'upload_project_documents',
            'manage_project_expenses', 'manage_project_income',
            'manage_project_services_and_payments', 'view_project_services_and_payments',
            'add_project_notes', 'view_project_notes',
            'manage_project_users', 'view_project_users',
            'manage_project_clients', 'view_project_clients',

            // Email Management
            'compose_emails', 'view_emails', 'approve_emails',
            'view_rejected_emails', 'resubmit_emails',

            // Dashboard
            'view_dashboard', 'view_statistics',
        ];

        foreach ($managerPermissions as $slug) {
            if (isset($allPermissions[$slug])) {
                $managerRole->assignPermission($allPermissions[$slug]);
            }
        }

        // Employee permissions
        $employeePermissions = [
            // Client Management
            'view_clients',

            // Project Management
            'view_projects', 'view_project_documents',
            'view_project_financial', 'view_project_transactions',
            'view_project_services_and_payments', 'view_project_notes',
            'view_project_users', 'view_project_clients',

            // Email Management
            'compose_emails', 'view_emails', 'view_rejected_emails', 'resubmit_emails',

            // Dashboard
            'view_dashboard',
        ];

        foreach ($employeePermissions as $slug) {
            if (isset($allPermissions[$slug])) {
                $employeeRole->assignPermission($allPermissions[$slug]);
            }
        }

        // Contractor permissions
        $contractorPermissions = [
            // Project Management (only assigned projects)
            'view_projects', 'view_project_documents',
            'view_project_services_and_payments', 'view_project_notes',
            'view_project_users', 'view_project_clients',

            // Email Management
            'compose_emails', 'view_emails', 'view_rejected_emails', 'resubmit_emails',

            // Dashboard
            'view_dashboard',
        ];

        foreach ($contractorPermissions as $slug) {
            if (isset($allPermissions[$slug])) {
                $contractorRole->assignPermission($allPermissions[$slug]);
            }
        }

        // Assign Super Admin role to the first user (if exists)
        $adminUser = User::where('email', 'info@ozeeweb.com.au')->first();
        //        if (!$adminUser) {
        //            $adminUser = User::where('role', 'super_admin')->first();
        //        }

        if ($adminUser) {
            $adminUser->assignRole($superAdminRole);
        }
    }
}
