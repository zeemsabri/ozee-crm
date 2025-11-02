<?php

namespace App\Services;

use App\Models\MonthlyBudget;
use App\Models\User;

class BonusService
{
    /**
     * Calculates the monthly leaderboard.
     *
     * @param  int  $year
     * @param  int  $month
     * @return \Illuminate\Support\Collection
     */
    public function getMonthlyLeaderboard($year, $month)
    {
        return User::with(['monthlyPoints' => function ($query) use ($year, $month) {
            $query->where('year', $year)
                ->where('month', $month)
                ->orderBy('total_points', 'desc');
        }])->get();
    }

    /**
     * Calculates and distributes the bonuses for a given month and year.
     * This method combines all the bonus logic.
     *
     * @param  int  $year
     * @param  int  $month
     * @return array
     */
    public function distributeMonthlyBonuses($year, $month)
    {
        $leaderboard = $this->getMonthlyLeaderboard($year, $month);
        $monthlyBudget = MonthlyBudget::where('year', $year)->where('month', $month)->first();
        if (! $monthlyBudget) {
            return ['error' => 'Monthly budget not found.'];
        }

        $bonuses = [
            'employees' => [],
            'contractors' => [],
            'project_performance' => [],
        ];

        // Process employee bonuses
        $employeeLeaderboard = $leaderboard->filter(function ($item) {
            return $item->user->user_type === 'employee';
        })->values();

        $this->processEmployeeBonuses($employeeLeaderboard, $monthlyBudget, $bonuses);

        // Process contractor bonuses
        $contractorLeaderboard = $leaderboard->filter(function ($item) {
            return $item->user->user_type === 'contractor';
        })->values();

        $this->processContractorBonuses($contractorLeaderboard, $monthlyBudget, $bonuses);

        // This is a manual step, so we'll just return the required information for the manager dashboard
        // The Project Performance Bonus is also separate, so we'll handle it on a per-project basis.

        return $bonuses;
    }

    /**
     * Processes and calculates all employee bonuses for the month.
     *
     * @param  \Illuminate\Support\Collection  $employeeLeaderboard
     * @param  MonthlyBudget  $monthlyBudget
     * @param  array  $bonuses
     */
    private function processEmployeeBonuses($employeeLeaderboard, $monthlyBudget, &$bonuses)
    {
        // High Achiever Awards
        $awards = [
            'first_place' => $monthlyBudget->first_place_award_pkr,
            'second_place' => $monthlyBudget->second_place_award_pkr,
            'third_place' => $monthlyBudget->third_place_award_pkr,
        ];

        foreach ($awards as $key => $amount) {
            if (isset($employeeLeaderboard[$key])) {
                $user = $employeeLeaderboard[$key]->user;
                $bonuses['employees'][] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'award' => $key,
                    'amount' => $amount,
                ];
            }
        }

        // Consistent Contributor Tiered Bonus
        $totalQualifyingBonus = 0;
        $qualifyingEmployees = [];

        foreach ($employeeLeaderboard as $item) {
            $userPoints = $item->total_points;
            if ($userPoints >= 2000) {
                $totalQualifyingBonus += 2000;
                $qualifyingEmployees[] = ['user_id' => $item->user_id, 'user_name' => $item->user->name, 'tier' => 'Gold', 'target_bonus' => 2000];
            } elseif ($userPoints >= 1500) {
                $totalQualifyingBonus += 1000;
                $qualifyingEmployees[] = ['user_id' => $item->user_id, 'user_name' => $item->user->name, 'tier' => 'Silver', 'target_bonus' => 1000];
            } elseif ($userPoints >= 1000) {
                $totalQualifyingBonus += 500;
                $qualifyingEmployees[] = ['user_id' => $item->user_id, 'user_name' => $item->user->name, 'tier' => 'Bronze', 'target_bonus' => 500];
            }
        }

        $consistentPool = $monthlyBudget->consistent_contributor_pool_pkr;
        $bonusMultiplier = $totalQualifyingBonus > 0 ? $consistentPool / $totalQualifyingBonus : 0;

        foreach ($qualifyingEmployees as $qualifyingEmployee) {
            $bonuses['employees'][] = [
                'user_id' => $qualifyingEmployee['user_id'],
                'user_name' => $qualifyingEmployee['user_name'],
                'award' => "Consistent Contributor - {$qualifyingEmployee['tier']}",
                'amount' => $qualifyingEmployee['target_bonus'] * $bonusMultiplier,
            ];
        }
    }

    /**
     * Processes and calculates all contractor bonuses for the month.
     *
     * @param  \Illuminate\Support\Collection  $contractorLeaderboard
     * @param  MonthlyBudget  $monthlyBudget
     * @param  array  $bonuses
     */
    private function processContractorBonuses($contractorLeaderboard, $monthlyBudget, &$bonuses)
    {
        // Contractor of the Month
        if ($contractorLeaderboard->isNotEmpty()) {
            $user = $contractorLeaderboard->first()->user;
            $bonuses['contractors'][] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'award' => 'Contractor of the Month',
                'amount' => $monthlyBudget->contractor_of_the_month_award_pkr,
            ];
        }

        // Project Performance Bonus (requires manual management based on project completion data)
        // This part of the bonus is a separate process and is not handled by the monthly leaderboard calculation.
    }
}
