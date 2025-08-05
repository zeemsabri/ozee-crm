<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingAttendee extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'meeting_id',
        'user_id',
        'notification_sent',
        'notification_sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'notification_sent' => 'boolean',
        'notification_sent_at' => 'datetime',
    ];

    /**
     * Get the meeting that the attendee belongs to.
     */
    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the user who is attending the meeting.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
