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

    /** @deprecated use App\Enums\MilestoneStatus::Pending */
    public const PENDING = \App\Enums\MilestoneStatus::Pending->value;
    /** @deprecated use App\Enums\MilestoneStatus::Approved */
    public const APPROVED = \App\Enums\MilestoneStatus::Approved->value;
    /** @deprecated use App\Enums\MilestoneStatus::Rejected */
    public const REJECTED = \App\Enums\MilestoneStatus::Rejected->value;
    /** @deprecated use App\Enums\MilestoneStatus::Completed */
    public const COMPLETED = \App\Enums\MilestoneStatus::Completed->value;
    /** @deprecated use App\Enums\MilestoneStatus::InProgress */
    public const IN_PROGRESS = \App\Enums\MilestoneStatus::InProgress->value;
    /** @deprecated use App\Enums\MilestoneStatus::Overdue */
    public const OVERDUE = \App\Enums\MilestoneStatus::Overdue->value;
    /** @deprecated use App\Enums\MilestoneStatus::Canceled */
    public const CANCELED = \App\Enums\MilestoneStatus::Canceled->value;
    /** @deprecated use App\Enums\MilestoneStatus::Expired */
    public const EXPIRED = \App\Enums\MilestoneStatus::Expired->value;
    /** @deprecated use App\Enums\MilestoneStatus::PendingApproval */
    public const PENDING_APPROVAL = \App\Enums\MilestoneStatus::PendingApproval->value;
    /** @deprecated use App\Enums\MilestoneStatus::PendingReview */
    public const PENDING_REVIEW = \App\Enums\MilestoneStatus::PendingReview->value;

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
        'status' => \App\Enums\MilestoneStatus::class,
    ];

    protected static function booted()
    {
        // Dispatch the standup event after the note has been created so it has a persisted ID
        static::updated(function (Milestone $milestone) {
            if ($milestone->status === \App\Enums\MilestoneStatus::Approved) {
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
        return $this->status === \App\Enums\MilestoneStatus::Completed;
    }

    /**
     * Check if the milestone is overdue.
     *
     * @return bool
     */
    public function isOverdue()
    {
        return $this->status === \App\Enums\MilestoneStatus::Overdue ||
               ($this->completion_date && $this->completion_date->isPast() && !$this->isCompleted());
    }

    /**
     * Mark the milestone as completed.
     *
     * @return void
     */
    public function markAsCompleted()
    {
        $this->status = \App\Enums\MilestoneStatus::Completed;
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
        $this->status = \App\Enums\MilestoneStatus::InProgress;
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
