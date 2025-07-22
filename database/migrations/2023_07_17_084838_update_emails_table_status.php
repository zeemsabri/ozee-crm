<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the status enum to include 'pending_approval_received'
        DB::statement("ALTER TABLE emails MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'rejected', 'sent', 'received', 'pending_approval_received') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the status enum to exclude 'pending_approval_received'
        DB::statement("ALTER TABLE emails MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'rejected', 'sent', 'received') DEFAULT 'draft'");
    }
};
