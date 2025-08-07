<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleAccounts extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'expires_in',
        'created',
        'email',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created' => 'integer',
        'expires_in' => 'integer',
    ];

    /**
     * Get the user that owns the Google account.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        // Token is expired if current time is greater than created time + expires_in
        return time() > ($this->created + $this->expires_in);
    }
}
