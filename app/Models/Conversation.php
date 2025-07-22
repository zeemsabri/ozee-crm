<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'project_id',
        'client_id',
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

    public function client()
    {
        return $this->belongsTo(Client::class);
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
