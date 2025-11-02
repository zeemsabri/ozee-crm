<?php

namespace App\Models;

use App\Models\Traits\Taggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShareableResource extends Model
{
    use HasFactory, SoftDeletes, Taggable;

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
        'visible_to_team',
        'is_private',
        'sent_push',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'visible_to_client' => 'boolean',
        'visible_to_team' => 'boolean',
        'is_private' => 'boolean',
        'sent_push' => 'boolean',
    ];

    /**
     * Boot the model and add a global scope to exclude notices.
     * This applies only to the base ShareableResource model, not subclasses like NoticeBoard.
     */
    protected static function booted()
    {
        if (static::class === self::class) {
            static::addGlobalScope('exclude_notice', function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('notice', false)->orWhereNull('notice');
                });
            });
        }
    }

    /**
     * Get the user who created the resource.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_shareable_resource')
            ->withTimestamps();
    }

    /**
     * Determine if the resource is a YouTube video.
     */
    public function isYouTube(): bool
    {
        return $this->type === 'youtube';
    }

    /**
     * Determine if the resource is a website.
     */
    public function isWebsite(): bool
    {
        return $this->type === 'website';
    }
}
