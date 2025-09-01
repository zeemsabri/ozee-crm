<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class WireframeVersion extends Model
{
    use HasFactory, LogsActivity;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'wireframe_id',
        'version_number',
        'data',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'version_number' => 'integer',
    ];

    /**
     * Get the activity log options for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['version_number', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the wireframe that owns the version.
     */
    public function wireframe(): BelongsTo
    {
        return $this->belongsTo(Wireframe::class);
    }

    /**
     * Determine if the version is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Determine if the version is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Publish this version.
     */
    public function publish(): bool
    {
        if ($this->isPublished()) {
            return false;
        }

        $this->status = 'published';
        return $this->save();
    }
}
