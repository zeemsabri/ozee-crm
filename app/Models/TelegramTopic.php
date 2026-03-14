<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TelegramTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'topicable_id',
        'topicable_type',
        'telegram_thread_id',
        'name',
        'type',
        'is_private',
    ];

    /**
     * Get the parent topicable model (Project, Task, Milestone, etc.).
     */
    public function topicable()
    {
        return $this->morphTo();
    }

    /**
     * Get the project that owns this topic.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the chat messages sent in this topic.
     */
    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }
}
