<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deliverable extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'team_member_id',
        'title',
        'description',
        'type',
        'status',
        'content_url',
        'content_text',
        'attachment_path',
        'version',
        'parent_deliverable_id',
        'submitted_at',
        'overall_approved_at',
        'overall_approved_by_client_id',
        'due_for_review_by',
        'is_visible_to_client',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'overall_approved_at' => 'datetime',
        'due_for_review_by' => 'datetime',
        'is_visible_to_client' => 'boolean',
    ];

    /**
     * Get the project that owns the deliverable.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the team member (user) that owns the deliverable.
     */
    public function teamMember()
    {
        return $this->belongsTo(User::class, 'team_member_id');
    }

    /**
     * Get the client that approved the deliverable.
     */
    public function approvedByClient()
    {
        return $this->belongsTo(Client::class, 'overall_approved_by_client_id');
    }

    /**
     * Get the parent deliverable.
     */
    public function parent()
    {
        return $this->belongsTo(Deliverable::class, 'parent_deliverable_id');
    }

    /**
     * Get the child deliverables.
     */
    public function children()
    {
        return $this->hasMany(Deliverable::class, 'parent_deliverable_id');
    }

    /**
     * Get the client interactions for the deliverable.
     */
    public function clientInteractions()
    {
        return $this->hasMany(ClientDeliverableInteraction::class);
    }

    /**
     * Get the comments for the deliverable.
     */
    public function comments()
    {
        return $this->hasMany(DeliverableComment::class);
    }
}
