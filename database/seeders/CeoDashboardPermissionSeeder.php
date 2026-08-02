<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CeoDashboardPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'view-ceo-dashboard'],
            [
                'name' => 'View CEO Dashboard',
                'description' => 'Allows the user to view the CEO Financial Dashboard',
                'category' => 'dashboard',
            ]
        );

        $superAdmin = \App\Models\Role::where('slug', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->assignPermission($permission);
        }
    }
}
