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
            // Make project_id nullable since notes can now belong to other entities
            $table->foreignId('project_id')->nullable()->change();

            // Add polymorphic columns
            $table->unsignedBigInteger('noteable_id')->nullable()->after('project_id');
            $table->string('noteable_type')->nullable()->after('noteable_id');

            // Add index for better query performance
            $table->index(['noteable_id', 'noteable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_notes', function (Blueprint $table) {
            // Remove the index
            $table->dropIndex(['noteable_id', 'noteable_type']);

            // Remove the polymorphic columns
            $table->dropColumn(['noteable_id', 'noteable_type']);

            // Make project_id required again
            $table->foreignId('project_id')->nullable(false)->change();
        });
    }
};
