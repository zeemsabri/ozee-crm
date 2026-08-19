<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the redesigned inbox needs.
 *
 * That inbox lists threads, not individual emails, so every page load runs correlated
 * subqueries over `emails` grouped by conversation ("newest inbound", "newest outbound",
 * "unread by me") to derive the reply clock. Without these that is a full scan per row.
 *
 * Deliberately index-only: no columns are added and nothing is denormalised, so the
 * reply clock can never drift out of sync with the emails it is derived from, and the
 * legacy /inbox page is completely unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Drives "newest inbound / newest outbound per conversation".
            $table->index(['conversation_id', 'type', 'created_at'], 'emails_conv_type_created_idx');

            // Drives the per-view status buckets (Waiting approval, With AI, Drafts).
            $table->index(['status', 'created_at'], 'emails_status_created_idx');
        });

        Schema::table('user_interactions', function (Blueprint $table) {
            // Drives the unread lookup: "has this user read these emails?"
            $table->index(
                ['user_id', 'interaction_type', 'interactable_type', 'interactable_id'],
                'user_interactions_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex('emails_conv_type_created_idx');
            $table->dropIndex('emails_status_created_idx');
        });

        Schema::table('user_interactions', function (Blueprint $table) {
            $table->dropIndex('user_interactions_lookup_idx');
        });
    }
};
