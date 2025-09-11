<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->onDelete('cascade');
            $table->integer('step_order');
            $table->string('name');
            $table->string('step_type')->default('AI_PROMPT');
            $table->foreignId('prompt_id')->nullable()->constrained('prompts')->nullOnDelete();
            $table->json('step_config')->nullable();
            $table->json('condition_rules')->nullable();
            $table->integer('delay_minutes')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
