<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;

class AddEditCredentialPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'edit_credential'],
            [
                'name' => 'Unlock and edit vault credentials',
                'description' => 'Permission to unlock and edit project vault credentials',
                'category' => 'Client Management',
            ]
        );

        $superAdmin = Role::where('slug', 'super-admin')->first();
        if ($superAdmin && !$superAdmin->permissions()->where('slug', 'edit_credential')->exists()) {
            $superAdmin->permissions()->attach($permission->id);
        }

        $manager = Role::where('slug', 'manager')->first();
        if ($manager && !$manager->permissions()->where('slug', 'edit_credential')->exists()) {
            $manager->permissions()->attach($permission->id);
        }
    }
}
