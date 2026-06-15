<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE emails MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'rejected', 'sent', 'received', 'pending_approval_received', 'auto_send', 'unknown', 'pending', 'delayed') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE emails MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'rejected', 'sent', 'received', 'pending_approval_received', 'auto_send', 'unknown', 'pending') DEFAULT 'draft'");
    }
};
