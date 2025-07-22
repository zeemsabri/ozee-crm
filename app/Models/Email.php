<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'to',
        'subject',
        'body',
        'status',
        'approved_by',
        'rejection_reason',
        'sent_at',
        'message_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'to' => 'array', // If 'to' can store multiple recipients as JSON
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
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
}
