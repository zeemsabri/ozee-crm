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
        'expires_at',
        'used',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
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
