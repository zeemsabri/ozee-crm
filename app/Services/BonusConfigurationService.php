<?php

namespace App\Services;

use App\Models\BonusConfiguration;
use App\Models\BonusConfigurationGroup;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class BonusConfigurationService
{
    /**
     * Get all active bonus configurations for a project and source type.
     *
     * @param Project $project The project
     * @param string $sourceType The source type (standup, task, milestone, etc.)
     * @return Collection The applicable bonus configurations
     */
    public function getApplicableConfigurations(Project $project, string $sourceType): Collection
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
     * Get all active bonus configurations for a user across all their projects.
     *
     * @param User $user The user
     * @param string|null $sourceType Optional source type filter
     * @return Collection The applicable bonus configurations
     */
    public function getUserConfigurations(User $user, ?string $sourceType = null): Collection
    {
        // Get all projects the user is assigned to
        $projects = $user->projects;

        // Collect all configurations from these projects
        $configs = collect();

        foreach ($projects as $project) {
            $projectConfigs = $this->getApplicableConfigurations($project, $sourceType ?: 'standup');
            $configs = $configs->merge($projectConfigs);
        }

        return $configs->unique('id');
    }

    /**
     * Get all active bonus configurations of a specific type (bonus/penalty).
     *
     * @param Project $project The project
     * @param string $type The configuration type (bonus/penalty)
     * @param string|null $sourceType Optional source type filter
     * @return Collection The applicable bonus configurations
     */
    public function getConfigurationsByType(Project $project, string $type, ?string $sourceType = null): Collection
    {
        $configs = collect();

        // Get all bonus configuration groups attached to this project
        $groups = $project->bonusConfigurationGroups()->where('is_active', true)->get();

        foreach ($groups as $group) {
            $query = $group->bonusConfigurations()
                ->where('isActive', true)
                ->where('type', $type);

            if ($sourceType) {
                $query->where('appliesTo', $sourceType);
            }

            $groupConfigs = $query->get();
            $configs = $configs->merge($groupConfigs);
        }

        return $configs;
    }

    /**
     * Calculate the total bonus amount for a user on a project.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime|null $startDate Optional start date for filtering
     * @param \DateTime|null $endDate Optional end date for filtering
     * @return float The total bonus amount
     */
    public function calculateTotalBonus(User $user, Project $project, ?\DateTime $startDate = null, ?\DateTime $endDate = null): float
    {
        $query = $user->bonusTransactions()
            ->where('project_id', $project->id)
            ->where('type', 'bonus')
            ->where('status', 'processed');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->format('Y-m-d'));
        }

        return $query->sum('amount');
    }

    /**
     * Calculate the total penalty amount for a user on a project.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime|null $startDate Optional start date for filtering
     * @param \DateTime|null $endDate Optional end date for filtering
     * @return float The total penalty amount
     */
    public function calculateTotalPenalty(User $user, Project $project, ?\DateTime $startDate = null, ?\DateTime $endDate = null): float
    {
        $query = $user->bonusTransactions()
            ->where('project_id', $project->id)
            ->where('type', 'penalty')
            ->where('status', 'processed');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->format('Y-m-d'));
        }

        return $query->sum('amount');
    }

    /**
     * Calculate the net bonus amount (bonuses minus penalties) for a user on a project.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime|null $startDate Optional start date for filtering
     * @param \DateTime|null $endDate Optional end date for filtering
     * @return float The net bonus amount
     */
    public function calculateNetBonus(User $user, Project $project, ?\DateTime $startDate = null, ?\DateTime $endDate = null): float
    {
        $totalBonus = $this->calculateTotalBonus($user, $project, $startDate, $endDate);
        $totalPenalty = $this->calculateTotalPenalty($user, $project, $startDate, $endDate);

        return $totalBonus - $totalPenalty;
    }

    /**
     * Get a summary of bonus/penalty transactions for a user on a project.
     *
     * @param User $user The user
     * @param Project $project The project
     * @param \DateTime|null $startDate Optional start date for filtering
     * @param \DateTime|null $endDate Optional end date for filtering
     * @return array The summary data
     */
    public function getUserBonusSummary(User $user, Project $project, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $query = $user->bonusTransactions()
            ->where('project_id', $project->id)
            ->where('status', 'processed');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->format('Y-m-d'));
        }

        $transactions = $query->get();

        $bonusTransactions = $transactions->where('type', 'bonus');
        $penaltyTransactions = $transactions->where('type', 'penalty');

        $totalBonus = $bonusTransactions->sum('amount');
        $totalPenalty = $penaltyTransactions->sum('amount');
        $netBonus = $totalBonus - $totalPenalty;

        return [
            'total_bonus' => $totalBonus,
            'total_penalty' => $totalPenalty,
            'net_bonus' => $netBonus,
            'bonus_count' => $bonusTransactions->count(),
            'penalty_count' => $penaltyTransactions->count(),
            'transactions' => $transactions,
        ];
    }
}
