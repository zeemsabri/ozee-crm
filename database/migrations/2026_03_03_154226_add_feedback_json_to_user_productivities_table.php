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
        Schema::table('user_productivities', function (Blueprint $table) {
            $table->json('feedback_json')->nullable()->after('ai_report_json');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_productivities', function (Blueprint $table) {
            $table->dropColumn('feedback_json');
        });
    }
};
