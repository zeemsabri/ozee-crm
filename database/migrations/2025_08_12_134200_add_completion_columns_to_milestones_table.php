<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->timestamp('mark_completed_at')->nullable()->after('actual_completion_date');
            $table->timestamp('approved_at')->nullable()->after('mark_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn(['mark_completed_at', 'approved_at']);
        });
    }
};
