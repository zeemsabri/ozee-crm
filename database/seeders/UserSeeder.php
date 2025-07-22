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
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role_id' => $superAdminRole->id,
            ]
        );

        // Create Manager user
        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('password'),
                'role_id' => $managerRole->id,
            ]
        );

        // Create Employee user
        $employee = User::firstOrCreate(
            ['email' => 'employee@example.com'],
            [
                'name' => 'Employee User',
                'password' => Hash::make('password'),
                'role_id' => $employeeRole->id,
            ]
        );

        // Create Contractor user
        $contractor = User::firstOrCreate(
            ['email' => 'contractor@example.com'],
            [
                'name' => 'Contractor User',
                'password' => Hash::make('password'),
                'role_id' => $contractorRole->id,
            ]
        );


        // Output information
        $this->command->info('Users created successfully!');
        $this->command->info('Super Admin: admin@example.com / password');
        $this->command->info('Manager: manager@example.com / password');
        $this->command->info('Employee: employee@example.com / password');
        $this->command->info('Contractor: contractor@example.com / password');
    }
}
