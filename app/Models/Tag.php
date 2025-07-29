<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($tag) {
            // Generate slug from name if not provided
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * Get the tasks associated with this tag.
     */
    public function tasks()
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }

    /**
     * Get the projects associated with this tag.
     */
    public function projects()
    {
        return $this->morphedByMany(Project::class, 'taggable');
    }

    /**
     * Get the documents associated with this tag.
     */
    public function documents()
    {
        return $this->morphedByMany(Document::class, 'taggable');
    }

    /**
     * Get the emails associated with this tag.
     */
    public function emails()
    {
        return $this->morphedByMany(Email::class, 'taggable');
    }

    /**
     * Get the milestones associated with this tag.
     */
    public function milestones()
    {
        return $this->morphedByMany(Milestone::class, 'taggable');
    }

    /**
     * Get the project notes associated with this tag.
     */
    public function projectNotes()
    {
        return $this->morphedByMany(ProjectNote::class, 'taggable');
    }

    /**
     * Get the resources associated with this tag.
     */
    public function resources()
    {
        return $this->morphedByMany(Resource::class, 'taggable');
    }

    /**
     * Get the clients associated with this tag.
     */
    public function clients()
    {
        return $this->morphedByMany(Client::class, 'taggable');
    }
}
