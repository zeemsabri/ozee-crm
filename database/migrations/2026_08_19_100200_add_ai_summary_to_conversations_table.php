<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thread-level AI output: the summary card at the top of a thread and the task it
 * suggests creating.
 *
 * `ai_summary_email_count` is what makes the summary safely cacheable — it records how
 * many messages the summary was generated from, so the thread view can tell "this summary
 * is current" from "two replies have landed since" without storing a hash of the bodies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->text('ai_summary')->nullable()->after('last_activity_at');
            $table->timestamp('ai_summary_at')->nullable()->after('ai_summary');
            $table->unsignedSmallInteger('ai_summary_email_count')->nullable()->after('ai_summary_at');

            // { title, due_date, priority, reason } — the "Create this task" suggestion.
            $table->json('ai_task_suggestion')->nullable()->after('ai_summary_email_count');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn([
                'ai_summary',
                'ai_summary_at',
                'ai_summary_email_count',
                'ai_task_suggestion',
            ]);
        });
    }
};
