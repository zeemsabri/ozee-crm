<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusConfiguration extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'amountType',
        'value',
        'appliesTo',
        'targetBonusTypeForRevocation',
        'isActive',
        'uuid',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'decimal:2',
        'isActive' => 'boolean',
    ];

    /**
     * Get the user that owns the bonus configuration.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bonus configuration groups that include this configuration.
     */
    public function bonusConfigurationGroups()
    {
        return $this->belongsToMany(BonusConfigurationGroup::class, 'bonus_configuration_group_items', 'configuration_id', 'group_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * Get the transactions that were created using this configuration.
     */
    public function transactions()
    {
        return $this->hasMany(BonusTransaction::class, 'bonus_configuration_id');
    }

    /**
     * Check if this configuration is applicable to the given source type.
     *
     * @param  string  $sourceType  The source type (standup, task, milestone, etc.)
     * @return bool Whether the configuration is applicable
     */
    public function isApplicableTo($sourceType)
    {
        return $this->isActive && $this->appliesTo === $sourceType;
    }

    /**
     * Calculate the bonus/penalty amount based on the configuration.
     *
     * @param  float|null  $baseAmount  The base amount to calculate from (for percentage types)
     * @return float The calculated amount
     */
    public function calculateAmount($baseAmount = null)
    {
        // For 'all_related_bonus' type, the amount is determined elsewhere
        if ($this->amountType === 'all_related_bonus') {
            return 0;
        }

        // For percentage type, calculate based on the base amount
        if ($this->amountType === 'percentage' && $baseAmount !== null) {
            return ($this->value / 100) * $baseAmount;
        }

        // For fixed amount or when no base amount is provided
        return $this->value;
    }

    /**
     * Check if this configuration should be applied to a standup submission.
     *
     * @param  User  $user  The user
     * @param  Project  $project  The project
     * @param  \DateTime  $submissionDate  The date the standup was submitted
     * @return bool Whether the configuration should be applied
     */
    public function shouldApplyToStandup(User $user, Project $project, $submissionDate)
    {
        // Check if this is a workday (Monday to Friday)
        $dayOfWeek = $submissionDate->format('N');
        if ($dayOfWeek > 5) {
            return false; // Weekend, no bonus/penalty
        }

        // Check if the user already received a transaction for this day
        $existingTransaction = BonusTransaction::where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->where('bonus_configuration_id', $this->id)
            ->whereDate('created_at', $submissionDate->format('Y-m-d'))
            ->exists();

        if ($existingTransaction) {
            return false; // Already received a transaction for this day
        }

        return true;
    }

    /**
     * Check if this configuration should be applied to a task completion.
     *
     * @param  User  $user  The user
     * @param  Project  $project  The project
     * @param  \DateTime  $completionDate  The date the task was completed
     * @param  \DateTime  $dueDate  The date the task was due
     * @return bool Whether the configuration should be applied
     */
    public function shouldApplyToTask(User $user, Project $project, $completionDate, $dueDate)
    {
        // For on-time task bonus
        if ($this->appliesTo === 'task') {
            // Check if the task was completed on time
            if ($completionDate > $dueDate) {
                return false; // Task was late, no bonus
            }
        }

        // For late task penalty
        if ($this->appliesTo === 'late_task') {
            // Check if the task was completed late
            if ($completionDate <= $dueDate) {
                return false; // Task was on time, no penalty
            }
        }

        // Check if the user already received a transaction for this task
        $existingTransaction = BonusTransaction::where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->where('bonus_configuration_id', $this->id)
            ->where('source_type', $this->appliesTo)
            ->exists();

        if ($existingTransaction) {
            return false; // Already received a transaction for this task
        }

        return true;
    }

    /**
     * Check if this configuration should be applied to a milestone completion.
     *
     * @param  User  $user  The user
     * @param  Project  $project  The project
     * @param  \DateTime  $completionDate  The date the milestone was completed
     * @param  \DateTime  $dueDate  The date the milestone was due
     * @return bool Whether the configuration should be applied
     */
    public function shouldApplyToMilestone(User $user, Project $project, $completionDate, $dueDate)
    {
        // For on-time milestone bonus
        if ($this->appliesTo === 'milestone') {
            // Check if the milestone was completed on time
            if ($completionDate > $dueDate) {
                return false; // Milestone was late, no bonus
            }
        }

        // For late milestone penalty
        if ($this->appliesTo === 'late_milestone') {
            // Check if the milestone was completed late
            if ($completionDate <= $dueDate) {
                return false; // Milestone was on time, no penalty
            }
        }

        // Check if the user already received a transaction for this milestone
        $existingTransaction = BonusTransaction::where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->where('bonus_configuration_id', $this->id)
            ->where('source_type', $this->appliesTo)
            ->exists();

        if ($existingTransaction) {
            return false; // Already received a transaction for this milestone
        }

        return true;
    }
}
