<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    protected $fillable = [
        'user_id',
        'domain',
        'url',
        'title',
        'is_incognito',
        'is_audible',
        'tab_count',
        'duration',
        'hostname',
        'browser',
        'recorded_at',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'is_incognito' => 'boolean',
        'is_audible' => 'boolean',
        'tab_count' => 'integer',
        'duration' => 'integer',
        'recorded_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
