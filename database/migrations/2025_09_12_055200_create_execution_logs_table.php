<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('workflow_id')->constrained('workflows');
            $table->foreignId('step_id')->constrained('workflow_steps');
            $table->string('triggering_object_id')->nullable();
            $table->unsignedBigInteger('parent_execution_log_id')->nullable();
            $table->string('status');
            $table->json('input_context')->nullable();
            $table->json('raw_output')->nullable();
            $table->json('parsed_output')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->json('token_usage')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->timestamp('executed_at')->useCurrent();

            $table->foreign('parent_execution_log_id')
                ->references('id')->on('execution_logs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
