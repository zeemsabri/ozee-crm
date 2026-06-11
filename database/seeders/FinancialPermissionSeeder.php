<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinancialPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionsByCategory = [
            'Project Expendables' => [
                'add_expendables' => 'Access the Project Expendables dashboard',
                'view_project_expendable' => 'View project expendables data',
                'manage_project_expendable' => 'Create and manage project expendables',
                'view_project_expendables_proposals' => 'View the Proposals tab',
                'view_project_expendables_planning' => 'View the Planning tab',
                'view_project_expendables_execution' => 'View the Execution tab',
                'view_project_expendables_financials' => 'View the Financials tab',
            ],
            'Financials' => [
                'view_project_bills' => 'View project bills and bill details',
                'create_project_bills' => 'Create new project bills',
                'edit_project_bills' => 'Edit bill details before approval',
                'approve_project_bills' => 'Approve project bills',
                'void_project_bills' => 'Void approved project bills',
                'link_xero_contractors' => 'Link project contractors to Xero',
                'view_project_invoices' => 'View project invoices and invoice details',
                'create_project_invoices' => 'Create new project invoices',
                'edit_project_invoices' => 'Edit invoice details before approval',
                'approve_project_invoices' => 'Approve project invoices',
                'void_project_invoices' => 'Void approved project invoices',
                'configure_xero_settings' => 'Configure Xero settings',
            ],
        ];

        $allPermissions = [];

        foreach ($permissionsByCategory as $category => $permissions) {
            foreach ($permissions as $slug => $description) {
                $permission = Permission::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => Str::title(str_replace('_', ' ', $slug)),
                        'description' => $description,
                        'category' => $category,
                    ]
                );

                $allPermissions[$slug] = $permission;
            }
        }

        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $employeeRole = Role::where('slug', 'employee')->first();
        $contractorRole = Role::where('slug', 'contractor')->first();

        if (! $superAdminRole || ! $managerRole || ! $employeeRole || ! $contractorRole) {
            $this->command?->warn('Skipping FinancialPermissionSeeder because required roles were not found.');

            return;
        }

        foreach ($allPermissions as $permission) {
            $superAdminRole->assignPermission($permission);
        }

        $managerPermissions = [
            'add_expendables',
            'view_project_expendable',
            'manage_project_expendable',
            'view_project_expendables_proposals',
            'view_project_expendables_planning',
            'view_project_expendables_execution',
            'view_project_expendables_financials',
            'view_project_bills',
            'create_project_bills',
            'edit_project_bills',
            'approve_project_bills',
            'link_xero_contractors',
            'view_project_invoices',
            'create_project_invoices',
            'edit_project_invoices',
            'approve_project_invoices',
            'void_project_invoices',
        ];

        $employeePermissions = [
            'add_expendables',
            'view_project_expendable',
            'view_project_expendables_proposals',
            'view_project_expendables_planning',
            'view_project_expendables_execution',
        ];

        $contractorPermissions = [
            'view_project_expendable',
        ];

        foreach ($managerPermissions as $slug) {
            if (isset($allPermissions[$slug])) {
                $managerRole->assignPermission($allPermissions[$slug]);
            }
        }

        foreach ($employeePermissions as $slug) {
            if (isset($allPermissions[$slug])) {
                $employeeRole->assignPermission($allPermissions[$slug]);
            }
        }

        foreach ($contractorPermissions as $slug) {
            if (isset($allPermissions[$slug])) {
                $contractorRole->assignPermission($allPermissions[$slug]);
            }
        }
    }
}
