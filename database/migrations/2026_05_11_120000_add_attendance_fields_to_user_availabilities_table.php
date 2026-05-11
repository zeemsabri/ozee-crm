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
            $table->boolean('did_not_show_up')->default(false)->after('is_available');
            $table->boolean('was_late')->default(false)->after('did_not_show_up');
            $table->boolean('left_early')->default(false)->after('was_late');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_availabilities', function (Blueprint $table) {
            $table->dropColumn(['did_not_show_up', 'was_late', 'left_early']);
        });
    }
};