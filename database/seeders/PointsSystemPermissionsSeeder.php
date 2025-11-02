<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PointsSystemPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permission categories and permissions for the points system
        $permissionsByCategory = [
            'Project Tier Management' => [
                'view_project_tiers' => 'View project tiers',
                'create_project_tiers' => 'Create new project tiers',
                'edit_project_tiers' => 'Edit project tiers',
                'delete_project_tiers' => 'Delete project tiers',
                'assign_project_tiers' => 'Assign tiers to projects',
            ],
            'Kudos Management' => [
                'view_kudos' => 'View kudos',
                'create_kudos' => 'Create new kudos',
                'approve_kudos' => 'Approve or reject kudos',
                'view_own_kudos' => 'View own kudos (sent and received)',
                'view_all_kudos' => 'View all kudos in the system',
            ],
            'Points System' => [
                'view_points_ledger' => 'View points ledger entries',
                'manage_points' => 'Manually add or adjust points',
                'view_monthly_budgets' => 'View monthly points budgets',
                'manage_monthly_budgets' => 'Manage monthly points budgets',
                'view_monthly_points' => 'View monthly points summaries',
                'view_own_points' => 'View own points',
            ],
        ];

        // Create permissions
        $allPermissions = [];
        foreach ($permissionsByCategory as $category => $permissions) {
            foreach ($permissions as $slug => $description) {
                $name = Str::title(str_replace('_', ' ', $slug));

                // Check if permission already exists
                $existingPermission = Permission::where('slug', $slug)->first();
                if (! $existingPermission) {
                    $permission = Permission::create([
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description,
                        'category' => $category,
                    ]);
                    $allPermissions[$slug] = $permission;
                } else {
                    $allPermissions[$slug] = $existingPermission;
                }
            }
        }

        // Get roles
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $employeeRole = Role::where('slug', 'employee')->first();
        $contractorRole = Role::where('slug', 'contractor')->first();

        if (! $superAdminRole || ! $managerRole || ! $employeeRole || ! $contractorRole) {
            $this->command->error('Required roles not found. Please run RolePermissionSeeder first.');

            return;
        }

        // Assign permissions to roles

        // Super Admin gets all permissions
        foreach ($allPermissions as $permission) {
            $superAdminRole->assignPermission($permission);
        }

        // Manager permissions
        $managerPermissions = [
            // Project Tier Management
            'view_project_tiers', 'create_project_tiers', 'edit_project_tiers',
            'delete_project_tiers', 'assign_project_tiers',

            // Kudos Management
            'view_kudos', 'create_kudos', 'approve_kudos',
            'view_own_kudos', 'view_all_kudos',

            // Points System
            'view_points_ledger', 'manage_points',
            'view_monthly_budgets', 'manage_monthly_budgets',
            'view_monthly_points', 'view_own_points',
        ];

        foreach ($managerPermissions as $slug) {
            if (isset($allPermissions[$slug])) {
                $managerRole->assignPermission($allPermissions[$slug]);
            }
        }

        // Employee permissions
        $employeePermissions = [
            // Project Tier Management
            'view_project_tiers',

            // Kudos Management
            'view_kudos', 'create_kudos', 'view_own_kudos',

            // Points System
            'view_points_ledger', 'view_monthly_points', 'view_own_points',
        ];

        foreach ($employeePermissions as $slug) {
            if (isset($allPermissions[$slug])) {
                $employeeRole->assignPermission($allPermissions[$slug]);
            }
        }

        // Contractor permissions
        $contractorPermissions = [
            // Kudos Management
            'view_own_kudos', 'create_kudos',

            // Points System
            'view_own_points',
        ];

        foreach ($contractorPermissions as $slug) {
            if (isset($allPermissions[$slug])) {
                $contractorRole->assignPermission($allPermissions[$slug]);
            }
        }
    }
}
