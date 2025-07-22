<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing application roles
        DB::table('roles')->whereIn('slug', ['super-admin', 'manager', 'employee', 'contractor'])
            ->update(['type' => 'application']);

        // Create client roles
        $clientRoles = [
            [
                'name' => 'Client Admin',
                'slug' => 'client-admin',
                'description' => 'Client administrator with full access to client features',
                'type' => 'client',
            ],
            [
                'name' => 'Client User',
                'slug' => 'client-user',
                'description' => 'Regular client user with limited access',
                'type' => 'client',
            ],
            [
                'name' => 'Client Viewer',
                'slug' => 'client-viewer',
                'description' => 'Client with view-only access',
                'type' => 'client',
            ],
        ];

        // Create project roles
        $projectRoles = [
            [
                'name' => 'Project Manager',
                'slug' => 'project-manager',
                'description' => 'Project manager with full access to project features',
                'type' => 'project',
            ],
            [
                'name' => 'Project Member',
                'slug' => 'project-member',
                'description' => 'Regular project member with edit access',
                'type' => 'project',
            ],
            [
                'name' => 'Project Viewer',
                'slug' => 'project-viewer',
                'description' => 'Project member with view-only access',
                'type' => 'project',
            ],
        ];

        // Insert new roles
        foreach ($clientRoles as $role) {
            DB::table('roles')->insertOrIgnore($role);
        }

        foreach ($projectRoles as $role) {
            DB::table('roles')->insertOrIgnore($role);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set type to 'application' for all roles (default)
        DB::table('roles')->update(['type' => 'application']);

        // Remove the client and project roles we added
        DB::table('roles')->whereIn('slug', [
            'client-admin', 'client-user', 'client-viewer',
            'project-manager', 'project-member', 'project-viewer'
        ])->delete();
    }
};
