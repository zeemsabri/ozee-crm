<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class ExtensionBypassPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::updateOrCreate(
            ['slug' => 'by_pass_extension'],
            [
                'name' => 'By Pass Extension',
                'description' => 'Allows users to login and stay active without the mandatory Chrome extension check.',
                'category' => 'User Management'
            ]
        );

        // Assign to Super Admin by default
        $superAdmin = Role::where('slug', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->assignPermission($permission);
        }
    }
}
