<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserAvailability;
use App\Models\UserProductivity;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class CtoReportController extends Controller
{
    /**
     * Render the report index page.
     */
    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name'])->map(fn($u) => [
            'value' => $u->id,
            'label' => $u->name,
        ]);

        return Inertia::render('Admin/Reports/CtoReport', [
            'users' => $users
        ]);
    }

    /**
     * Fetch aggregated report data.
     */
    public function fetch(Request $request)
    {
        $startDateStr = $request->input('date_start', Carbon::now()->subDays(13)->toDateString());
        $endDateStr = $request->input('date_end', Carbon::now()->toDateString());

        $startDate = Carbon::parse($startDateStr)->startOfDay();
        $endDate = Carbon::parse($endDateStr)->endOfDay();

        $usersQuery = User::query();
        if ($request->has('user_id') && !empty($request->user_id)) {
            $usersQuery->where('id', $request->user_id);
        }
        $users = $usersQuery->orderBy('name')->get(['id', 'name', 'timezone']);

        // Fetch availabilities
        $availabilities = UserAvailability::whereBetween('date', [$startDateStr, $endDateStr])
            ->get()
            ->groupBy('user_id');

        // Fetch productivity snapshots
        $productivities = UserProductivity::whereBetween('date', [$startDateStr, $endDateStr])
            ->get()
            ->groupBy('user_id');

        // Fallback for direct hours check: aggregate raw telemetry duration
        $rawActivities = UserActivity::whereBetween('recorded_at', [$startDate, $endDate])
            ->select('user_id', DB::raw('DATE(recorded_at) as date_only'), DB::raw('SUM(duration) as total_duration_seconds'))
            ->groupBy('user_id', 'date_only')
            ->get()
            ->groupBy('user_id');

        $period = CarbonPeriod::create($startDateStr, $endDateStr);
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->toDateString();
        }

        $reportData = $users->map(function ($user) use ($dates, $availabilities, $productivities, $rawActivities) {
            $userAvails = $availabilities->get($user->id, collect())->keyBy(fn($a) => Carbon::parse($a->date)->toDateString());
            $userProds = $productivities->get($user->id, collect())->keyBy(fn($p) => Carbon::parse($p->date)->toDateString());
            $userRawActs = $rawActivities->get($user->id, collect())->keyBy('date_only');

            $dailyDetails = [];
            foreach ($dates as $date) {
                $avail = $userAvails->get($date);
                $prod = $userProds->get($date);
                $rawAct = $userRawActs->get($date);

                // Determine actual hours worked
                $actualMinutes = 0;
                $activeMinutes = 0;
                if ($prod) {
                    $actualMinutes = $prod->stats_json['actual_online_minutes'] ?? 0;
                    $activeMinutes = $prod->stats_json['active_minutes'] ?? 0;
                } elseif ($rawAct) {
                    $actualMinutes = round($rawAct->total_duration_seconds / 60, 2);
                    $activeMinutes = $actualMinutes; // Estimate active same as online minutes
                }

                $dailyDetails[$date] = [
                    'date' => $date,
                    'has_availability' => !empty($avail),
                    'is_available' => $avail ? (bool)$avail->is_available : null,
                    'did_not_show_up' => $avail ? (bool)$avail->did_not_show_up : false,
                    'was_late' => $avail ? (bool)$avail->was_late : false,
                    'left_early' => $avail ? (bool)$avail->left_early : false,
                    'reason' => $avail ? $avail->reason : null,
                    'time_slots' => $avail ? $avail->time_slots : [],
                    
                    'has_productivity' => !empty($prod),
                    'productivity_status' => $prod ? $prod->status : null,
                    'has_ai_report' => $prod ? (!empty($prod->ai_report_json) && isset($prod->ai_report_json['headline'])) : false,
                    'ai_report' => $prod ? $prod->ai_report_json : null,
                    'tasks' => $prod ? $prod->tasks_json : [],
                    'feedback' => $prod ? $prod->feedback_json : null,
                    
                    'actual_online_hours' => round($actualMinutes / 60, 2),
                    'active_hours' => round($activeMinutes / 60, 2),
                    'promised_hours' => $prod ? round(($prod->stats_json['promised_minutes'] ?? 0) / 60, 2) : 0,
                ];
            }

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'avatar_url' => $user->avatar,
                'timezone' => $user->timezone,
                'daily_details' => $dailyDetails,
            ];
        });

        // Dropdown options for filtering users
        $allUsers = User::orderBy('name')->get(['id', 'name'])->map(fn($u) => [
            'value' => $u->id,
            'label' => $u->name,
        ]);

        return response()->json([
            'users' => $allUsers,
            'dates' => $dates,
            'reportData' => $reportData,
            'filters' => [
                'date_start' => $startDateStr,
                'date_end' => $endDateStr,
                'user_id' => $request->input('user_id'),
            ],
        ]);
    }
}
