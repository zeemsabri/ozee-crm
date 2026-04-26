<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalEmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'magic_link_id',
        'email_app_id',
        'project_id',
        'status',
        'provider',
        'to_email',
        'subject',
        'error_message',
        'request_payload',
        'response_payload',
        'attempted_at',
        'sent_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempted_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function emailApp(): BelongsTo
    {
        return $this->belongsTo(EmailApp::class);
    }

    public function magicLink(): BelongsTo
    {
        return $this->belongsTo(MagicLink::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
