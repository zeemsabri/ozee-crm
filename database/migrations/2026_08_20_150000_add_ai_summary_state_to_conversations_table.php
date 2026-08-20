<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes thread summarising a request with a visible state, instead of a silent side effect.
 *
 * Two problems this exists to fix.
 *
 * **It ran on its own, on every open.** InboxThreadController::show dispatched
 * SummariseConversation whenever a thread had no current summary. Opening a thread called
 * a model. Worse, once the thread began polling itself every minute, each poll re-entered
 * show() and re-dispatched — a thread left open on screen billed a summary a minute,
 * indefinitely, for a summary nobody had asked for. Summarising is now something a person
 * clicks.
 *
 * **The result never appeared.** Even when the job succeeded, nothing re-read the thread,
 * so the summary showed up only if someone happened to reopen it. Same shape of bug as
 * "Draft for me". A status column gives the page something to poll on and something to
 * show progress from.
 *
 * Mirrors the ai_draft_status pair on `emails`; see EmailDraftStatus for why the terminal
 * states matter as much as the working ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('ai_summary_status', 16)->nullable()->after('ai_summary_email_count');
            $table->timestamp('ai_summary_requested_at')->nullable()->after('ai_summary_status');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['ai_summary_status', 'ai_summary_requested_at']);
        });
    }
};
