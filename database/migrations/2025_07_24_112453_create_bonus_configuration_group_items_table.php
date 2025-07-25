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
        Schema::create('bonus_configuration_group_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('bonus_configuration_groups')->onDelete('cascade');
            $table->foreignId('configuration_id')->constrained('bonus_configurations')->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Add a unique constraint to prevent duplicate configurations in a group
            $table->unique(['group_id', 'configuration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_configuration_group_items');
    }
};
