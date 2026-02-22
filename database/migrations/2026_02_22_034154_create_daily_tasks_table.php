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
        Schema::create('daily_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->unsignedSmallInteger('order')->default(0);
            // 'pending', 'completed', 'pushed_to_next_day'
            $table->string('status', 30)->default('pending')->index();
            $table->text('note')->nullable(); // Optional user note for this day's entry
            $table->timestamps();

            $table->unique(['user_id', 'task_id', 'date']); // One entry per user/task/day
            $table->index(['user_id', 'date']); // Fast lookup for a user's day
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_tasks');
    }
};
