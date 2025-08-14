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
        Schema::create('monthly_budgets', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('month');
            $table->decimal('total_budget_pkr', 10, 2);
            $table->decimal('consistent_contributor_pool_pkr', 10, 2);
            $table->decimal('high_achiever_pool_pkr', 10, 2);
            $table->decimal('team_total_points', 10, 2);
            $table->decimal('points_value_pkr', 10, 4);
            $table->decimal('most_improved_award_pkr', 10, 2);
            $table->decimal('first_place_award_pkr', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_budgets');
    }
};
