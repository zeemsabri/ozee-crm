<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'project_id',
        'conversable_type',
        'conversable_id',
        'contractor_id',
        'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Polymorphic recipient of the conversation (Client or Lead)
     */
    public function conversable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Backward compatible accessor: conversation->client returns the conversable if it is a Client.
     */
    public function getClientAttribute()
    {
        return $this->conversable instanceof Client ? $this->conversable : null;
    }

    /**
     * Get the contractor associated with the conversation.
     *
     * Note: contractor_id can be nullable when a client sends an email and we receive it.
     * In this case, the conversation is initiated by the client, not a contractor.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contractor()
    {
        return $this->belongsTo(User::class, 'contractor_id');
    }

    public function emails()
    {
        return $this->hasMany(Email::class);
    }
}
