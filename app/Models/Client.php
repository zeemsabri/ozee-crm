<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Taggable;
use App\Models\ProjectNote;

class Client extends Model
{
    use HasFactory, Taggable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'notes',
        'timezone',
    ];

    protected $hidden = [
        'email', 'phone'
    ];

    // Add a boot method to handle dynamic hiding
    protected static function boot()
    {
        parent::boot();

        static::retrieved(function ($user) {
            if (auth()->check() && ! auth()->user()->hasPermission('edit_clients')) {
                $user->setHidden(array_merge($user->getHidden(), ['email']));
            }
        });
    }

    // Or, a more explicit method you can call
    public function hideEmailIfUnauthorized()
    {
        if (auth()->check() && ! auth()->user()->hasPermission('edit_clients')) {
            $this->setHidden(array_merge($this->getHidden(), ['email']));
        }
        return $this;
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get the deliverable interactions for the client.
     */
    public function deliverableInteractions()
    {
        return $this->hasMany(ClientDeliverableInteraction::class);
    }

    /**
     * Get the deliverable comments for the client.
     *
     * @deprecated Use notes() instead.
     */
    public function deliverableComments()
    {
        return $this->hasMany(DeliverableComment::class);
    }

    /**
     * Get all notes created by this client.
     */
    public function notes()
    {
        return $this->morphMany(ProjectNote::class, 'creator');
    }

    /**
     * Get the deliverables that the client has approved.
     */
    public function approvedDeliverables()
    {
        return $this->hasMany(Deliverable::class, 'overall_approved_by_client_id');
    }
}
