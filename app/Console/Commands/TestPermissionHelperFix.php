<?php

namespace App\Console\Commands;

use App\Helpers\PermissionHelper;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestPermissionHelperFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:permission-helper-fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the fix for PermissionHelper::getUsersWithProjectPermission method';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Testing PermissionHelper::getUsersWithProjectPermission fix\n");

        // Get a project ID to test with
        $project = Project::first();

        if (! $project) {
            $this->error('No projects found in the database. Please create a project first.');

            return 1;
        }

        $this->info("Using project ID: {$project->id}");

        // Get a permission slug to test with
        $permission = DB::table('permissions')->first();

        if (! $permission) {
            $this->error('No permissions found in the database. Please create permissions first.');

            return 1;
        }

        $permissionSlug = $permission->slug;
        $this->info("Using permission slug: {$permissionSlug}\n");

        // Test the fixed method
        try {
            $this->info('Testing getUsersWithProjectPermission method...');
            $users = PermissionHelper::getUsersWithProjectPermission($permissionSlug, $project->id);
            $this->info('Success! Found '.$users->count()." users with project permission '{$permissionSlug}' for project {$project->id}");

            // Display the users
            if ($users->count() > 0) {
                $this->info("\nUsers with permission:");
                foreach ($users as $user) {
                    $this->line("- {$user->name} (ID: {$user->id})");
                }
            }

            // Test getAllUsersWithPermission method which uses getUsersWithProjectPermission
            $this->info("\nTesting getAllUsersWithPermission method...");
            $allUsers = PermissionHelper::getAllUsersWithPermission($permissionSlug, $project->id);
            $this->info('Success! Found '.$allUsers->count()." users with permission '{$permissionSlug}' (global or project-specific) for project {$project->id}");

            // Display the users
            if ($allUsers->count() > 0) {
                $this->info("\nAll users with permission (global or project-specific):");
                foreach ($allUsers as $user) {
                    $this->line("- {$user->name} (ID: {$user->id})");
                }
            }

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return 1;
        }

        $this->info("\nTest completed successfully.");

        return 0;
    }
}
