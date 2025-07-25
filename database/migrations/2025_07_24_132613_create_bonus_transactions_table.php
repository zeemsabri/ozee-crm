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
        Schema::create('bonus_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('bonus_configuration_id')->nullable()->constrained('bonus_configurations')->onDelete('set null');
            $table->enum('type', ['bonus', 'penalty']);
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->enum('status', ['pending', 'approved', 'rejected', 'processed'])->default('pending');
            $table->enum('source_type', ['standup', 'task', 'milestone', 'manual', 'other']);
            $table->string('source_id')->nullable(); // ID of the related entity (task ID, standup ID, etc.)
            $table->dateTime('processed_at')->nullable();
            $table->json('metadata')->nullable(); // Additional data related to the transaction
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_transactions');
    }
};
