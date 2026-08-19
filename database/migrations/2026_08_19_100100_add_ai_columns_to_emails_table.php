<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-email AI state for the redesigned inbox.
 *
 * Why columns on `emails` rather than the existing polymorphic `contexts` table:
 * the inbox filters and sorts on this ("With AI" view, stalled-check detection), and a
 * morph join per row to find the newest context of the right shape is exactly the query
 * that made the old page slow. `contexts` stays the place for durable analysis history;
 * these columns are the current state of one email.
 *
 * `ai_status` is intentionally a plain string, not an enum column — the check states are
 * expected to change as the feature settles, and an enum migration to add one value is a
 * table lock. Allowed values live in App\Enums\EmailAiStatus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // null = never submitted to the checker. See App\Enums\EmailAiStatus.
            $table->string('ai_status', 24)->nullable()->after('is_private');
            $table->text('ai_reason')->nullable()->after('ai_status');
            $table->timestamp('ai_checked_at')->nullable()->after('ai_reason');

            // One-line summary of THIS message, shown collapsed in the thread.
            $table->text('ai_summary')->nullable()->after('ai_checked_at');

            // A suggested reply to this (inbound) message, pre-loaded into the reply box.
            $table->text('ai_draft')->nullable()->after('ai_summary');
            $table->timestamp('ai_draft_at')->nullable()->after('ai_draft');

            $table->index(['ai_status', 'ai_checked_at'], 'emails_ai_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex('emails_ai_status_idx');
            $table->dropColumn([
                'ai_status',
                'ai_reason',
                'ai_checked_at',
                'ai_summary',
                'ai_draft',
                'ai_draft_at',
            ]);
        });
    }
};
