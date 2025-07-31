<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Taggable;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShareableResource extends Model
{
    use HasFactory, Taggable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'url',
        'type',
        'thumbnail_url',
        'created_by',
        'visible_to_client',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'visible_to_client' => 'boolean',
    ];

    /**
     * Get the user who created the resource.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Determine if the resource is a YouTube video.
     *
     * @return bool
     */
    public function isYouTube(): bool
    {
        return $this->type === 'youtube';
    }

    /**
     * Determine if the resource is a website.
     *
     * @return bool
     */
    public function isWebsite(): bool
    {
        return $this->type === 'website';
    }
}
