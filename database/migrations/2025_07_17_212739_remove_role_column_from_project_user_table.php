<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            // Remove the 'role' column if it exists
            if (Schema::hasColumn('project_user', 'role')) {
                $table->dropColumn('role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            // Add back the 'role' column if it doesn't exist
            if (!Schema::hasColumn('project_user', 'role')) {
                $table->string('role')->nullable();
            }
        });
    }
};
