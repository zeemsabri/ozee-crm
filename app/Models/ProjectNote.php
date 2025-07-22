<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'content',
        'user_id',
        'chat_message_id',
        'parent_id',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(ProjectNote::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ProjectNote::class, 'parent_id');
    }

    public function isParent()
    {
        return is_null($this->parent_id);
    }

    public function replyCount()
    {
        return $this->replies()->count();
    }
}
