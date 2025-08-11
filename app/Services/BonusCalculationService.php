<?php

namespace App\Services;

use App\Models\MonthlyBudget;
use App\Models\MonthlyPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BonusCalculationService
{
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
        })->values(); // Reset the keys to be zero-indexed

        $contractors = $leaderboard->filter(function ($item) {
            return $item->user->user_type === 'contractor';
        })->values(); // Reset the keys to be zero-indexed

        // Calculate and process all employee bonuses
        $employeeBonusSummary = $this->calculateEmployeeBonuses($employees, $monthlyBudget);

        // Calculate and process all contractor bonuses
        $contractorBonusSummary = $this->calculateContractorBonuses($contractors, $monthlyBudget);

        return [
            'period' => Carbon::create($year, $month, 1)->format('F Y'),
            'total_budget' => $monthlyBudget->total_budget_pkr,
            'employee_pool_allocated' => $monthlyBudget->employee_bonus_pool_pkr,
            'contractor_pool_allocated' => $monthlyBudget->contractor_bonus_pool_pkr,
            'employees' => $employeeBonusSummary,
            'contractors' => $contractorBonusSummary,
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

        // --- First Pass: Assign specific awards to the initialized entries ---

        // High Achiever Awards
        $highAchievers = $employees->take(3);
        foreach ($highAchievers as $index => $employeePoints) {
            $rank = $index + 1;
            $userId = $employeePoints->user_id;

            // Check and initialize the user's awards array if it doesn't exist
            if (!isset($employeeAwards[$userId])) {
                $employeeAwards[$userId] = [
                    'user_id' => $userId,
                    'name' => $employeePoints->user->name,
                    'points' => $employeePoints->total_points,
                    'awards' => [],
                ];
            }
            $employeeAwards[$userId]['awards'][] = [
                'award' => "Top Performer #{$rank}",
                'amount' => $awards[$rank],
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

            // This is the key fix: Initialize the array if the user hasn't been added yet
            if (!isset($employeeAwards[$userId])) {
                $employeeAwards[$userId] = [
                    'user_id' => $userId,
                    'name' => $qualifyingEmployee['employee_points']->user->name,
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

        // Most Improved Award (placeholder)
        $mostImprovedAwardUser = 'most_improved_user';
        $employeeAwards[$mostImprovedAwardUser] = [
            'user_id' => null,
            'name' => 'Manager to Select',
            'points' => null,
            'awards' => [
                [
                    'award' => 'Most Improved',
                    'amount' => $monthlyBudget->most_improved_award_pkr,
                ],
            ],
        ];

        return array_values($employeeAwards);
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

        // Contractor of the Month
        if ($contractors->isNotEmpty()) {
            $topContractor = $contractors->first();
            $userId = $topContractor->user_id;

            if (!isset($contractorAwards[$userId])) {
                $contractorAwards[$userId] = [
                    'user_id' => $userId,
                    'name' => $topContractor->user->name,
                    'points' => $topContractor->total_points,
                    'awards' => [],
                ];
            }
            $contractorAwards[$userId]['awards'][] = [
                'award' => 'Contractor of the Month',
                'amount' => $monthlyBudget->contractor_of_the_month_award_pkr,
            ];
        }

        // Project Performance Bonus Pool (This is a pool, not an individual award)
        $performancePoolUser = 'performance_pool_user';
        $contractorAwards[$performancePoolUser] = [
            'user_id' => null,
            'name' => 'N/A',
            'points' => null,
            'awards' => [
                [
                    'award' => 'Project Performance Bonus Pool',
                    'amount' => $monthlyBudget->contractor_bonus_pool_pkr - $monthlyBudget->contractor_of_the_month_award_pkr,
                ],
            ],
        ];

        return array_values($contractorAwards);
    }
}
