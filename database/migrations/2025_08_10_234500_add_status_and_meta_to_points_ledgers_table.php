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
        Schema::table('points_ledgers', function (Blueprint $table) {
            // Status enum with default 'pending'
            $table->enum('status', [
                'pending',
                'refunded',
                'cancelled',
                'paid',
                'consumed',
                'rejected',
            ])->default('pending')->after('description');

            // JSON meta field for additional structured data
            $table->json('meta')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('points_ledgers', function (Blueprint $table) {
            $table->dropColumn(['status', 'meta']);
        });
    }
};
