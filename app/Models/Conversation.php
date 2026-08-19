<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'project_id',
        'conversable_type',
        'conversable_id',
        'contractor_id',
        'last_activity_at',
        // Redesigned inbox (/inbox/beta) — thread-level AI output. See config/inbox.php.
        'ai_summary',
        'ai_summary_at',
        'ai_summary_email_count',
        'ai_task_suggestion',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'ai_summary_at' => 'datetime',
        'ai_task_suggestion' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Polymorphic recipient of the conversation (Client or Lead)
     */
    public function conversable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Backward compatible accessor: conversation->client returns the conversable if it is a Client.
     */
    public function getClientAttribute()
    {
        return $this->conversable instanceof Client ? $this->conversable : null;
    }

    /**
     * Get the contractor associated with the conversation.
     *
     * Note: contractor_id can be nullable when a client sends an email and we receive it.
     * In this case, the conversation is initiated by the client, not a contractor.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contractor()
    {
        return $this->belongsTo(User::class, 'contractor_id');
    }

    public function emails()
    {
        return $this->hasMany(Email::class);
    }

    /**
     * Internal team notes on this thread — the yellow cards in the redesigned inbox.
     *
     * Reuses the generic `comments` table rather than adding a notes table: notes are
     * plain author + body + timestamp, and Comment already models exactly that
     * polymorphically. Unlike ProjectNote these are not encrypted and do not push to
     * Google Chat, which is what we want for something rendered inline on every open.
     *
     * These are never sent to the client. Nothing in the send path reads them.
     */
    public function notes()
    {
        return $this->morphMany(Comment::class, 'commentable')->latest();
    }

    /**
     * Is the AI thread summary still describing the whole thread?
     *
     * Compares the message count the summary was built from with the count now, so a
     * stale summary can be hidden (or regenerated) instead of quietly misleading someone
     * about a thread that has moved on.
     */
    public function hasCurrentAiSummary(?int $emailCount = null): bool
    {
        if (! $this->ai_summary || ! $this->ai_summary_email_count) {
            return false;
        }

        $count = $emailCount ?? $this->emails()->count();

        // Exact, not >=. A thread that has lost a message (deleted, or newly withheld
        // from this viewer) is a different thread from the one the summary describes, and
        // ">= 0" would have made every summary look current when the count was missing.
        return $count > 0 && $this->ai_summary_email_count === $count;
    }
}
