<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRememberedDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_token_hash',
        'user_agent',
        'ip_address',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
