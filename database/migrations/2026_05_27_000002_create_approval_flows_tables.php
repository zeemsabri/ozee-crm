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
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('approvable_type');
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['approvable_type', 'project_id', 'is_active']);
        });

        Schema::create('approval_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained('approval_flows')->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('approver_type', 20); // role|user
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['approval_flow_id', 'step_order']);
            $table->index(['approval_flow_id', 'approver_type']);
        });

        Schema::create('approval_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained('approval_flows')->cascadeOnDelete();
            $table->morphs('approvable');
            $table->unsignedInteger('current_step_order')->nullable();
            $table->string('status', 30)->default('pending'); // pending|in_progress|completed|rejected
            $table->timestamps();

            $table->index(['approval_flow_id', 'status']);
        });

        Schema::create('approval_instance_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_instance_id')->constrained('approval_instances')->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('approver_type', 20); // role|user
            $table->foreignId('approver_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label')->nullable();
            $table->string('status', 30)->default('pending'); // pending|approved|rejected|skipped
            $table->foreignId('acted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['approval_instance_id', 'step_order']);
            $table->index(['approval_instance_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_instance_steps');
        Schema::dropIfExists('approval_instances');
        Schema::dropIfExists('approval_flow_steps');
        Schema::dropIfExists('approval_flows');
    }
};
