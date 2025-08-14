<?php

namespace App\Services;

use App\Models\Milestone;
use App\Models\MonthlyBudget;
use App\Models\MonthlyPoint;
use App\Models\ProjectExpendable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BonusCalculationService
{
    protected CurrencyConversionService $currencyService;

    // Constants for bonus calculations
    private const PROJECT_PERFORMANCE_BONUS_PERCENTAGE = 0.05;

    public function __construct(CurrencyConversionService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Orchestrates the calculation of all monthly bonuses and returns a structured response.
     *
     * @param int $year
     * @param int $month
     * @return array
     */
    public function calculateMonthlyBonuses(int $year, int $month): array
    {
        $monthlyBudget = MonthlyBudget::where('year', $year)->where('month', $month)->first();
        if (!$monthlyBudget) {
            return ['error' => 'Monthly budget not found for the specified period.'];
        }

        $leaderboard = MonthlyPoint::with('user')
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('total_points', 'desc')
            ->get();

        $employees = $leaderboard->filter(fn ($item) => $item->user->user_type === 'employee')->values();
        $contractors = $leaderboard->filter(fn ($item) => $item->user->user_type === 'contractor')->values();
        $allUsers = $employees->merge($contractors);

        // Calculate all awards
        list($highAchieverAwards, $distributedHighAchieverBonus) = $this->calculateHighAchieverAwards($employees, $monthlyBudget);
        list($consistentContributorAward, $consistentContributorPoolDistributed) = $this->calculateConsistentContributorAward($employees, $monthlyBudget);
        list($projectPerformanceAwards, $distributedProjectPerformanceBonus) = $this->calculateProjectPerformanceBonuses($monthlyBudget);

        $topPerformerPool = $monthlyBudget->contractor_bonus_pool_pkr - $distributedProjectPerformanceBonus;
        list($topPerformerAwards, $distributedTopPerformerBonus) = $this->calculateContractorTopPerformerBonuses($contractors, $monthlyBudget, $topPerformerPool);

        $managersChoicePool = $topPerformerPool - $distributedTopPerformerBonus;
        list($managersChoiceAwards, $distributedManagersChoiceBonus, $mostImprovedRecommendations) = $this->calculateManagersChoiceBonuses($employees, $contractors, $monthlyBudget, $managersChoicePool);

        // Consolidate all awards into a single, comprehensive list
        $allAwards = [
            'high_achiever_awards' => [
                'award_id' => 'high_achiever_awards',
                'award_name' => 'High Achiever Awards',
                'user_type' => 'employee',
                'bonus_pool_pkr' => round((float)$monthlyBudget->high_achiever_pool_pkr, 2),
                'distributed_pkr' => round($distributedHighAchieverBonus, 2),
                'recipients' => $highAchieverAwards,
            ],
            'consistent_contributor' => [
                'award_id' => 'consistent_contributor_awards',
                'award_name' => 'Consistent Contributor',
                'user_type' => 'employee',
                'bonus_pool_pkr' => round((float)$monthlyBudget->consistent_contributor_pool_pkr, 2),
                'distributed_pkr' => round($consistentContributorPoolDistributed, 2),
                'recipients' => $consistentContributorAward ? [$consistentContributorAward] : [],
            ],
            'project_performance_bonus' => [
                'award_id' => 'project_performance_bonus',
                'award_name' => 'Project Performance Bonus',
                'user_type' => 'contractor',
                'bonus_pool_pkr' => round((float)$monthlyBudget->contractor_bonus_pool_pkr, 2),
                'distributed_pkr' => round($distributedProjectPerformanceBonus, 2),
                'recipients' => $projectPerformanceAwards,
            ],
            'top_contractor' => [
                'award_id' => 'top_contractor',
                'award_name' => 'Top Contractor (Performance-based)',
                'user_type' => 'contractor',
                'distributed_pkr' => round($distributedTopPerformerBonus, 2),
                'recipients' => $topPerformerAwards,
            ],
            'managers_choice_most_improved' => [
                'award_id' => 'managers_choice_most_improved',
                'award_name' => 'Most Improved',
                'user_type' => 'both',
                'distributed_pkr' => round($distributedManagersChoiceBonus, 2),
                'recipients' => $managersChoiceAwards,
            ],
        ];

        // Build the user summary list
        $usersSummary = $this->buildUsersSummary($allUsers, $allAwards, $monthlyBudget);

        $totalDistributedEmployee = $allAwards['high_achiever_awards']['distributed_pkr'] + $allAwards['consistent_contributor']['distributed_pkr'];
        $totalDistributedContractor = $allAwards['project_performance_bonus']['distributed_pkr'] + $allAwards['top_contractor']['distributed_pkr'] + $allAwards['managers_choice_most_improved']['distributed_pkr'];
        $totalDistributed = $totalDistributedEmployee + $totalDistributedContractor;

        // Build the final response
        return [
            'period' => Carbon::create($year, $month, 1)->format('F Y'),
            'summary' => [
                'total_budget_pkr' => round((float)$monthlyBudget->total_budget_pkr, 2),
                'total_distributed_pkr' => round($totalDistributed, 2),
                'pools' => [
                    'employee' => [
                        'allocated_pkr' => round((float)$monthlyBudget->employee_bonus_pool_pkr, 2),
                        'distributed_pkr' => round($totalDistributedEmployee, 2),
                        'types' => [
                            'high_achiever_pkr' => round((float)$monthlyBudget->high_achiever_pool_pkr, 2),
                            'consistent_contributor_pkr' => round((float)$monthlyBudget->consistent_contributor_pool_pkr, 2),
                        ]
                    ],
                    'contractor' => [
                        'allocated_pkr' => round((float)$monthlyBudget->contractor_bonus_pool_pkr, 2),
                        'distributed_pkr' => round($totalDistributedContractor, 2),
                        'types' => [
                            'project_performance_pkr' => round((float)$monthlyBudget->contractor_bonus_pool_pkr, 2)
                        ]
                    ],
                    'managers_choice' => [
                        'allocated_pkr' => round($managersChoicePool, 2),
                        'distributed_pkr' => round($distributedManagersChoiceBonus, 2)
                    ]
                ]
            ],
            'users' => $usersSummary,
            'awards_details' => array_values($allAwards)
        ];
    }

    /**
     * Builds a summary of all users and their total bonuses.
     *
     * @param Collection $allUsers
     * @param array $allAwards
     * @param MonthlyBudget $monthlyBudget
     * @return array
     */
    private function buildUsersSummary(Collection $allUsers, array $allAwards, MonthlyBudget $monthlyBudget): array
    {
        $usersSummary = [];
        $previousMonth = Carbon::create($monthlyBudget->year, $monthlyBudget->month, 1)->subMonth();
        $previousMonthPoints = MonthlyPoint::where('year', $previousMonth->year)
            ->where('month', $previousMonth->month)
            ->get()
            ->keyBy('user_id');

        foreach ($allUsers as $userPoints) {
            $userId = $userPoints->user_id;
            $userBonusTotal = 0;
            $pointIncrease = 0;
            $previousPoints = $previousMonthPoints->get($userId);

            if ($previousPoints) {
                $pointIncrease = $userPoints->total_points - $previousPoints->total_points;
            }

            foreach ($allAwards as $awardCategory) {
                foreach ($awardCategory['recipients'] as $recipient) {
                    if ($recipient['user_id'] === $userId) {
                        $userBonusTotal += (float) ($recipient['amount_pkr'] ?? $recipient['amount'] ?? 0);
                    }
                }
            }

            $usersSummary[] = [
                'user_id' => $userId,
                'name' => $userPoints->user->name,
                'user_type' => $userPoints->user->user_type,
                'total_points' => round((float)$userPoints->total_points, 2),
                'total_bonus_pkr' => round($userBonusTotal, 2),
                'point_increase_from_last_month' => round($pointIncrease, 2),
            ];
        }

        return $usersSummary;
    }

    /**
     * Calculates awards for the top 3 high-achieving employees.
     *
     * @param Collection $employees
     * @param MonthlyBudget $monthlyBudget
     * @return array [array $highAchieverAwards, float $distributedAmount]
     */
    private function calculateHighAchieverAwards(Collection $employees, MonthlyBudget $monthlyBudget): array
    {
        $highAchieverAwards = [];
        $distributedAmount = 0.0;
        $highAchievers = $employees->take(3);
        $highAchieverBonusAmounts = [
            1 => $monthlyBudget->first_place_award_pkr,
            2 => $monthlyBudget->second_place_award_pkr,
            3 => $monthlyBudget->third_place_award_pkr,
        ];

        foreach ($highAchievers as $index => $userPoints) {
            $rank = $index + 1;
            $userId = $userPoints->user_id;
            $amount = (float)$highAchieverBonusAmounts[$rank];
            $distributedAmount += $amount;

            $highAchieverAwards[] = [
                'user_id' => $userId,
                'points' => round((float)$userPoints->total_points, 2),
                'award_title' => "Top Performer #{$rank}",
                'amount_pkr' => round($amount, 2),
            ];
        }
        return [$highAchieverAwards, $distributedAmount];
    }

    /**
     * Calculates the Consistent Contributor award.
     *
     * @param Collection $employees
     * @param MonthlyBudget $monthlyBudget
     * @return array [array|null $award, float $distributedAmount]
     */
    private function calculateConsistentContributorAward(Collection $employees, MonthlyBudget $monthlyBudget): array
    {
        $consistentContributorAward = null;
        $distributedAmount = 0.0;
        $previousMonth = Carbon::create($monthlyBudget->year, $monthlyBudget->month, 1)->subMonth();
        $previousMonthPoints = MonthlyPoint::where('year', $previousMonth->year)
            ->where('month', $previousMonth->month)
            ->get()
            ->keyBy('user_id');

        $highestCombinedPoints = 0;
        $consistentContributor = null;

        foreach ($employees as $userPoints) {
            $previousPoints = $previousMonthPoints->get($userPoints->user_id);
            $combinedPoints = $userPoints->total_points + ($previousPoints->total_points ?? 0);
            if ($combinedPoints > $highestCombinedPoints) {
                $highestCombinedPoints = $combinedPoints;
                $consistentContributor = $userPoints;
            }
        }

        if ($consistentContributor) {
            $userId = $consistentContributor->user_id;
            $amount = (float)$monthlyBudget->consistent_contributor_pool_pkr;
            $distributedAmount = $amount;

            $consistentContributorAward = [
                'user_id' => $userId,
                'points' => round((float)$consistentContributor->total_points, 2),
                'award_title' => 'Consistent Contributor',
                'amount_pkr' => round($amount, 2),
            ];
        }

        return [$consistentContributorAward, $distributedAmount];
    }

    /**
     * Calculates Project Performance Bonuses for contractors.
     *
     * @param MonthlyBudget $monthlyBudget
     * @return array [array $performanceAwards, float $distributedAmount]
     */
    private function calculateProjectPerformanceBonuses(MonthlyBudget $monthlyBudget): array
    {
        $distributedAmount = 0.0;
        $performanceAwards = [];
        $startOfMonth = Carbon::create($monthlyBudget->year, $monthlyBudget->month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($monthlyBudget->year, $monthlyBudget->month, 1)->endOfMonth();

        $onTimeApprovedMilestones = Milestone::where('status', Milestone::APPROVED)
            ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
            ->whereColumn('completed_at', '<=', 'completion_date')
            ->get();

        $bonuses = [];
        foreach ($onTimeApprovedMilestones as $milestone) {
            $agreements = $milestone->expendable()->where('status', ProjectExpendable::STATUS_ACCEPTED)->get();

            foreach ($agreements as $agreement) {
                $agreedAmountInPkr = $this->currencyService->convert((float) $agreement->amount, $agreement->currency, 'PKR');
                $bonusAmount = $agreedAmountInPkr * self::PROJECT_PERFORMANCE_BONUS_PERCENTAGE;
                $userId = $agreement->user_id;

                if (!isset($bonuses[$userId])) {
                    $bonuses[$userId] = [];
                }

                $bonuses[$userId][] = [
                    'project_name' => $milestone->project->name,
                    'agreed_amount' => $agreedAmountInPkr,
                    'bonus_amount' => $bonusAmount,
                ];
            }
        }

        foreach ($bonuses as $userId => $bonusDetails) {
            $user = User::find($userId);
            $userPoints = MonthlyPoint::where('user_id', $userId)
                ->where('year', $monthlyBudget->year)
                ->where('month', $monthlyBudget->month)
                ->first();

            $groupedProjectBonuses = [];
            foreach ($bonusDetails as $bonus) {
                if (!isset($groupedProjectBonuses[$bonus['project_name']])) {
                    $groupedProjectBonuses[$bonus['project_name']] = [
                        'total_agreed_amount_pkr' => 0,
                        'total_bonus_amount_pkr' => 0,
                        'milestone_count' => 0,
                    ];
                }
                $groupedProjectBonuses[$bonus['project_name']]['total_agreed_amount_pkr'] += $bonus['agreed_amount'];
                $groupedProjectBonuses[$bonus['project_name']]['total_bonus_amount_pkr'] += $bonus['bonus_amount'];
                $groupedProjectBonuses[$bonus['project_name']]['milestone_count']++;
            }

            $awardsForUser = [];
            foreach ($groupedProjectBonuses as $projectName => $projectData) {
                $awardsForUser[] = [
                    'award_title' => 'Project Performance Bonus',
                    'amount_pkr' => round($projectData['total_bonus_amount_pkr'], 2),
                    'project_details' => [
                        'project_name' => $projectName,
                        'total_agreed_amount_pkr' => round($projectData['total_agreed_amount_pkr'], 2),
                        'bonus_reason' => "{$projectData['milestone_count']} milestone(s) completed on time, resulting in a 5% bonus of the total agreed amount."
                    ]
                ];
            }

            $performanceAwards[] = [
                'user_id' => $userId,
                'points' => round((float)($userPoints->total_points ?? 0), 2),
                'awards' => $awardsForUser,
            ];

            $distributedAmount += array_sum(array_column($groupedProjectBonuses, 'total_bonus_amount_pkr'));
        }
        return [$performanceAwards, $distributedAmount];
    }

    /**
     * Calculates the top 3 contractor bonuses based on points and remaining pool.
     *
     * @param Collection $contractors
     * @param MonthlyBudget $monthlyBudget
     * @param float $remainingPool
     * @return array [array $topPerformerAwards, float $distributedAmount]
     */
    private function calculateContractorTopPerformerBonuses(Collection $contractors, MonthlyBudget $monthlyBudget, float $remainingPool): array
    {
        $distributedAmount = 0.0;
        $topPerformerAwards = [];
        $topContractors = $contractors->take(3);

        $totalCalculatedBonus = 0.0;
        $individualCalculatedBonuses = [];

        foreach ($topContractors as $userPoints) {
            $calculatedBonus = $userPoints->total_points * $monthlyBudget->points_value_pkr;
            $individualCalculatedBonuses[$userPoints->user_id] = $calculatedBonus;
            $totalCalculatedBonus += $calculatedBonus;
        }

        $multiplier = ($totalCalculatedBonus > 0 && $totalCalculatedBonus > $remainingPool) ? $remainingPool / $totalCalculatedBonus : 1;

        foreach ($topContractors as $userPoints) {
            $userId = $userPoints->user_id;
            $amount = $individualCalculatedBonuses[$userId] * $multiplier;
            $distributedAmount += $amount;

            $topPerformerAwards[] = [
                'user_id' => $userId,
                'points' => round((float)$userPoints->total_points, 2),
                'award_title' => 'Top Contractor (Performance-based)',
                'amount_pkr' => round($amount, 2),
                'bonus_details' => "Bonus calculated from {$userPoints->total_points} points at {$monthlyBudget->points_value_pkr} PKR/point."
            ];
        }

        return [$topPerformerAwards, $distributedAmount];
    }

    /**
     * Calculates Manager's Choice bonuses from the remaining contractor pool.
     *
     * @param Collection $employees
     * @param Collection $contractors
     * @param MonthlyBudget $monthlyBudget
     * @param float $remainingContractorPool
     * @return array [array $awards, float $distributedAmount, array $recommendations]
     */
    private function calculateManagersChoiceBonuses(Collection $employees, Collection $contractors, MonthlyBudget $monthlyBudget, float $remainingContractorPool): array
    {
        $awards = [];
        $distributedAmount = 0.0;
        $employeeRecommendations = $this->calculateMostImprovedRecommendations($employees, $monthlyBudget);
        $contractorRecommendations = $this->calculateMostImprovedRecommendations($contractors, $monthlyBudget);
        $combinedRecommendations = $employeeRecommendations->concat($contractorRecommendations->toArray())->sortByDesc('point_increase')->values();

        $recommendations = [
            'employees' => $employeeRecommendations->take(5)->toArray(),
            'contractors' => $contractorRecommendations->take(5)->toArray(),
            'combined' => $combinedRecommendations->take(5)->toArray(),
        ];

        // Manager's choice bonus pools
        $teamMostImprovedPool = $remainingContractorPool * 0.5;
        $employeeMostImprovedPool = $remainingContractorPool * 0.25;
        $contractorMostImprovedPool = $remainingContractorPool * 0.25;

        $awardedUserIds = [];

        // Team Most Improved
        if ($combinedRecommendations->isNotEmpty()) {
            $winner = $combinedRecommendations->first();
            $awardedUserIds[] = $winner['user_id'];
            $userPoints = MonthlyPoint::where('user_id', $winner['user_id'])->where('year', $monthlyBudget->year)->where('month', $monthlyBudget->month)->first();
            $calculatedBonus = $winner['point_increase'] * $monthlyBudget->points_value_pkr;
            $bonusAmount = min($calculatedBonus, $teamMostImprovedPool);

            $awards[] = [
                'user_id' => $winner['user_id'],
                'points' => round((float)($userPoints->total_points ?? 0), 2),
                'award_title' => 'Team Most Improved',
                'amount_pkr' => round($bonusAmount, 2),
                'bonus_details' => "Bonus for a point increase of {$winner['point_increase']} from last month."
            ];
            $distributedAmount += $bonusAmount;
        }

        // Employee Most Improved

        $employeeWinner = null;
        foreach($employeeRecommendations as $recommendation) {
            if(in_array($recommendation['user_id'], $awardedUserIds)) {
                continue;
            }
            $winner = $recommendation;
            $employeeWinner = $winner['user_id'];
        }

        if ($employeeRecommendations->isNotEmpty() && $employeeWinner && !in_array($employeeWinner, $awardedUserIds)) {
            $winner = $employeeRecommendations->where('user_id', $employeeWinner)->first();
            $userPoints = MonthlyPoint::where('user_id', $winner['user_id'])->where('year', $monthlyBudget->year)->where('month', $monthlyBudget->month)->first();
            $calculatedBonus = $winner['point_increase'] * $monthlyBudget->points_value_pkr;
            $bonusAmount = min($calculatedBonus, $employeeMostImprovedPool);

            $awards[] = [
                'user_id' => $winner['user_id'],
                'points' => round((float)($userPoints->total_points ?? 0), 2),
                'award_title' => 'Employee Most Improved',
                'amount_pkr' => round($bonusAmount, 2),
                'bonus_details' => "Bonus for a point increase of {$winner['point_increase']} from last month."
            ];
            $distributedAmount += $bonusAmount;
        }

        // Contractor Most Improved
        if ($contractorRecommendations->isNotEmpty() && !in_array($contractorRecommendations->first()['user_id'], $awardedUserIds)) {
            $winner = $contractorRecommendations->first();
            $userPoints = MonthlyPoint::where('user_id', $winner['user_id'])->where('year', $monthlyBudget->year)->where('month', $monthlyBudget->month)->first();
            $calculatedBonus = $winner['point_increase'] * $monthlyBudget->points_value_pkr;
            $bonusAmount = min($calculatedBonus, $contractorMostImprovedPool);

            $awards[] = [
                'user_id' => $winner['user_id'],
                'points' => round((float)($userPoints->total_points ?? 0), 2),
                'award_title' => 'Contractor Most Improved',
                'amount_pkr' => round($bonusAmount, 2),
                'bonus_details' => "Bonus for a point increase of {$winner['point_increase']} from last month."
            ];
            $distributedAmount += $bonusAmount;
        }

        return [$awards, $distributedAmount, $recommendations];
    }

    /**
     * Calculates recommendations for the "Most Improved" award.
     *
     * @param Collection $users
     * @param MonthlyBudget $monthlyBudget
     * @return Collection
     */
    private function calculateMostImprovedRecommendations(Collection $users, MonthlyBudget $monthlyBudget): Collection
    {
        $previousMonth = Carbon::create($monthlyBudget->year, $monthlyBudget->month, 1)->subMonth();
        $previousMonthPoints = MonthlyPoint::where('year', $previousMonth->year)
            ->where('month', $previousMonth->month)
            ->get()
            ->keyBy('user_id');

        $mostImprovedRecommendations = new Collection();
        foreach ($users as $userPoints) {
            $userId = $userPoints->user_id;
            $previousPoints = $previousMonthPoints->get($userId);
            $pointIncrease = 0;
            if ($previousPoints) {
                $pointIncrease = $userPoints->total_points - $previousPoints->total_points;
            }

            if ($pointIncrease > 0) {
                $mostImprovedRecommendations->push([
                    'user_id' => $userId,
                    'name' => $userPoints->user->name,
                    'user_type' => $userPoints->user->user_type,
                    'point_increase' => $pointIncrease,
                ]);
            }
        }
        return $mostImprovedRecommendations->sortByDesc('point_increase')->values();
    }
}
