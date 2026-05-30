<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $fillable = [
        'email',
        'otp_hash',
        'project_token',
        'expires_at',
        'verified_at',
        'session_token',
        'guest_user_id',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function guestUser()
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }
}
