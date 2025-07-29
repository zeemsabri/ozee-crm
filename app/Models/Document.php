<?php

namespace App\Models;

use App\Services\GoogleChatService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\Traits\Taggable;

class Document extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, Taggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'project_id',
        'path',
        'filename',
        'google_drive_file_id',
        'thumbnail',
        'upload_error',
        'mime_type',
        'file_size',
    ];

    /**
     * Get the project that owns the document.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the full URL for the document.
     *
     * @return string
     */
    public function getUrlAttribute()
    {
        return asset('storage/' . $this->path);
    }

    public function notes()
    {
        return $this->morphMany(ProjectNote::class, 'noteable');
    }

    /**
     * Add a note to the task's thread in the project's Google Chat space.
     *
     * @param string $note
     * @param User|Client $user
     * @return ProjectNote $projectNote
     */
    public function addNote(string $note, User|Client $user)
    {

        // Get project_id from milestone if available
        $projectId = $this->project_id ?? null;

        // Save the note to the database using the polymorphic relationship
        return $this->notes()->create([
            'content' => $note,
            'creator_id' => $user->id,
            'creator_type' => get_class($user),
            'type' => 'note',
            'project_id' => $projectId,
        ]);

    }
}
