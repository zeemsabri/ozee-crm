<?php

namespace App\Models;

use App\Models\Traits\HasCategories;
use App\Models\Traits\Taggable;
use App\Models\Schedule;
use App\Services\PointsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Email extends Model
{
    use HasCategories, HasFactory, SoftDeletes, Taggable;

    /** @deprecated use App\Enums\EmailStatus::PendingApprovalReceived */
    public const STATUS_PENDING_APPROVAL = \App\Enums\EmailStatus::PendingApprovalReceived->value;

    /** @deprecated use App\Enums\EmailStatus::PendingApproval */
    public const STATUS_PENDING_APPROVAL_SENT = \App\Enums\EmailStatus::PendingApproval->value;

    /** @deprecated use App\Enums\EmailStatus::Sent */
    public const STATUS_APPROVED = \App\Enums\EmailStatus::Sent->value;

    /** @deprecated use App\Enums\EmailStatus::RejectedReceived */
    public const STATUS_REJECTED = \App\Enums\EmailStatus::RejectedReceived->value;

    /** @deprecated use App\Enums\EmailStatus::Sent */
    public const STATUS_SENT = \App\Enums\EmailStatus::Sent->value;

    /** @deprecated use App\Enums\EmailStatus::Draft */
    public const STATUS_DRAFT = \App\Enums\EmailStatus::Draft->value;

    /** @deprecated use App\Enums\EmailType::Received */
    public const TYPE_RECEIVED = \App\Enums\EmailType::Received->value;

    /** @deprecated use App\Enums\EmailType::Sent */
    public const TYPE_SENT = \App\Enums\EmailType::Sent->value;

    const APPROVE_RECEIVED_EMAILS_PERMISSION = 'approve_received_emails';

    const APPROVE_SENT_EMAIL_PERMISSION = 'approve_emails';

    /** @deprecated use App\Enums\EmailStatus::Approved */
    public const APPROVED = 'approved';

    const VIEW_EMAIL_PERMISSION = 'view_emails';

    // Blade view names for rendering outgoing emails
    const TEMPLATE_DEFAULT = 'email_template';

    const TEMPLATE_AI_LEAD_OUTREACH = 'ai_lead_outreach_template';

    protected $appends = [
        'can_approve', 'can_open', 'email_number'
    ];

    protected static function booted()
    {
        /*
         * Once an email has gone out, its status may not travel backwards.
         *
         * `sent` is a statement about the world: Gmail accepted the message and a client
         * has it. Nothing that happens here afterwards makes that untrue, so a write moving
         * it back to draft / pending_approval / auto_send is always a bug in the writer —
         * and an expensive one, because the thread then offers "Approve & send" on a
         * message the client already received, and pressing it sends a second copy.
         *
         * The known culprit was EmailProcessingService::processDraftEmail's catch block,
         * fixed at source. It is not the only writer: the automation engine's UPDATE_RECORD
         * action writes this column too, from a queued job whose view of the row is as old
         * as the moment it was queued. This guard is what makes the invariant hold
         * regardless of who writes.
         *
         * It DROPS the offending change rather than throwing — the caller is usually
         * mid-recovery from some other failure, and an exception here would mask it — and
         * logs enough of the call stack to name the writer.
         */
        static::updating(fn (Email $email) => self::guardStatusRegression($email));

        static::updated(function (Email $email) {
            // Only trigger when moving into sent state
            $typeIsSent = ($email->type instanceof \App\Enums\EmailType)
                ? ($email->type === \App\Enums\EmailType::Sent)
                : (strtolower((string) $email->type) === 'sent');
            $statusIsSent = ($email->status instanceof \App\Enums\EmailStatus)
                ? ($email->status === \App\Enums\EmailStatus::Sent)
                : (strtolower((string) $email->status) === 'sent');
            $changedToSent = $email->wasChanged('status');

            if ($changedToSent && $typeIsSent && $statusIsSent) {
                try {
                    app(PointsService::class)->awardPointsFor($email);
                } catch (\Throwable $e) {
                    Log::error('Failed to award email points: '.$e->getMessage());
                }
            }
        });
    }

    /**
     * Drop any write that moves a SENT email back to an unsent status.
     *
     * Extracted from the `updating` hook so it has a second caller: the automation
     * engine's UPDATE_RECORD action persists inside Model::withoutEvents(), which
     * silences every model event — including the hook this rule used to live in only.
     * That is not a hypothetical: it is how a delivered email ended up reading
     * pending_approval with sent_at and approved_by both populated.
     *
     * Any writer that deliberately bypasses model events MUST call this before saving.
     *
     * It DROPS the offending change rather than throwing — the caller is usually
     * mid-recovery from some other failure, and an exception here would mask it — and
     * logs enough of the call stack to name the writer.
     */
    public static function guardStatusRegression(Email $email): void
    {
        if (! $email->isDirty('status')) {
            return;
        }

        $original = self::statusValueOf($email->getOriginal('status'));

        if ($original !== \App\Enums\EmailStatus::Sent) {
            return;
        }

        $incoming = self::statusValueOf($email->status);

        $regressions = [
            \App\Enums\EmailStatus::Draft,
            \App\Enums\EmailStatus::PendingApproval,
            \App\Enums\EmailStatus::AutoSend,
        ];

        if (! in_array($incoming, $regressions, true)) {
            return;
        }

        Log::warning('Refused to move a sent email back to an unsent status.', [
            'email_id' => $email->id,
            'attempted_status' => $incoming?->value,
            // Nothing else identifies the caller: the processing service and the
            // automation engine both arrive as a plain save() inside a queued job.
            'caller' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25))
                ->map(fn ($frame) => ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''))
                ->filter(fn ($f) => str_starts_with($f, 'App\\'))
                ->take(5)
                ->values()
                ->all(),
        ]);

        $email->status = $original;
    }

    /**
     * The status as an enum, whatever shape it is in.
     *
     * getOriginal('status') returns the RAW column value (a string) even though the
     * attribute is cast, so the two sides of a comparison have to be normalised before
     * they mean anything.
     */
    private static function statusValueOf(mixed $status): ?\App\Enums\EmailStatus
    {
        if ($status instanceof \App\Enums\EmailStatus) {
            return $status;
        }

        return $status === null ? null : \App\Enums\EmailStatus::tryFrom((string) $status);
    }

    /**
     * Get all interactions for this email.
     */
    public function interactions()
    {
        return $this->morphMany(UserInteraction::class, 'interactable');
    }

    /**
     * Check if the email has been read by a specific user.
     *
     * @param  int  $userId
     * @return bool
     */
    public function isReadBy($userId)
    {
        return $this->interactions()
            ->where('user_id', $userId)
            ->where('interaction_type', 'read')
            ->exists();
    }

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'to',
        'subject',
        'body',
        'status',
        'approved_by',
        'rejection_reason',
        'sent_at',
        'message_id',
        'type',
        'template_id',
        'template_data',
        'email_template',
        'is_private',
        // Redesigned inbox (/inbox/beta) — see config/inbox.php and the
        // 2026_08_19_1001 migration. Null ai_status means never checked.
        'ai_status',
        'ai_reason',
        'ai_checked_at',
        'ai_summary',
        'ai_draft',
        'ai_draft_at',
        'ai_draft_status',
        'ai_draft_requested_at',
        // Threading (2026_08_19_100300). rfc_message_id is the RFC 5322 Message-ID
        // header — NOT message_id above, which is Gmail's API id.
        'rfc_message_id',
        'gmail_thread_id',
        'in_reply_to_email_id',
        'last_communication_at',
        'contacted_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'to' => 'array', // If 'to' can store multiple recipients as JSON
        'template_data' => 'array',
        'is_private' => 'boolean',
        'status' => \App\Enums\EmailStatus::class,
        'type' => \App\Enums\EmailType::class,
        'ai_status' => \App\Enums\EmailAiStatus::class,
        'ai_checked_at' => 'datetime',
        'ai_draft_at' => 'datetime',
        'ai_draft_status' => \App\Enums\EmailDraftStatus::class,
        'ai_draft_requested_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The message this one replies to, when it was composed in the redesigned inbox.
     *
     * Null on everything that predates it, which is deliberate: the send path only adds
     * threading headers and the quoted chain when this is set, so legacy flows are
     * untouched. See App\Services\Inbox\ReplyThreading.
     */
    public function inReplyTo()
    {
        return $this->belongsTo(self::class, 'in_reply_to_email_id');
    }

    public function project()
    {
        return $this->conversation?->project;
    }

    /**
     * Get the sender model (polymorphic relationship).
     * This can be a User or any other model that can send emails.
     */
    public function sender()
    {
        return $this->morphTo();
    }

    /**
     * Set the sender_id attribute and automatically set sender_type to User
     * when the email is created from the frontend.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setSenderIdAttribute($value)
    {
        $this->attributes['sender_id'] = $value;

        // If sender_type is not set, default to User model
        if (! isset($this->attributes['sender_type'])) {
            $this->attributes['sender_type'] = 'App\\Models\\User';
        }
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if the email is viewable by non-manager users.
     * Only approved or sent emails are viewable by all authorized users.
     */
    public function isViewableByNonManagers()
    {
        $statusIsSent = ($this->status instanceof \App\Enums\EmailStatus)
            ? ($this->status === \App\Enums\EmailStatus::Sent)
            : (strtolower((string) $this->status) === \App\Enums\EmailStatus::Sent->value);

        $typeIsReceived = ($this->type instanceof \App\Enums\EmailType)
            ? ($this->type === \App\Enums\EmailType::Received)
            : (strtolower((string) $this->type) === \App\Enums\EmailType::Received->value);

        return $statusIsSent || $typeIsReceived;
    }

    /**
     * Get the email template associated with this email.
     */
    public function template()
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function files()
    {
        return $this->morphMany(FileAttachment::class, 'fileable');
    }

    /**
     * Context records where this email is the source (referencable).
     */
    public function contexts()
    {
        return $this->morphMany(Context::class, 'referencable');
    }

    /**
     * Scope to filter emails visible to a given user, hiding private emails unless permitted.
     */
    public function scopeVisibleTo($query, $user)
    {
        if (! $user || ! $user->hasPermission('view_private_emails')) {
            $query->where(function ($q) {
                $q->whereNull('is_private')->orWhere('is_private', false);
            });
        }

        return $query;
    }

    public function getCanOpenAttribute()
    {
        if ($this->status === self::STATUS_DRAFT) {
            return false;
        }

        return false;
    }

    public function getCanApproveAttribute()
    {
        $user = request()?->user();

        if (! $user) {
            return false;
        }

        // Normalize status to enum when possible
        $statusEnum = $this->status instanceof \App\Enums\EmailStatus
            ? $this->status
            : \App\Enums\EmailStatus::tryFrom(strtolower((string) $this->status));

        if ($statusEnum === \App\Enums\EmailStatus::Draft) {
            return false;
        }

        $canApprove = false; // Default to false

        // Check approval permission for outgoing emails
        if ($statusEnum === \App\Enums\EmailStatus::PendingApproval && $this->conversation?->project?->id) {

            if ($user->hasPermission('approve_all_emails')) {
                $canApprove = true;
            }

            if ($user->hasProjectPermission($this->conversation->project->id, self::APPROVE_SENT_EMAIL_PERMISSION)) {
                $canApprove = true;
            }
        }

        // For leads, allow approval with 'contact_lead' permission
        if ($this->conversation?->conversable && get_class($this->conversation->conversable) === Lead::class && $user->hasPermission('contact_lead')) {
            $canApprove = true;
        }

        if (! $this->conversation?->conversable && $user->hasPermission('contact_leads')) {
            $canApprove = true;
        }

        // Check approval permission for incoming emails
        if ($statusEnum === \App\Enums\EmailStatus::PendingApprovalReceived) {
            // This assumes hasPermission('approve_received_emails') is a global permission
            if ($user->hasPermission(self::APPROVE_RECEIVED_EMAILS_PERMISSION)) {
                $canApprove = true;
            }
        }

        return $canApprove;
    }

    public static function fieldMetaForWorkflow(): array
    {
        return [

            'sender_type' => [
                'label' => 'Sender Type',
                'description' => 'Choose what Client, Lead or User',
                'ui' => 'morph_type',
            ],
        ];
    }

    /**
     * Execute this email when triggered by a schedule.
     */
    public function runScheduled(Schedule $schedule): void
    {
        $this->refresh();

        // For delayed emails, scheduling only unlocks them back to draft.
        if ($this->status === \App\Enums\EmailStatus::Delayed) {
            $this->update([
                'status' => \App\Enums\EmailStatus::Draft,
            ]);
        }
    }

    /**
     * Accessor for task number (OZ + id).
     *
     * @return string
     */
    public function getEmailNumberAttribute(): string
    {
        return 'OZE' . $this->id;
    }
}
