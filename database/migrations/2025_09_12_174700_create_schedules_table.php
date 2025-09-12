<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->string('recurrence_pattern'); // cron expression
            $table->boolean('is_active')->default(true);
            $table->boolean('is_onetime')->default(false);
            $table->dateTime('last_run_at')->nullable();

            // Polymorphic relation: scheduled_item (Task, Workflow, etc.)
            $table->unsignedBigInteger('scheduled_item_id');
            $table->string('scheduled_item_type', 191);

            $table->softDeletes();
            $table->timestamps();

            // Indexes to speed up queries
            $table->index(['is_active', 'start_at', 'end_at']);
            $table->index(['scheduled_item_type', 'scheduled_item_id'], 'schedules_item_index');
            $table->index('recurrence_pattern');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
