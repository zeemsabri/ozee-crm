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
        Schema::table('emails', function (Blueprint $table) {
            // Drop the foreign key constraint on sender_id
            $table->dropForeign(['sender_id']);

            // Add sender_type column for polymorphic relationship
            $table->string('sender_type')->default('App\\Models\\User')->after('sender_id');

            // Update existing records to use User model as sender_type
            // DB::statement("UPDATE emails SET sender_type = 'App\\\\Models\\\\User'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Remove the sender_type column
            $table->dropColumn('sender_type');

            // Add back the foreign key constraint
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
