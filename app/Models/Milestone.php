<?php

namespace App\Models;

use App\Events\MilestoneApprovedEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Taggable;
/*
* @property \Illuminate\Support\Carbon|null $approved_at
* @property \Illuminate\Support\Carbon|null $mark_completed_at
* @property \Illuminate\Support\Carbon $created_at
* @property \Illuminate\Support\Carbon $updated_at
*
 * @property-read \Carbon\Carbon|null $due_date
* @property-read \Carbon\Carbon|null $submitted_at
* @property-read \Carbon\Carbon|null $finalized_at
*/
class Milestone extends Model
{
    use HasFactory, Taggable;

    const PENDING = 'pending';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';
    const COMPLETED = 'completed';
    const IN_PROGRESS = 'in progress';
    const OVERDUE = 'overdue';
    const CANCELED = 'canceled';
    const EXPIRED = 'expired';
    const PENDING_APPROVAL = 'pending approval';
    const PENDING_REVIEW = 'pending review';

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
        'completed_at',
        'approved_at',
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
        'mark_completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function booted()
    {
        // Dispatch the standup event after the note has been created so it has a persisted ID
        static::updated(function (Milestone $milestone) {
            if($milestone->status === self::APPROVED) {
                MilestoneApprovedEvent::dispatch($milestone); //This will reward points to each user in milestone
            }
        });
    }

    /**
     * Get the due date for the milestone.
     *
     * @return Null|Carbon
     */
    public function getDueDateAttribute(): Null|Carbon
    {
        return $this->completion_date;
    }

    /**
     * Get the submitted at timestamp for the milestone.
     *
     * @return Carbon|null
     */
    public function getSubmittedAtAttribute(): ?Carbon
    {
        return $this->mark_completed_at;
    }

    /**
     * Get the finalized at timestamp for the milestone.
     *
     * @return Carbon|null
     */
    public function getFinalizedAtAttribute(): ?Carbon
    {
        return $this->completed_at;
    }

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

    /**
     * Get the project deliverables associated with this milestone.
     */
    public function projectDeliverables()
    {
        return $this->hasMany(ProjectDeliverable::class);
    }

    /**
     * Expendable items related to this milestone (polymorphic).
     */
    public function expendable()
    {
        return $this->morphMany(ProjectExpendable::class, 'expendable')->whereNotNull('user_id');
    }

    public function budget()
    {
        return $this->morphOne(ProjectExpendable::class, 'expendable')->whereNull('user_id');
    }

    /**
     * Notes associated with this milestone.
     */
    public function notes()
    {
        return $this->morphMany(ProjectNote::class, 'noteable');
    }
}
