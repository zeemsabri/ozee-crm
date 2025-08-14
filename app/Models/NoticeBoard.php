<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class NoticeBoard extends ShareableResource
{
    use HasFactory, SoftDeletes;

    protected $table = 'shareable_resources';

    // Notice types
    public const TYPE_GENERAL = 'General';
    public const TYPE_WARNING = 'Warning';
    public const TYPE_UPDATES = 'Updates';
    public const TYPE_FINAL_NOTICE = 'Final Notice';

    public const TYPES = [
        self::TYPE_GENERAL,
        self::TYPE_WARNING,
        self::TYPE_UPDATES,
        self::TYPE_FINAL_NOTICE,
    ];

    protected $fillable = [
        'title',
        'description',
        'url',
        'type',
        'created_by',
        'visible_to_client',
    ];

    protected $casts = [
        'visible_to_client' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Ensure default visibility to client is false for notices
            if ($model->visible_to_client === null) {
                $model->visible_to_client = false;
            }
        });
    }

    // Interactions: read and click tracking
    public function interactions()
    {
        return $this->morphMany(UserInteraction::class, 'interactable');
    }

    public function isClickable(): bool
    {
        return !empty($this->url);
    }
}
