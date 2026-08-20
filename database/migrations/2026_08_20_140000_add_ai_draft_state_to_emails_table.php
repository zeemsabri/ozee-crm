<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "Draft for me" observable.
 *
 * The button dispatched DraftReplyForEmail and returned success immediately. The job then
 * wrote `ai_draft` on the email and nothing told anyone. There was no state meaning "a
 * draft is being written", so the composer could not show progress, the thread had nothing
 * to poll on, and the draft only ever appeared if the person happened to close and reopen
 * the thread. From the outside that is indistinguishable from the feature being broken —
 * which is exactly how it was reported.
 *
 * Two columns rather than one because they answer two different questions, and folding
 * them together is what makes a state machine like this ambiguous later:
 *
 *   ai_draft_status        where the request is now: queued → writing → ready | failed
 *   ai_draft_requested_at  when the button was pressed, so a job that dies without ever
 *                          reaching a terminal state can be spotted and offered again
 *                          rather than spinning forever
 *
 * `ai_draft_at` already exists and keeps its meaning: when a draft was successfully
 * produced. A failed attempt does not touch it, so "the last draft we have" stays
 * truthful even after a retry fails.
 *
 * Both nullable, so every existing row reads as "never requested" and nothing changes for
 * mail that predates this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Plain string, not an enum column, for the same reason as `ai_status`:
            // adding a value to an enum is a table lock. Allowed values live in
            // App\Enums\EmailDraftStatus.
            $table->string('ai_draft_status', 16)->nullable()->after('ai_draft_at');
            $table->timestamp('ai_draft_requested_at')->nullable()->after('ai_draft_status');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn(['ai_draft_status', 'ai_draft_requested_at']);
        });
    }
};
