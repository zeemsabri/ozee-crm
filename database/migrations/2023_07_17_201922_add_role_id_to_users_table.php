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
        // Add role_id column to users table
        Schema::table('users', function (Blueprint $table) {
            // Add role_id column after the role column

        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
