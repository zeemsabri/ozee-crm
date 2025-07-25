<?php

namespace App\Services;

use App\Models\BonusConfiguration;
use App\Models\BonusTransaction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BonusProcessor
{
    /**
     * Process a standup submission for potential bonus/penalty.
     *
     * @param int $userId The user who submitted the standup
     * @param int $projectId The project the standup is for
     * @param string $standupId The ID of the standup
     * @param \DateTime $submissionDate The date the standup was submitted
     * @return BonusTransaction|null The created transaction, if any
     */
    public function processStandupSubmission($userId, $projectId, $standupId, $submissionDate)
    {
        try {
            $project = Project::findOrFail($projectId);
            $user = User::findOrFail($userId);

            // Get applicable bonus configurations for this project
            $bonusConfigs = $this->getApplicableBonusConfigurations($project, 'standup');

            if ($bonusConfigs->isEmpty()) {
                Log::info("No applicable standup bonus configurations found for project {$projectId}");
                return null;
            }

            // For each applicable configuration, check if conditions are met
            foreach ($bonusConfigs as $config) {
                if ($this->shouldApplyStandupBonus($user, $project, $submissionDate, $config)) {
                    // Create and return the bonus transaction
                    return $this->createBonusTransaction(
                        $user->id,
                        $project->id,
                        $config->id,
                        'bonus',
                        $this->calculateBonusAmount($config),
                        "Daily standup bonus for " . $submissionDate->format('Y-m-d'),
                        'standup',
                        $standupId
                    );
                }
            }

            // Check for standup missed penalties
            $penaltyConfigs = $this->getApplicableBonusConfigurations($project, 'standup_missed');

            if (!$penaltyConfigs->isEmpty() && $this->shouldApplyStandupMissedPenalty($user, $project, $submissionDate)) {
                $config = $penaltyConfigs->first();

                // Create and return the penalty transaction
                return $this->createBonusTransaction(
                    $user->id,
                    $project->id,
                    $config->id,
                    'penalty',
                    $this->calculatePenaltyAmount($config),
                    "Missed daily standup penalty for " . $submissionDate->format('Y-m-d'),
                    'standup_missed',
                    null
                );
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error processing standup submission: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Process a task completion for potential bonus/penalty.
     *
     * @param int $userId The user who completed the task
     * @param int $projectId The project the task is for
     * @param string $taskId The ID of the task
     * @param \DateTime $completionDate The date the task was completed
     * @param \DateTime $dueDate The date the task was due
     * @return BonusTransaction|null The created transaction, if any
     */
    public function processTaskCompletion($userId, $projectId, $taskId, $completionDate, $dueDate)
    {
        try {
            $project = Project::findOrFail($projectId);
            $user = User::findOrFail($userId);

            // Check if task was completed on time
            $isOnTime = $completionDate <= $dueDate;

            if ($isOnTime) {
                // Get applicable bonus configurations for on-time task completion
                $bonusConfigs = $this->getApplicableBonusConfigurations($project, 'task');

                if ($bonusConfigs->isEmpty()) {
                    Log::info("No applicable task bonus configurations found for project {$projectId}");
                    return null;
                }

                // For each applicable configuration, check if conditions are met
                foreach ($bonusConfigs as $config) {
                    if ($this->shouldApplyTaskBonus($user, $project, $completionDate, $dueDate, $config)) {
                        // Create and return the bonus transaction
                        return $this->createBonusTransaction(
                            $user->id,
                            $project->id,
                            $config->id,
                            'bonus',
                            $this->calculateBonusAmount($config),
                            "Task completion bonus for task {$taskId}",
                            'task',
                            $taskId
                        );
                    }
                }
            } else {
                // Get applicable penalty configurations for late task completion
                $penaltyConfigs = $this->getApplicableBonusConfigurations($project, 'late_task');

                if ($penaltyConfigs->isEmpty()) {
                    Log::info("No applicable late task penalty configurations found for project {$projectId}");
                    return null;
                }

                // For each applicable configuration, check if conditions are met
                foreach ($penaltyConfigs as $config) {
                    if ($this->shouldApplyLateTaskPenalty($user, $project, $completionDate, $dueDate, $config)) {
                        // Create and return the penalty transaction
                        return $this->createBonusTransaction(
                            $user->id,
                            $project->id,
                            $config->id,
                            'penalty',
                            $this->calculatePenaltyAmount($config),
                            "Late task completion penalty for task {$taskId}",
                            'late_task',
                            $taskId
                        );
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error processing task completion: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Process a milestone completion for potential bonus/penalty.
     *
     * @param int $userId The user who completed the milestone
     * @param int $projectId The project the milestone is for
     * @param string $milestoneId The ID of the milestone
     * @param \DateTime $completionDate The date the milestone was completed
     * @param \DateTime $dueDate The date the milestone was due
     * @return BonusTransaction|null The created transaction, if any
     */
    public function processMilestoneCompletion($userId, $projectId, $milestoneId, $completionDate, $dueDate)
    {
        try {
            $project = Project::findOrFail($projectId);
            $user = User::findOrFail($userId);

            // Check if milestone was completed on time
            $isOnTime = $completionDate <= $dueDate;

            if ($isOnTime) {
                // Get applicable bonus configurations for on-time milestone completion
                $bonusConfigs = $this->getApplicableBonusConfigurations($project, 'milestone');

                if ($bonusConfigs->isEmpty()) {
                    Log::info("No applicable milestone bonus configurations found for project {$projectId}");
                    return null;
                }

                // For each applicable configuration, check if conditions are met
                foreach ($bonusConfigs as $config) {
                    if ($this->shouldApplyMilestoneBonus($user, $project, $completionDate, $dueDate, $config)) {
                        // Create and return the bonus transaction
                        return $this->createBonusTransaction(
                            $user->id,
                            $project->id,
                            $config->id,
                            'bonus',
                            $this->calculateBonusAmount($config),
                            "Milestone completion bonus for milestone {$milestoneId}",
                            'milestone',
                            $milestoneId
                        );
                    }
                }
            } else {
                // Get applicable penalty configurations for late milestone completion
                $penaltyConfigs = $this->getApplicableBonusConfigurations($project, 'late_milestone');

                if ($penaltyConfigs->isEmpty()) {
                    Log::info("No applicable late milestone penalty configurations found for project {$projectId}");
                    return null;
                }

                // For each applicable configuration, check if conditions are met
                foreach ($penaltyConfigs as $config) {
                    if ($this->shouldApplyLateMilestonePenalty($user, $project, $completionDate, $dueDate, $config)) {
                        // Create and return the penalty transaction
                        return $this->createBonusTransaction(
                            $user->id,
                            $project->id,
                            $config->id,
                            'penalty',
                            $this->calculatePenaltyAmount($config),
                            "Late milestone completion penalty for milestone {$milestoneId}",
                            'late_milestone',
                            $milestoneId
                        );
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error processing milestone completion: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get applicable bonus configurations for a project and source type.
     *
     * @param Project $project The project
     * @param string $sourceType The source type (standup, task, milestone, etc.)
     * @return \Illuminate\Support\Collection The applicable bonus configurations
     */
    public function getApplicableBonusConfigurations(Project $project, $sourceType)
    {
        // Get all bonus configuration groups attached to this project
        $groups = $project->bonusConfigurationGroups()->where('is_active', true)->get();

        // Collect all configurations from these groups that match the source type
        $configs = collect();

        foreach ($groups as $group) {
            $groupConfigs = $group->bonusConfigurations()
                ->where('isActive', true)
                ->where('appliesTo', $sourceType)
                ->get();

            $configs = $configs->merge($groupConfigs);
        }

        return $configs;
    }

    /**
     * Check if a standup bonus should be applied.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime $submissionDate The date the standup was submitted
     * @param BonusConfiguration $config The bonus configuration
     * @return bool Whether the bonus should be applied
     */
    protected function shouldApplyStandupBonus(User $user, Project $project, $submissionDate, BonusConfiguration $config)
    {
        // Check if this is a workday (Monday to Friday)
        $dayOfWeek = $submissionDate->format('N');
        if ($dayOfWeek > 5) {
            return false; // Weekend, no bonus
        }

        // Check if the user already received a standup bonus for this day
        $existingTransaction = BonusTransaction::where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->where('source_type', 'standup')
            ->whereDate('created_at', $submissionDate->format('Y-m-d'))
            ->exists();

        if ($existingTransaction) {
            return false; // Already received a bonus for this day
        }

        // Additional conditions can be added here

        return true;
    }

    /**
     * Check if a standup missed penalty should be applied.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime $date The date to check
     * @return bool Whether the penalty should be applied
     */
    protected function shouldApplyStandupMissedPenalty(User $user, Project $project, $date)
    {
        // Check if this is a workday (Monday to Friday)
        $dayOfWeek = $date->format('N');
        if ($dayOfWeek > 5) {
            return false; // Weekend, no penalty
        }

        // Check if the user already has a standup for this day
        $hasStandup = false; // This would need to be implemented based on your standup model

        if ($hasStandup) {
            return false; // User submitted a standup, no penalty
        }

        // Check if the user already received a penalty for this day
        $existingTransaction = BonusTransaction::where('user_id', $user->id)
            ->where('project_id', $project->id)
            ->where('source_type', 'standup_missed')
            ->whereDate('created_at', $date->format('Y-m-d'))
            ->exists();

        if ($existingTransaction) {
            return false; // Already received a penalty for this day
        }

        // Additional conditions can be added here

        return true;
    }

    /**
     * Check if a task bonus should be applied.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime $completionDate The date the task was completed
     * @param \DateTime $dueDate The date the task was due
     * @param BonusConfiguration $config The bonus configuration
     * @return bool Whether the bonus should be applied
     */
    protected function shouldApplyTaskBonus(User $user, Project $project, $completionDate, $dueDate, BonusConfiguration $config)
    {
        // Check if the task was completed on time
        if ($completionDate > $dueDate) {
            return false; // Task was late, no bonus
        }

        // Additional conditions can be added here

        return true;
    }

    /**
     * Check if a late task penalty should be applied.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime $completionDate The date the task was completed
     * @param \DateTime $dueDate The date the task was due
     * @param BonusConfiguration $config The penalty configuration
     * @return bool Whether the penalty should be applied
     */
    protected function shouldApplyLateTaskPenalty(User $user, Project $project, $completionDate, $dueDate, BonusConfiguration $config)
    {
        // Check if the task was completed late
        if ($completionDate <= $dueDate) {
            return false; // Task was on time, no penalty
        }

        // Additional conditions can be added here

        return true;
    }

    /**
     * Check if a milestone bonus should be applied.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime $completionDate The date the milestone was completed
     * @param \DateTime $dueDate The date the milestone was due
     * @param BonusConfiguration $config The bonus configuration
     * @return bool Whether the bonus should be applied
     */
    protected function shouldApplyMilestoneBonus(User $user, Project $project, $completionDate, $dueDate, BonusConfiguration $config)
    {
        // Check if the milestone was completed on time
        if ($completionDate > $dueDate) {
            return false; // Milestone was late, no bonus
        }

        // Additional conditions can be added here

        return true;
    }

    /**
     * Check if a late milestone penalty should be applied.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime $completionDate The date the milestone was completed
     * @param \DateTime $dueDate The date the milestone was due
     * @param BonusConfiguration $config The penalty configuration
     * @return bool Whether the penalty should be applied
     */
    protected function shouldApplyLateMilestonePenalty(User $user, Project $project, $completionDate, $dueDate, BonusConfiguration $config)
    {
        // Check if the milestone was completed late
        if ($completionDate <= $dueDate) {
            return false; // Milestone was on time, no penalty
        }

        // Additional conditions can be added here

        return true;
    }

    /**
     * Calculate the bonus amount based on the configuration.
     *
     * @param BonusConfiguration $config The bonus configuration
     * @return float The calculated bonus amount
     */
    protected function calculateBonusAmount(BonusConfiguration $config)
    {
        // For percentage bonuses, we would need to know the base amount
        // For now, we'll just return the value from the configuration
        return $config->value;
    }

    /**
     * Calculate the penalty amount based on the configuration.
     *
     * @param BonusConfiguration $config The penalty configuration
     * @return float The calculated penalty amount
     */
    protected function calculatePenaltyAmount(BonusConfiguration $config)
    {
        // For percentage penalties, we would need to know the base amount
        // For now, we'll just return the value from the configuration
        return $config->value;
    }

    /**
     * Create a bonus transaction.
     *
     * @param int $userId The user ID
     * @param int $projectId The project ID
     * @param int $configId The bonus configuration ID
     * @param string $type The transaction type (bonus/penalty)
     * @param float $amount The transaction amount
     * @param string $description The transaction description
     * @param string $sourceType The source type (standup/task/milestone)
     * @param string|null $sourceId The source ID
     * @return BonusTransaction The created transaction
     */
    protected function createBonusTransaction($userId, $projectId, $configId, $type, $amount, $description, $sourceType, $sourceId = null)
    {
        return BonusTransaction::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'bonus_configuration_id' => $configId,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'status' => 'pending', // Default status
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'metadata' => [
                'created_at' => now()->toIso8601String(),
                'created_by' => 'system',
            ],
        ]);
    }
}
