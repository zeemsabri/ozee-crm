<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BonusService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function monthly(Request $request, BonusService $bonusService)
    {
        // Determine target year and month (allow overrides via query params for testing)
        $year = (int) $request->query('year', Carbon::now()->year);
        $month = (int) $request->query('month', Carbon::now()->month);

        $users = $bonusService->getMonthlyLeaderboard($year, $month);

        // Map to the structure expected by the frontend leaderboard display
        // Now includes all users, providing a default of 0 points if monthlyPoints is empty
        $data = $users->map(function ($user) {
            $monthlyPoint = $user->monthlyPoints->first(); // The query sorts and limits to one
            return [
                'id' => (string) $user->id,
                'name' => $user->name,
                'finalPoints' => (int) ($monthlyPoint->total_points ?? 0),
                'userType' => $user->user_type === 'contractor' ? 'Contractor' : 'Employee',
            ];
        })->sortByDesc('finalPoints')->values();

        return response()->json([
            'year' => $year,
            'month' => $month,
            'leaderboard' => $data,
        ]);
    }
}
