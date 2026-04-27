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
        Schema::table('email_apps', function (Blueprint $table) {
            $table->unsignedInteger('hourly_send_limit')
                ->default(100)
                ->after('delivery_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_apps', function (Blueprint $table) {
            $table->dropColumn('hourly_send_limit');
        });
    }
};
