<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyPoint;
use App\Models\PointsLedger;
use App\Models\ProjectNote;
use App\Models\Project;
use App\Models\Task;
use App\Services\BonusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function stats(Request $request)
    {
        $user = Auth::user();
        $now = Carbon::now();
        $year = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();

        // Days left in the month (including today or excluding?) We'll exclude today to show remaining full days.
        $today = Carbon::today();
        $endOfMonth = $today->copy()->endOfMonth();

        // Calculate the difference in days. Carbon's diffInDays method is used
        // to get the number of days between the two dates.
        $daysLeftInMonth = (int) $today->diffInDays($endOfMonth);
//        $daysLeftInMonth = Carbon::now()->endOfMonth()->diffInDays(Carbon::today());

        // User's monthly points from monthly_points
        $monthlyPointsRow = MonthlyPoint::where('user_id', $user->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
        $userMonthlyPoints = (int) ($monthlyPointsRow->total_points ?? 0);

        // Sum of points from points_ledgers in current month
        $ledgerMonthlyPoints = (int) PointsLedger::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('points_awarded');

        // Accessible projects via project_user
        $accessibleProjectIds = $user->projects()->pluck('projects.id');

        // Pending tasks across accessible projects: tasks linked via milestones
        $pendingTaskCount = Task::whereIn('status', [
                Task::STATUS_TO_DO,
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_PAUSED,
                Task::STATUS_BLOCKED,
            ])
            ->whereHas('milestone.project', function ($q) use ($accessibleProjectIds) {
                $q->whereIn('projects.id', $accessibleProjectIds);
            })
            ->count();

        // Standups submitted by the user this month
        $standupsThisMonth = ProjectNote::where('type', ProjectNote::STANDUP)
            ->where('creator_type', \App\Models\User::class)
            ->where('creator_id', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        // Tasks completed this month by user (assigned_to_user_id) and status Done and completed within month
        $tasksCompletedThisMonth = Task::where('assigned_to_user_id', $user->id)
            ->where('status', Task::STATUS_DONE)
            ->whereBetween('actual_completion_date', [$startOfMonth, $endOfMonth])
            ->count();

        // Points needed to be on top: difference between top monthly_points and user's
        $topMonthlyPoints = (int) MonthlyPoint::where('year', $year)
            ->where('month', $month)
            ->orderByDesc('total_points')
            ->value('total_points') ?? 0;
        $pointsNeededForTop = max(0, $topMonthlyPoints - $userMonthlyPoints);

        return response()->json([
            'year' => $year,
            'month' => $month,
            'daysLeftInMonth' => $daysLeftInMonth,
            'userMonthlyPoints' => $userMonthlyPoints,
            'ledgerMonthlyPoints' => $ledgerMonthlyPoints,
            'pendingTasksAcrossAccessibleProjects' => $pendingTaskCount,
            'standupsThisMonth' => $standupsThisMonth,
            'tasksCompletedThisMonthByUser' => $tasksCompletedThisMonth,
            'pointsNeededForTop' => $pointsNeededForTop,
        ]);
    }
}
