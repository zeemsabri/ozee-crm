<?php

namespace App\Models;

use Faker\Core\File;
use GPBMetadata\Google\Api\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Taggable;
use App\Services\PointsService;
use Illuminate\Support\Facades\Log;

class Email extends Model
{
    use HasFactory, Taggable, SoftDeletes;

    const STATUS_PENDING_APPROVAL = 'pending_approval_received';

    const STATUS_PENDING_APPROVAL_SENT = 'pending_approval';
    const STATUS_APPROVED = 'sent';
    const STATUS_REJECTED = 'rejected_received';
    const STATUS_SENT = 'sent';
    const STATUS_DRAFT = 'draft';
    protected $appends = [
        'can_approve', 'can_open',
    ];

    protected static function booted()
    {
        static::updated(function (Email $email) {
            // Only trigger when moving into sent state
            $typeIsSent = strtolower($email->type ?? '') === 'sent';
            $statusIsSent = strtolower($email->status ?? '') === 'sent';
            $changedToSent = $email->wasChanged('status');

            if ($changedToSent && $typeIsSent && $statusIsSent) {
                try {
                    app(PointsService::class)->awardPointsFor($email);
                } catch (\Throwable $e) {
                    Log::error('Failed to award email points: ' . $e->getMessage());
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
     * @param int $userId
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
        'is_private',
        'last_communication_at',
        'contacted_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
        'to' => 'array', // If 'to' can store multiple recipients as JSON
        'template_data' => 'array',
        'is_private' => 'boolean',
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
     * @param mixed $value
     * @return void
     */
    public function setSenderIdAttribute($value)
    {
        $this->attributes['sender_id'] = $value;

        // If sender_type is not set, default to User model
        if (!isset($this->attributes['sender_type'])) {
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
        return in_array($this->status, ['approved', 'sent', 'received']);
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
        if (!$user || !$user->hasPermission('view_private_emails')) {
            $query->where(function ($q) {
                $q->whereNull('is_private')->orWhere('is_private', false);
            });
        }
        return $query;
    }

    public function getCanOpenAttribute()
    {
        if($this->status === self::STATUS_DRAFT) {
            return false;
        }

        return false;
    }

    public function getCanApproveAttribute()
    {

        $user = request()?->user();

        if(!$user) {
            return false;
        }

        if($this->status === self::STATUS_DRAFT) {
            return false;
        }

        $canApprove = false; // Default to false

        // Check approval permission for outgoing emails
        if ($this->status === 'pending_approval' && $this->conversation?->project?->id) {
            if ($user->hasProjectPermission($this->conversation->project->id, 'approve_emails')
            ) {
                $canApprove = true;
            }
        }

        if(get_class($this->conversation->conversable) === Lead::class && $user->hasPermission('contact_lead')) {
            $canApprove = true;
        }

        // Check approval permission for incoming emails
        if ($this->status === 'pending_approval_received') {
            // This assumes hasPermission('approve_received_emails') is a global permission
            if ($user->hasPermission('approve_received_emails')) {
                $canApprove = true;
            }
        }

        return $canApprove;

    }

}
