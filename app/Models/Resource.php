<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Traits\Taggable;

class Resource extends Model
{
    use HasFactory, Taggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'url',
        'file_id',
        'resourceable_id',
        'resourceable_type',
        'description',
        'requires_approval',
        'visible_to_client',
    ];

    /**
     * Get the parent resourceable model.
     */
    public function resourceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determine if the resource is a link.
     *
     * @return bool
     */
    public function isLink(): bool
    {
        return $this->type === 'link';
    }

    /**
     * Determine if the resource is a file.
     *
     * @return bool
     */
    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    /**
     * Get all of the resource's comments.
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
