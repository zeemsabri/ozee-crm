<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivityReportController extends Controller
{
    /**
     * Get activity data for the reporting dashboard.
     */
    public function index(Request $request)
    {
        $userIds = $request->input('user_ids') ? explode(',', $request->input('user_ids')) : [];
        $dateStart = $request->input('date_start');
        $dateEnd = $request->input('date_end');

        $query = UserActivity::with('user:id,name');

        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }

        if ($dateStart) {
            $query->where('recorded_at', '>=', Carbon::parse($dateStart)->startOfDay());
        }

        if ($dateEnd) {
            $query->where('recorded_at', '<=', Carbon::parse($dateEnd)->endOfDay());
        }

        $activities = $query->latest('recorded_at')->get();

        // Aggregate stats
        $totalSeconds = $activities->sum('duration');
        $totalEvents = $activities->count();

        // Top Domain
        $topDomainData = UserActivity::select('domain', DB::raw('SUM(duration) as total_duration'))
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart)->startOfDay()))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd)->endOfDay()))
            ->groupBy('domain')
            ->orderByDesc('total_duration')
            ->first();

        // Chart Data: Domain Distribution (Top 10)
        $domainDist = UserActivity::select('domain', DB::raw('SUM(duration) as value'))
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart)->startOfDay()))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd)->endOfDay()))
            ->groupBy('domain')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        // Chart Data: Hourly Trend
        // Note: For sqlite or mysql, grouping by hour might differ.
        // Assuming MySQL for production-like behavior, but let's use a more generic approach if possible.
        $hourlyData = UserActivity::select(
                DB::raw('HOUR(recorded_at) as hour'),
                DB::raw('SUM(duration) as value')
            )
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart)->startOfDay()))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd)->endOfDay()))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Fill in missing hours
        $trend = collect(range(0, 23))->map(function($hour) use ($hourlyData) {
            $data = $hourlyData->firstWhere('hour', $hour);
            return [
                'label' => $this->formatHour($hour),
                'value' => $data ? round($data->value / 60, 1) : 0 // in minutes
            ];
        });

        // Users for filter
        $users = User::select('id as value', 'name as label')->orderBy('name')->get();

        // Productivity Metrics
        $productivity = $this->calculateProductivityMetrics($activities);
        
        // Category Breakdown
        $categoryBreakdown = $this->getCategoryBreakdown($userIds, $dateStart, $dateEnd);
        
        // Idle vs Active breakdown
        $idleBreakdown = $this->getIdleBreakdown($userIds, $dateStart, $dateEnd);

        return response()->json([
            'activities' => $activities,
            'stats' => [
                'total_hours' => round($totalSeconds / 3600, 1),
                'total_minutes' => round($totalSeconds / 60),
                'total_events' => $totalEvents,
                'top_domain' => $topDomainData ? $topDomainData->domain : '-',
                'top_domain_time' => $topDomainData ? round($topDomainData->total_duration / 60) : 0,
            ],
            'productivity' => $productivity,
            'charts' => [
                'domain_dist' => $domainDist->map(fn($d) => [
                    'label' => $d->domain,
                    'value' => round($d->value / 60) // in minutes
                ]),
                'hourly_trend' => $trend,
                'category_breakdown' => $categoryBreakdown,
                'idle_breakdown' => $idleBreakdown,
            ],
            'users' => $users
        ]);
    }

    /**
     * Calculate productivity metrics based on activity data.
     */
    private function calculateProductivityMetrics($activities)
    {
        $weights = config('activity_categories.productivity_weights', []);
        
        $totalActiveTime = $activities->where('idle_state', 'active')->sum('duration');
        $totalIdleTime = $activities->where('idle_state', '!=', 'active')->sum('duration');
        
        $categoryTimes = $activities->groupBy('category')->map(fn($group) => $group->sum('duration'));
        
        $weightedScore = 0;
        $totalWeightedTime = 0;
        
        foreach ($categoryTimes as $category => $duration) {
            $weight = $weights[$category] ?? 0.5;
            $weightedScore += $duration * $weight;
            $totalWeightedTime += $duration;
        }
        
        $productivityScore = $totalWeightedTime > 0 ? round(($weightedScore / $totalWeightedTime) * 100) : 0;
        
        $socialMediaTime = $categoryTimes['social_media'] ?? 0;
        $productiveTime = ($categoryTimes['productive'] ?? 0) + ($categoryTimes['development'] ?? 0);
        $unproductiveTime = ($categoryTimes['unproductive'] ?? 0) + $socialMediaTime;
        
        return [
            'score' => $productivityScore,
            'productive_time' => round($productiveTime / 60), // minutes
            'unproductive_time' => round($unproductiveTime / 60),
            'idle_time' => round($totalIdleTime / 60),
            'social_media_time' => round($socialMediaTime / 60),
            'active_time' => round($totalActiveTime / 60),
        ];
    }

    /**
     * Get category breakdown with percentages.
     */
    private function getCategoryBreakdown($userIds, $dateStart, $dateEnd)
    {
        $query = UserActivity::select('category', DB::raw('SUM(duration) as total_duration'))
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart)->startOfDay()))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd)->endOfDay()))
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('total_duration')
            ->get();

        $total = $query->sum('total_duration');
        $categories = config('activity_categories.categories', []);

        return $query->map(function($item) use ($total, $categories) {
            $categoryConfig = $categories[$item->category] ?? [];
            return [
                'category' => $item->category,
                'label' => $categoryConfig['label'] ?? ucfirst($item->category),
                'color' => $categoryConfig['color'] ?? '#6b7280',
                'duration' => round($item->total_duration / 60), // minutes
                'percentage' => $total > 0 ? round(($item->total_duration / $total) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Get idle vs active breakdown by hour.
     */
    private function getIdleBreakdown($userIds, $dateStart, $dateEnd)
    {
        $activeData = UserActivity::select(
                DB::raw('HOUR(recorded_at) as hour'),
                DB::raw('SUM(duration) as value')
            )
            ->where('idle_state', 'active')
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart)->startOfDay()))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd)->endOfDay()))
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $idleData = UserActivity::select(
                DB::raw('HOUR(recorded_at) as hour'),
                DB::raw('SUM(duration) as value')
            )
            ->where('idle_state', '!=', 'active')
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart)->startOfDay()))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd)->endOfDay()))
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        return collect(range(0, 23))->map(function($hour) use ($activeData, $idleData) {
            return [
                'hour' => $this->formatHour($hour),
                'active' => isset($activeData[$hour]) ? round($activeData[$hour]->value / 60, 1) : 0,
                'idle' => isset($idleData[$hour]) ? round($idleData[$hour]->value / 60, 1) : 0,
            ];
        });
    }

    private function formatHour($hour)
    {
        if ($hour == 0) return '12am';
        if ($hour < 12) return $hour . 'am';
        if ($hour == 12) return '12pm';
        return ($hour - 12) . 'pm';
    }
}
