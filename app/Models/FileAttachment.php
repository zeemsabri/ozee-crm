<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FileAttachment extends Model
{
    use HasFactory;

    protected $table = 'files';

    protected $fillable = [
        'fileable_id',
        'fileable_type',
        'project_id',
        'filename',
        'mime_type',
        'file_size',
        'path',
        'google_drive_file_id',
        'thumbnail',
    ];

    protected $appends = [
        'path_url',
        'thumbnail_url',
    ];

    public function fileable()
    {
        return $this->morphTo();
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Public URL (signed) for the original file path stored on GCS.
     */
    public function getPathUrlAttribute(): ?string
    {
        $path = $this->attributes['path'] ?? null;
        if (!$path) return null;
        try {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addDay());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Public URL (signed) for the thumbnail stored on GCS.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        $thumb = $this->attributes['thumbnail'] ?? null;
        if (!$thumb) return null;
        try {
            return Storage::disk('gcs')->temporaryUrl($thumb, now()->addDay());
        } catch (\Throwable $e) {
            return null;
        }
    }
}
