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
        Schema::table('project_notes', function (Blueprint $table) {
            // First drop the existing type column
            $table->dropColumn('type');

            // Then add it back as an enum
            $table->enum('type', ['standup', 'kudos', 'general'])->default('general')->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_notes', function (Blueprint $table) {
            // First drop the enum type column
            $table->dropColumn('type');

            // Then add it back as a string
            $table->string('type')->default('note')->after('content');
        });
    }
};
