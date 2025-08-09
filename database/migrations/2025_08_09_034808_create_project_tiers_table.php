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
        Schema::create('project_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('point_multiplier', 4, 2);
            $table->decimal('min_profit_margin_percentage', 5, 2);
            $table->decimal('max_profit_margin_percentage', 5, 2);
            $table->decimal('min_client_amount_pkr', 10, 2);
            $table->decimal('max_client_amount_pkr', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tiers');
    }
};
