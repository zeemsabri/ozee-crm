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
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_link_code')->nullable()->unique()->after('email');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('telegram_link_code')->nullable()->unique()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telegram_link_code');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('telegram_link_code');
        });
    }
};
