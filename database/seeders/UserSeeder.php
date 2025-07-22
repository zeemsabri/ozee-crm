<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if roles exist, if not, run the RolePermissionSeeder first
        if (Role::count() === 0) {
            $this->command->info('No roles found. Running RolePermissionSeeder first...');
            $this->call(RolePermissionSeeder::class);
        }

        // Get roles
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $employeeRole = Role::where('slug', 'employee')->first();
        $contractorRole = Role::where('slug', 'contractor')->first();

        // Verify roles exist
        if (!$superAdminRole || !$managerRole || !$employeeRole || !$contractorRole) {
            $this->command->error('Required roles not found. Please ensure RolePermissionSeeder has been run.');
            return;
        }

        // Create Super Admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'info@ozeeweb.com.au'],
            [
                'name' => 'Zeeshan Sabri',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
            ]
        );

        // Create Manager user
        $manager = User::firstOrCreate(
            ['email' => 'usama@ezysoft.solutions'],
            [
                'name' => 'Usama Saeed',
                'password' => Hash::make('password'),
                'role_id' => $managerRole->id,
            ]
        );

        // Create Employee user
        $employee = User::firstOrCreate(
            ['email' => 'dev1@ezysoft.solutions'],
            [
                'name' => 'Employee User',
                'password' => Hash::make('password'),
                'role_id' => $employeeRole->id,
            ]
        );

        // Create Contractor user
        $contractor = User::firstOrCreate(
            ['email' => 'dev2@ezysoft.solutions'],
            [
                'name' => 'Contractor User',
                'password' => Hash::make('password'),
                'role_id' => $contractorRole->id,
            ]
        );

    }
}
