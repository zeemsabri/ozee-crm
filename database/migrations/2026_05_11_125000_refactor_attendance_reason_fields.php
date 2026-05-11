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
            // Add three separate reason fields
            $table->unsignedBigInteger('did_not_show_up_reason_category_id')->nullable()->after('did_not_show_up');
            $table->unsignedBigInteger('was_late_reason_category_id')->nullable()->after('was_late');
            $table->unsignedBigInteger('left_early_reason_category_id')->nullable()->after('left_early');

            // Add foreign key constraints
            $table->foreign('did_not_show_up_reason_category_id')
                ->references('id')
                ->on('categories')
                ->nullableOnDelete();
            $table->foreign('was_late_reason_category_id')
                ->references('id')
                ->on('categories')
                ->nullableOnDelete();
            $table->foreign('left_early_reason_category_id')
                ->references('id')
                ->on('categories')
                ->nullableOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_availabilities', function (Blueprint $table) {
            $table->dropForeignKey(['did_not_show_up_reason_category_id']);
            $table->dropForeignKey(['was_late_reason_category_id']);
            $table->dropForeignKey(['left_early_reason_category_id']);
            $table->dropColumn(['did_not_show_up_reason_category_id', 'was_late_reason_category_id', 'left_early_reason_category_id']);
        });
    }
};
