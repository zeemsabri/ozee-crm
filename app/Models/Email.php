<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Taggable;

class Email extends Model
{
    use HasFactory, Taggable, SoftDeletes;

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
        'template_data'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'to' => 'array', // If 'to' can store multiple recipients as JSON
        'template_data' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
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
}
