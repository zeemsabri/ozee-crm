<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverableComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'deliverable_id',
        'client_id',
        'comment_text',
        'context',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the deliverable that owns the comment.
     */
    public function deliverable()
    {
        return $this->belongsTo(Deliverable::class);
    }

    /**
     * Get the client that created the comment.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
