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
        'mime_type',
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
        return $this->morphMany(ProjectNote::class, 'noteable');
    }

    /**
     * Get the content URL, formatted for embedding based on its type.
     * This accessor will automatically be called when you access $deliverable->content_url.
     *
     * @param  string|null  $value  The raw content_url from the database.
     * @return string|null The formatted content URL for embedding.
     */
    public function getContentUrlAttribute($value)
    {
        // If there's no content_url, return null
        if (empty($value)) {
            return null;
        }

        $url = $value;

        // Step 1: Clean any query parameters from Google Drive/Docs links
        // This applies to both file links and doc links that might have these parameters
        if (str_contains($url, 'drive.google.com/') || str_contains($url, 'docs.google.com/')) {
            $url = strtok($url, '?');
        }

        // Step 2: Transform to /preview for Google Drive files and Docs
        // This regex will match /view or /edit at the end of the URL path,
        // optionally followed by a trailing slash, and replace it with /preview.
        // It also handles cases where /preview might already exist, or if it's just the ID.
        if (str_contains($url, 'drive.google.com/file/d/') ||
            str_contains($url, 'docs.google.com/document/d/') ||
            str_contains($url, 'docs.google.com/spreadsheets/d/') ||
            str_contains($url, 'docs.google.com/presentation/d/')) {

            // If it already ends with /preview, do nothing
            if (str_ends_with($url, '/preview')) {
                return $url;
            }

            // Replace /view or /edit with /preview, handling optional trailing slashes
            $url = preg_replace('/\/(view|edit)(\/)?$/', '/preview', $url);

            // If after replacement, it still doesn't end with /preview and is a file/doc link,
            // it means it was just the ID part, so append /preview.
            // This ensures that links like "https://drive.google.com/file/d/FILE_ID" become "https://drive.google.com/file/d/FILE_ID/preview"
            if (! str_ends_with($url, '/preview') &&
                (str_contains($url, 'drive.google.com/file/d/') ||
                    str_contains($url, 'docs.google.com/document/d/') ||
                    str_contains($url, 'docs.google.com/spreadsheets/d/') ||
                    str_contains($url, 'docs.google.com/presentation/d/'))) {
                $url .= '/preview';
            }
        }

        // Step 3: Apply specific transformations based on content_url_type for other cases (like video)
        switch ($this->mime_type) {
            case 'video':
                // Basic conversion for YouTube/Vimeo embed links
                if (str_contains($url, 'youtube.com/watch?v=')) {
                    return str_replace('watch?v=', 'embed/', $url);
                } elseif (str_contains($url, 'youtu.be/')) {
                    return str_replace('youtu.be/', 'youtube.com/embed/', $url);
                } elseif (str_contains($url, 'vimeo.com/')) {
                    return str_replace('vimeo.com/', 'player.vimeo.com/video/', $url);
                }

                return $url; // Return original if not a recognized video embed pattern

            case 'other':
            default:
                return $url;
        }
    }
}
