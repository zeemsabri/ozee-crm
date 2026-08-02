<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOtp extends Model
{
    protected $fillable = [
        'identifier',
        'context',
        'otp_hash',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
        'meta',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'meta' => 'array',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasExceededMaxAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }
}
