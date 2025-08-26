<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_manager_id')->nullable()->after('client_id')->constrained('users')->nullOnDelete();
            $table->foreignId('project_admin_id')->nullable()->after('project_manager_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Drop foreign keys first if necessary
            if (Schema::hasColumn('projects', 'project_admin_id')) {
                $table->dropConstrainedForeignId('project_admin_id');
            }
            if (Schema::hasColumn('projects', 'project_manager_id')) {
                $table->dropConstrainedForeignId('project_manager_id');
            }
        });
    }
};
