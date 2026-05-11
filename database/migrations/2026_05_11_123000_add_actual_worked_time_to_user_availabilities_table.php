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
        Schema::table('user_availabilities', function (Blueprint $table) {
            $table->time('actual_start_time')->nullable()->after('left_early');
            $table->time('actual_end_time')->nullable()->after('actual_start_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_availabilities', function (Blueprint $table) {
            $table->dropColumn(['actual_start_time', 'actual_end_time']);
        });
    }
};
