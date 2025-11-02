<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_client', function (Blueprint $table) {
            // Add role_id column as a foreign key to roles table
            $table->foreignId('role_id')->nullable()->after('role');
        });

        // Update existing records to set role_id based on role string
        DB::statement('UPDATE project_client SET role_id = (
            SELECT id FROM roles
            WHERE LOWER(name) = LOWER(project_client.role)
            OR LOWER(slug) = LOWER(REPLACE(project_client.role, " ", "-"))
            LIMIT 1
        )');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_client', function (Blueprint $table) {
            // Drop the role_id column
            $table->dropColumn('role_id');
        });
    }
};
