<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MagicLink extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'token',
        'project_id',
        'temporary_pin',
        'temp_pin_expires_at',
        'expires_at',
        'used',
        'whitelist',
        'max_uses',
        'uses_count',
        'type',
        'label',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'temp_pin_expires_at' => 'datetime',
        'used' => 'boolean',
        'whitelist' => 'array',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the project that the magic link belongs to.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Determine if the magic link has expired.
     *
     * @return bool
     */
    public function hasExpired()
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Determine if the magic link has been used.
     *
     * @return bool
     */
    public function hasBeenUsed()
    {
        return $this->used;
    }

    /**
     * Mark the magic link as used.
     *
     * @return bool
     */
    public function markAsUsed()
    {
        $this->used = true;

        return $this->save();
    }
}
