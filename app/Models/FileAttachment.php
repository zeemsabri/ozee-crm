<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

//    protected $appends = [
//        'content_url',
//    ];

    public function fileable()
    {
        return $this->morphTo();
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the content URL, formatted for embedding based on its type.
     * This accessor will automatically be called when you access $deliverable->content_url.
     *
     * @param string|null $value The raw content_url from the database.
     * @return string|null The formatted content URL for embedding.
     */
//    public function getContentUrlAttribute()
//    {
//
//        $value = $this->attributes['path'];
//        // If there's no content_url, return null
//        if (empty($value)) {
//            return null;
//        }
//
//        $url = $value;
//
//        // Step 1: Clean any query parameters from Google Drive/Docs links
//        // This applies to both file links and doc links that might have these parameters
//        if (str_contains($url, 'drive.google.com/') || str_contains($url, 'docs.google.com/')) {
//            $url = strtok($url, '?');
//        }
//
//        // Step 2: Transform to /preview for Google Drive files and Docs
//        // This regex will match /view or /edit at the end of the URL path,
//        // optionally followed by a trailing slash, and replace it with /preview.
//        // It also handles cases where /preview might already exist, or if it's just the ID.
//        if (str_contains($url, 'drive.google.com/file/d/') ||
//            str_contains($url, 'docs.google.com/document/d/') ||
//            str_contains($url, 'docs.google.com/spreadsheets/d/') ||
//            str_contains($url, 'docs.google.com/presentation/d/')) {
//
//            // If it already ends with /preview, do nothing
//            if (str_ends_with($url, '/preview')) {
//                return $url;
//            }
//
//            // Replace /view or /edit with /preview, handling optional trailing slashes
//            $url = preg_replace('/\/(view|edit)(\/)?$/', '/preview', $url);
//
//            // If after replacement, it still doesn't end with /preview and is a file/doc link,
//            // it means it was just the ID part, so append /preview.
//            // This ensures that links like "https://drive.google.com/file/d/FILE_ID" become "https://drive.google.com/file/d/FILE_ID/preview"
//            if (!str_ends_with($url, '/preview') &&
//                (str_contains($url, 'drive.google.com/file/d/') ||
//                    str_contains($url, 'docs.google.com/document/d/') ||
//                    str_contains($url, 'docs.google.com/spreadsheets/d/') ||
//                    str_contains($url, 'docs.google.com/presentation/d/'))) {
//                $url .= '/preview';
//            }
//        }
//
//        // Step 3: Apply specific transformations based on content_url_type for other cases (like video)
//        switch ($this->mime_type) {
//            case 'video':
//                // Basic conversion for YouTube/Vimeo embed links
//                if (str_contains($url, 'youtube.com/watch?v=')) {
//                    return str_replace('watch?v=', 'embed/', $url);
//                } elseif (str_contains($url, 'youtu.be/')) {
//                    return str_replace('youtu.be/', 'youtube.com/embed/', $url);
//                } elseif (str_contains($url, 'vimeo.com/')) {
//                    return str_replace('vimeo.com/', 'player.vimeo.com/video/', $url);
//                }
//                return $url; // Return original if not a recognized video embed pattern
//
//            case 'other':
//            default:
//                return $url;
//        }
//    }
}
