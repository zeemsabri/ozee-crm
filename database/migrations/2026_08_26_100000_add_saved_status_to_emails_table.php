<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saved (unfinished) emails — the composer's "Save" button.
 *
 * A saved email is a REAL emails row with status 'saved', which nothing but
 * Api\InboxSavedController may write. Three changes make that safe:
 *
 * - `status` gains 'saved'. It could not be 'draft': status=draft means SUBMITTED — it is
 *   what the workflow automation selects on, and a parked half-written email with that
 *   status would be AI-checked and sent. (Also mirrored in App\Enums\EmailStatus.)
 *
 * - `conversation_id` becomes nullable, and a saved email has NO conversation. That one
 *   fact is what keeps saved rows out of everything else: ThreadQuery, the legacy inbox
 *   lists, quoting, the reply clock and the previews all reach emails THROUGH
 *   conversations, so a row with no conversation simply does not exist to them. The
 *   conversation is created by the normal compose endpoint when the email is actually
 *   submitted (a fresh row; the saved one is deleted).
 *
 * - `draft_meta` (json) carries what the composer needs to resume — project_id,
 *   client_ids, greeting — which a normal email derives from its conversation. Only ever
 *   read to prefill the composer; no send path looks at it.
 *
 * ## Why every step is conditional
 *
 * The first run of this migration failed halfway on the production database: re-adding
 * the foreign key threw 1452, because emails held ORPHANED rows — conversation_id values
 * whose conversation no longer exists (deleted at some point with FK enforcement off;
 * the ON DELETE CASCADE would otherwise have removed them). That left the FK dropped and
 * the column already nullable. Every step therefore checks before acting, so the
 * migration completes correctly from a fresh database AND from that half-applied state —
 * and the orphans are NULLed (not deleted: they are someone's mail, even if every
 * conversation-scoped query was already blind to them) before the FK comes back.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL will not modify a column that carries a foreign key — detach, widen,
        // reattach. Name from the Laravel convention used by create_emails_table.
        if ($this->conversationFkExists()) {
            DB::statement('ALTER TABLE emails DROP FOREIGN KEY emails_conversation_id_foreign');
        }

        DB::statement('ALTER TABLE emails MODIFY conversation_id BIGINT UNSIGNED NULL');

        /*
         * Orphaned emails — a conversation_id whose conversation is gone — are what made
         * the FK refuse to come back (1452). They were already invisible to every query
         * that reaches emails through conversations (whereHas / joins found no parent),
         * so NULLing the dangling pointer changes nothing anyone can see, and now that
         * the column is nullable it is finally representable.
         */
        DB::statement(
            'UPDATE emails e
             LEFT JOIN conversations c ON c.id = e.conversation_id
             SET e.conversation_id = NULL
             WHERE e.conversation_id IS NOT NULL AND c.id IS NULL'
        );

        if (! $this->conversationFkExists()) {
            DB::statement('ALTER TABLE emails ADD CONSTRAINT emails_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE');
        }

        // Same value list as 2026_06_15_120000_add_delayed_to_emails_status_enum, plus 'saved'.
        DB::statement("ALTER TABLE emails MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'rejected', 'sent', 'received', 'pending_approval_received', 'auto_send', 'unknown', 'pending', 'delayed', 'saved') DEFAULT 'draft'");

        if (! Schema::hasColumn('emails', 'draft_meta')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->json('draft_meta')->nullable()->after('template_data');
            });
        }
    }

    public function down(): void
    {
        // Neither saved rows nor NULL-conversation rows can survive the rollback: the
        // status is being removed and the NOT NULL is coming back. The latter set
        // includes the pre-existing orphans NULLed in up() — rows every
        // conversation-scoped query was already blind to.
        DB::statement("DELETE FROM emails WHERE status = 'saved' OR conversation_id IS NULL");

        if (Schema::hasColumn('emails', 'draft_meta')) {
            Schema::table('emails', function (Blueprint $table) {
                $table->dropColumn('draft_meta');
            });
        }

        DB::statement("ALTER TABLE emails MODIFY COLUMN status ENUM('draft', 'pending_approval', 'approved', 'rejected', 'sent', 'received', 'pending_approval_received', 'auto_send', 'unknown', 'pending', 'delayed') DEFAULT 'draft'");

        if ($this->conversationFkExists()) {
            DB::statement('ALTER TABLE emails DROP FOREIGN KEY emails_conversation_id_foreign');
        }
        DB::statement('ALTER TABLE emails MODIFY conversation_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE emails ADD CONSTRAINT emails_conversation_id_foreign FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE');
    }

    private function conversationFkExists(): bool
    {
        return ! empty(DB::select(
            "SELECT 1
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'emails'
               AND CONSTRAINT_NAME = 'emails_conversation_id_foreign'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        ));
    }
};
