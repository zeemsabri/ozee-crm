<?php

namespace App\Models;

use App\Models\Traits\HasCategories;
use App\Models\Traits\Taggable;
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
        'can_approve', 'can_open',
    ];

    protected static function booted()
    {
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
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
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
}
