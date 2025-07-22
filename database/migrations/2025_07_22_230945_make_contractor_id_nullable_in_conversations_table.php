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
        Schema::table('conversations', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['contractor_id']);

            // Make the contractor_id column nullable
            $table->foreignId('contractor_id')->nullable()->change();

            // Add the foreign key constraint back
            $table->foreign('contractor_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['contractor_id']);

            // Make the contractor_id column non-nullable again
            $table->foreignId('contractor_id')->nullable(false)->change();

            // Add the foreign key constraint back
            $table->foreign('contractor_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
