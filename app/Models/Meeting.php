<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'created_by_user_id',
        'google_event_id',
        'google_event_link',
        'google_meet_link',
        'summary',
        'description',
        'start_time',
        'end_time',
        'location',
        'timezone',
        'enable_recording',
        'is_utc',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'enable_recording' => 'boolean',
        'is_utc' => 'boolean',
    ];

    /**
     * Get the project that the meeting belongs to.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who created the meeting.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
