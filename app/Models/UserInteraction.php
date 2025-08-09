<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInteraction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'interactable_id',
        'interactable_type',
        'interaction_type',
    ];

    /**
     * Get the user that owns the interaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owning interactable model.
     */
    public function interactable()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include interactions of a specific type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }
}
