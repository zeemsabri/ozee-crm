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
        Schema::create('user_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('interactable_id');
            $table->string('interactable_type');
            $table->string('interaction_type'); // e.g., 'read', 'viewed', 'approved'
            $table->timestamps();

            // Add index for polymorphic relationship
            $table->index(['interactable_id', 'interactable_type']);

            // Add unique constraint to prevent duplicate interactions
            $table->unique(['user_id', 'interactable_id', 'interactable_type', 'interaction_type'], 'user_interaction_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_interactions');
    }
};
