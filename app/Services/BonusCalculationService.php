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

    public function __construct(CurrencyConversionService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Calculates the projected monthly bonuses for all users based on the month's budget and points.
     * This service is for pre-transaction review and does not update the database.
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

        $employees = $leaderboard->filter(function ($item) {
            return $item->user->user_type === 'employee';
        })->values();

        $contractors = $leaderboard->filter(function ($item) {
            return $item->user->user_type === 'contractor';
        })->values();

        // Calculate and process all employee bonuses
        list($employeeBonusSummary, $employeeMetrics) = $this->calculateEmployeeBonuses($employees, $monthlyBudget);

        // Calculate and process all contractor bonuses
        list($contractorBonusSummary, $contractorMetrics) = $this->calculateContractorBonuses($contractors, $monthlyBudget);

        return [
            'period' => Carbon::create($year, $month, 1)->format('F Y'),
            'total_budget' => $monthlyBudget->total_budget_pkr,
            'employee_pool_allocated' => $monthlyBudget->employee_bonus_pool_pkr,
            'contractor_pool_allocated' => $monthlyBudget->contractor_bonus_pool_pkr,
            'employees' => $employeeBonusSummary,
            'contractors' => $contractorBonusSummary,
            'team_metrics' => $employeeMetrics,
            'contractor_metrics' => $contractorMetrics,
        ];
    }

    /**
     * Calculates all bonuses for employees.
     *
     * @param Collection $employees
     * @param MonthlyBudget $monthlyBudget
     * @return array
     */
    private function calculateEmployeeBonuses(Collection $employees, MonthlyBudget $monthlyBudget): array
    {
        $employeeAwards = [];
        $awards = [
            1 => $monthlyBudget->first_place_award_pkr,
            2 => $monthlyBudget->second_place_award_pkr,
            3 => $monthlyBudget->third_place_award_pkr,
        ];

        // --- First Pass: Initialize entries and calculate 'Most Improved' metrics ---
        $previousMonth = Carbon::create($monthlyBudget->year, $monthlyBudget->month, 1)->subMonth();
        $previousMonthPoints = MonthlyPoint::where('year', $previousMonth->year)
            ->where('month', $previousMonth->month)
            ->get()
            ->keyBy('user_id');

        $mostImprovedRecommendations = new Collection();
        foreach ($employees as $employeePoints) {
            $userId = $employeePoints->user_id;
            $previousPoints = $previousMonthPoints->get($userId);
            $pointIncrease = 0;
            if ($previousPoints) {
                $pointIncrease = $employeePoints->total_points - $previousPoints->total_points;
            }

            // Add to the most improved recommendations list
            $mostImprovedRecommendations->push([
                'user_id' => $userId,
                'name' => $employeePoints->user->name,
                'point_increase' => $pointIncrease,
            ]);
        }

        // Sort recommendations by point increase
        $mostImprovedRecommendations = $mostImprovedRecommendations->sortByDesc('point_increase');
        $mostImprovedRecommendations = $mostImprovedRecommendations->take(5)->values(); // Top 5 recommendations

        // --- Second Pass: Assign specific awards to the initialized entries ---

        // High Achiever Awards
        $highAchievers = $employees->take(3);
        foreach ($highAchievers as $index => $employeePoints) {
            $rank = $index + 1;
            $userId = $employeePoints->user_id;
            if (!isset($employeeAwards[$userId])) {
                $employeeAwards[$userId] = [
                    'user_id' => $userId,
                    'name' => $employeePoints->user->name,
                    'user_type' => $employeePoints->user->user_type,
                    'points' => $employeePoints->total_points,
                    'awards' => [],
                ];
            }
            $employeeAwards[$userId]['awards'][] = [
                'award' => "Top Performer #{$rank}",
                'amount' => $monthlyBudget->{"rank_{$rank}_award_pkr"},
            ];
        }

        // Consistent Contributor Tiered Bonus
        $consistentPool = $monthlyBudget->consistent_contributor_pool_pkr;
        $qualifyingEmployees = [];
        $totalTargetBonus = 0;

        foreach ($employees as $employeePoints) {
            $userPoints = $employeePoints->total_points;
            $targetBonus = 0;
            $tier = 'None';
            if ($userPoints >= 2000) {
                $targetBonus = 2000;
                $tier = 'Gold';
            } elseif ($userPoints >= 1500) {
                $targetBonus = 1000;
                $tier = 'Silver';
            } elseif ($userPoints >= 1000) {
                $targetBonus = 500;
                $tier = 'Bronze';
            }

            if ($targetBonus > 0) {
                $qualifyingEmployees[] = ['employee_points' => $employeePoints, 'tier' => $tier, 'target_bonus' => $targetBonus];
                $totalTargetBonus += $targetBonus;
            }
        }

        $multiplier = ($totalTargetBonus > 0 && $consistentPool < $totalTargetBonus) ? $consistentPool / $totalTargetBonus : 1;

        foreach ($qualifyingEmployees as $qualifyingEmployee) {
            $finalAmount = $qualifyingEmployee['target_bonus'] * $multiplier;
            $userId = $qualifyingEmployee['employee_points']->user_id;
            if (!isset($employeeAwards[$userId])) {
                $employeeAwards[$userId] = [
                    'user_id' => $userId,
                    'name' => $qualifyingEmployee['employee_points']->user->name,
                    'user_type' => $qualifyingEmployee['employee_points']->user->user_type,
                    'points' => $qualifyingEmployee['employee_points']->total_points,
                    'awards' => [],
                ];
            }
            $employeeAwards[$userId]['awards'][] = [
                'award' => "Consistent Contributor ({$qualifyingEmployee['tier']} Tier)",
                'amount' => $finalAmount,
                'adjusted' => $multiplier < 1,
            ];
        }

        // Prepare the metrics and recommendations for the final output
        $employeeMetrics = [
            'most_improved' => [
                'award' => 'Most Improved',
                'amount' => $monthlyBudget->most_improved_award_pkr,
                'recommendations' => $mostImprovedRecommendations->toArray(),
            ],
        ];

        return [array_values($employeeAwards), $employeeMetrics];
    }

    /**
     * Calculates all bonuses for contractors.
     *
     * @param Collection $contractors
     * @param MonthlyBudget $monthlyBudget
     * @return array
     */
    private function calculateContractorBonuses(Collection $contractors, MonthlyBudget $monthlyBudget): array
    {
        $contractorAwards = [];
        $monthlyPerformanceBonuses = [];

        // Contractor of the Month - Divided amongst top 3
        $topContractors = $contractors->take(3);
        $topContractorPool = $monthlyBudget->contractor_of_the_month_award_pkr;
        $numTopContractors = $topContractors->count();
        $topContractorAwardAmount = $numTopContractors > 0 ? $topContractorPool / $numTopContractors : 0;

        foreach ($topContractors as $index => $contractorPoints) {
            $rank = $index + 1;
            $userId = $contractorPoints->user_id;

            if (!isset($contractorAwards[$userId])) {
                $contractorAwards[$userId] = [
                    'user_id' => $userId,
                    'name' => $contractorPoints->user->name,
                    'user_type' => $contractorPoints->user->user_type,
                    'points' => $contractorPoints->total_points,
                    'awards' => [],
                ];
            }
            $contractorAwards[$userId]['awards'][] = [
                'award' => "Top Contractor #{$rank}",
                'amount' => $topContractorAwardAmount,
            ];
        }

        // Project Performance Bonuses
        $startOfMonth = Carbon::create($monthlyBudget->year, $monthlyBudget->month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($monthlyBudget->year, $monthlyBudget->month, 1)->endOfMonth();

        // Fetch approved milestones that were completed on time within the month
        $onTimeApprovedMilestones = Milestone::where('status', Milestone::APPROVED)
            ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
            ->whereColumn('completed_at', '<=', 'completion_date')
            ->get();

        // Process bonuses for each on-time, approved milestone
        foreach ($onTimeApprovedMilestones as $milestone) {
            $agreements = $milestone->expendable()
                ->where('status', ProjectExpendable::STATUS_ACCEPTED)
                ->get();

            foreach ($agreements as $agreement) {
                $agreedAmountInPkr = $this->currencyService->convert((float) $agreement->amount, $agreement->currency, 'PKR');
                $bonusAmount = $agreedAmountInPkr * 0.05;
                $userId = $agreement->user_id;

                if (!isset($monthlyPerformanceBonuses[$userId])) {
                    $monthlyPerformanceBonuses[$userId] = [
                        'amount' => 0,
                        'name' => $agreement->user->name,
                        'projects' => [],
                    ];
                }
                $monthlyPerformanceBonuses[$userId]['amount'] += $bonusAmount;
                $monthlyPerformanceBonuses[$userId]['projects'][] = $agreement->project->name;
            }
        }

        foreach ($monthlyPerformanceBonuses as $userId => $bonusData) {
            if (!isset($contractorAwards[$userId])) {
                $user = User::find($userId);
                $contractorAwards[$userId] = [
                    'user_id' => $userId,
                    'name' => $user ? $user->name : 'N/A',
                    'user_type' => $user->user_type ?? 'contractor',
                    'points' => null,
                    'awards' => [],
                ];
            }
            $projectNames = implode(', ', array_unique($bonusData['projects']));
            $contractorAwards[$userId]['awards'][] = [
                'award' => 'Project Performance Bonus',
                'amount' => $bonusData['amount'],
                'details' => "Sum of approved bonuses for: {$projectNames}",
            ];
        }

        $contractorMetrics = [
            'project_performance_bonus_pool' => [
                'award' => 'Project Performance Bonus Pool',
                'amount' => $monthlyBudget->contractor_bonus_pool_pkr - $monthlyBudget->contractor_of_the_month_award_pkr,
            ],
        ];

        return [array_values($contractorAwards), $contractorMetrics];
    }
}
