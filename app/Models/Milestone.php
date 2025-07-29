<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Taggable;

class Milestone extends Model
{
    use HasFactory, Taggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'completion_date',
        'actual_completion_date',
        'status',
        'project_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'completion_date' => 'date',
        'actual_completion_date' => 'date',
    ];

    /**
     * Get the project that owns the milestone.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tasks associated with this milestone.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Check if the milestone is completed.
     *
     * @return bool
     */
    public function isCompleted()
    {
        return $this->status === 'Completed';
    }

    /**
     * Check if the milestone is overdue.
     *
     * @return bool
     */
    public function isOverdue()
    {
        return $this->status === 'Overdue' ||
               ($this->completion_date && $this->completion_date->isPast() && !$this->isCompleted());
    }

    /**
     * Mark the milestone as completed.
     *
     * @return void
     */
    public function markAsCompleted()
    {
        $this->status = 'Completed';
        $this->actual_completion_date = now();
        $this->save();
    }

    /**
     * Start the milestone (change status to In Progress).
     *
     * @return void
     */
    public function start()
    {
        $this->status = 'In Progress';
        $this->save();
    }
}
