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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users');
            $table->date('due_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->enum('status', ['To Do', 'In Progress', 'Done', 'Blocked', 'Archived'])->default('To Do');
            $table->foreignId('task_type_id')->constrained('task_types');
            $table->foreignId('milestone_id')->nullable()->constrained('milestones');
            $table->string('google_chat_space_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
