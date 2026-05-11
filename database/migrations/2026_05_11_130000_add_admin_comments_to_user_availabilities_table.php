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
            $table->text('admin_comments')->nullable()->after('left_early_reason_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_availabilities', function (Blueprint $table) {
            $table->dropColumn('admin_comments');
        });
    }
};
