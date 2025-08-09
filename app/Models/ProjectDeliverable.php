<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectDeliverable extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'milestone_id',
        'name',
        'description',
        'details',
        'status',
        'due_date',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'details' => 'array',
        'due_date' => 'date',
        'completed_at' => 'date',
    ];

    /**
     * Get the project that owns the deliverable.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the milestone associated with this deliverable.
     */
    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    /**
     * Get the tasks associated with this deliverable.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
