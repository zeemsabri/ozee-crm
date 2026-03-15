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
        Schema::table('magic_links', function (Blueprint $table) {
            $table->json('whitelist')->nullable()->after('token');
            $table->integer('max_uses')->nullable()->after('whitelist');
            $table->integer('uses_count')->default(0)->after('max_uses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('magic_links', function (Blueprint $table) {
            $table->dropColumn(['whitelist', 'max_uses', 'uses_count']);
        });
    }
};
