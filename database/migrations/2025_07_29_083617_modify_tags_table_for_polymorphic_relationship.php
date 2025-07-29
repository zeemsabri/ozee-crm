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
        Schema::table('tags', function (Blueprint $table) {
            // Add slug field
            $table->string('slug')->unique()->after('name');

            // Make name field unique
            $table->unique('name');

            // Drop the created_by_user_id foreign key constraint
            $table->dropForeign(['created_by_user_id']);

            // Drop the created_by_user_id column
            $table->dropColumn('created_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            // Add back the created_by_user_id column
            $table->foreignId('created_by_user_id')->constrained('users');

            // Remove the unique constraint from name
            $table->dropUnique(['name']);

            // Drop the slug column
            $table->dropColumn('slug');
        });
    }
};
