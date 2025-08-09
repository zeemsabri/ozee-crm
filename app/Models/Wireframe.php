<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Wireframe extends Model
{
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'name',
    ];

    /**
     * Get the activity log options for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the project that owns the wireframe.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the versions for the wireframe.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(WireframeVersion::class);
    }

    /**
     * Get the latest version of the wireframe.
     */
    public function latestVersion()
    {
        return $this->versions()->orderBy('version_number', 'desc')->first();
    }

    /**
     * Get the latest draft version of the wireframe.
     */
    public function latestDraftVersion()
    {
        return $this->versions()->where('status', 'draft')->orderBy('version_number', 'desc')->first();
    }

    /**
     * Get the latest published version of the wireframe.
     */
    public function latestPublishedVersion()
    {
        return $this->versions()->where('status', 'published')->orderBy('version_number', 'desc')->first();
    }
}
