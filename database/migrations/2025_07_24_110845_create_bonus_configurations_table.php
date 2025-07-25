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
        Schema::create('bonus_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['bonus', 'penalty']);
            $table->enum('amountType', ['percentage', 'fixed', 'all_related_bonus']);
            $table->decimal('value', 10, 2)->default(0);
            $table->enum('appliesTo', ['task', 'milestone', 'standup', 'late_task', 'late_milestone', 'standup_missed']);
            $table->string('targetBonusTypeForRevocation')->nullable();
            $table->boolean('isActive')->default(true);
            $table->string('uuid')->unique(); // Store the client-generated UUID
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_configurations');
    }
};
