<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientDeliverableInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'deliverable_id',
        'client_id',
        'read_at',
        'approved_at',
        'rejected_at',
        'revisions_requested_at',
        'feedback_text',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'revisions_requested_at' => 'datetime',
    ];

    /**
     * Get the deliverable that owns the interaction.
     */
    public function deliverable()
    {
        return $this->belongsTo(Deliverable::class);
    }

    /**
     * Get the client that owns the interaction.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
