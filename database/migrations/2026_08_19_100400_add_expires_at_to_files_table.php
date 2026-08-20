<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An expiry date for files that do not need to be kept forever.
 *
 * Added for the inbox's block-builder images. Those are embedded into the outgoing
 * message as CID parts, so the copy that matters lives in the client's mailbox — ours is
 * only a working copy for the composer and for rendering the thread in our own UI, and
 * there is no reason to pay to store it indefinitely.
 *
 * **Nullable means "keep forever", and every existing row is null.** Nothing already in
 * the `files` table (task attachments, invoices, chat, inbound email attachments) is
 * touched or becomes prunable by this migration — only rows that explicitly opt in by
 * setting a date.
 *
 * Note the table is `files`, not `file_attachments`, despite the model name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('thumbnail');

            // The prune command's only query is "expired, oldest first".
            $table->index('expires_at', 'files_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex('files_expires_at_idx');
            $table->dropColumn('expires_at');
        });
    }
};
