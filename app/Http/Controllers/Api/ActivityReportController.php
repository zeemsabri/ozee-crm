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
        $viewerTimezone = $request->user()->timezone ?? config('app.timezone', 'UTC');

        $query = UserActivity::with('user:id,name,timezone');

        if (!empty($userIds)) {
            $query->whereIn('user_id', $userIds);
        }

        // We interpret the date filters in the viewer's timezone
        if ($dateStart) {
            $start = Carbon::parse($dateStart, $viewerTimezone)->startOfDay()->setTimezone('UTC');
            $query->where('recorded_at', '>=', $start);
        }

        if ($dateEnd) {
            $end = Carbon::parse($dateEnd, $viewerTimezone)->endOfDay()->setTimezone('UTC');
            $query->where('recorded_at', '<=', $end);
        }

        $activities = $query->latest('recorded_at')->get();

        // Transform activities to include local time based on EACH user's timezone
        $activities->transform(function ($activity) {
            $userTz = $activity->user->timezone ?? config('app.timezone', 'UTC');
            $activity->local_time = Carbon::parse($activity->recorded_at)->setTimezone($userTz)->toDateTimeString();
            $activity->local_time_formatted = Carbon::parse($activity->recorded_at)->setTimezone($userTz)->format('g:i A');
            return $activity;
        });

        // Aggregate stats
        $totalSeconds = $activities->sum('duration');
        $totalEvents = $activities->count();

        // Top Domain
        $topDomainData = UserActivity::select('domain', DB::raw('SUM(duration) as total_duration'))
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart, $viewerTimezone)->startOfDay()->setTimezone('UTC')))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd, $viewerTimezone)->endOfDay()->setTimezone('UTC')))
            ->groupBy('domain')
            ->orderByDesc('total_duration')
            ->first();

        // Chart Data: Domain Distribution (Top 10)
        $domainDist = UserActivity::select(
                'domain', 
                DB::raw('SUM(duration) as value'),
                DB::raw("SUM(CASE WHEN idle_state = 'active' THEN duration ELSE 0 END) as active_duration"),
                DB::raw("SUM(CASE WHEN idle_state != 'active' THEN duration ELSE 0 END) as idle_duration")
            )
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart, $viewerTimezone)->startOfDay()->setTimezone('UTC')))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd, $viewerTimezone)->endOfDay()->setTimezone('UTC')))
            ->groupBy('domain')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        // Chart Data: Page Title Distribution (Top 10)
        $titleDist = UserActivity::select(
                'title', 
                DB::raw('SUM(duration) as value'),
                DB::raw("SUM(CASE WHEN idle_state = 'active' THEN duration ELSE 0 END) as active_duration"),
                DB::raw("SUM(CASE WHEN idle_state != 'active' THEN duration ELSE 0 END) as idle_duration")
            )
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart, $viewerTimezone)->startOfDay()->setTimezone('UTC')))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd, $viewerTimezone)->endOfDay()->setTimezone('UTC')))
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->groupBy('title')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        // Chart Data: Hourly Trend (Adjusted to Viewer's Timezone)
        // Since CONVERT_TZ might not be reliable without TZ tables, we'll group in PHP for safety
        // or calculate the offset. Let's group in PHP since we already have the collection for this period.
        $hourlyData = $this->getHourlyTrend($activities, $viewerTimezone);

        // Users for filter
        $users = User::select('id as value', 'name as label')->orderBy('name')->get();

        // Productivity Metrics
        $productivity = $this->calculateProductivityMetrics($activities);
        
        // Category Breakdown
        $categoryBreakdown = $this->getCategoryBreakdown($userIds, $dateStart, $dateEnd, $viewerTimezone);
        
        // Idle vs Active breakdown
        $idleBreakdown = $this->getIdleBreakdown($activities, $viewerTimezone);

        return response()->json([
            'activities' => $activities,
            'stats' => [
                'total_hours' => round($totalSeconds / 3600, 1),
                'total_minutes' => round($totalSeconds / 60),
                'total_events' => $totalEvents,
                'top_domain' => $topDomainData ? $topDomainData->domain : '-',
                'top_domain_time' => $topDomainData ? round($topDomainData->total_duration / 60) : 0,
                'viewer_timezone' => $viewerTimezone,
            ],
            'productivity' => $productivity,
            'charts' => [
                'domain_dist' => $domainDist->map(fn($d) => [
                    'label' => $d->domain,
                    'value' => round($d->value / 60), // in minutes
                    'active' => round($d->active_duration / 60),
                    'idle' => round($d->idle_duration / 60),
                ]),
                'title_dist' => $titleDist->map(fn($d) => [
                    'label' => $d->title,
                    'value' => round($d->value / 60), // in minutes
                    'active' => round($d->active_duration / 60),
                    'idle' => round($d->idle_duration / 60),
                ]),
                'hourly_trend' => $hourlyData,
                'category_breakdown' => $categoryBreakdown,
                'idle_breakdown' => $idleBreakdown,
            ],
            'users' => $users
        ]);
    }

    private function getHourlyTrend($activities, $timezone)
    {
        $trend = array_fill(0, 24, 0);
        
        foreach ($activities as $activity) {
            $hour = (int) Carbon::parse($activity->recorded_at)->setTimezone($timezone)->format('H');
            $trend[$hour] += $activity->duration;
        }

        return collect($trend)->map(function($value, $hour) {
            return [
                'label' => $this->formatHour($hour),
                'value' => round($value / 60, 1)
            ];
        })->values();
    }

    private function getIdleBreakdown($activities, $timezone)
    {
        $data = array_fill(0, 24, ['active' => 0, 'idle' => 0]);
        
        foreach ($activities as $activity) {
            $hour = (int) Carbon::parse($activity->recorded_at)->setTimezone($timezone)->format('H');
            if ($activity->idle_state === 'active') {
                $data[$hour]['active'] += $activity->duration;
            } else {
                $data[$hour]['idle'] += $activity->duration;
            }
        }

        return collect($data)->map(function($values, $hour) {
            return [
                'hour' => $this->formatHour($hour),
                'active' => round($values['active'] / 60, 1),
                'idle' => round($values['idle'] / 60, 1),
            ];
        })->values();
    }

    /**
     * Get category breakdown with percentages.
     */
    private function getCategoryBreakdown($userIds, $dateStart, $dateEnd, $viewerTimezone)
    {
        $query = UserActivity::select(
                'category', 
                DB::raw('SUM(duration) as total_duration'),
                DB::raw("SUM(CASE WHEN idle_state = 'active' THEN duration ELSE 0 END) as active_duration"),
                DB::raw("SUM(CASE WHEN idle_state != 'active' THEN duration ELSE 0 END) as idle_duration")
            )
            ->when(!empty($userIds), fn($q) => $q->whereIn('user_id', $userIds))
            ->when($dateStart, fn($q) => $q->where('recorded_at', '>=', Carbon::parse($dateStart, $viewerTimezone)->startOfDay()->setTimezone('UTC')))
            ->when($dateEnd, fn($q) => $q->where('recorded_at', '<=', Carbon::parse($dateEnd, $viewerTimezone)->endOfDay()->setTimezone('UTC')))
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderByDesc('total_duration')
            ->get();

        $totalActive = $query->sum('active_duration');
        $categories = config('activity_categories.categories', []);

        return $query->map(function($item) use ($totalActive, $categories) {
            $categoryConfig = $categories[$item->category] ?? [];
            return [
                'category' => $item->category,
                'label' => $categoryConfig['label'] ?? ucfirst($item->category),
                'color' => $categoryConfig['color'] ?? '#6b7280',
                'duration' => round($item->total_duration / 60), // minutes
                'active_duration' => round($item->active_duration / 60),
                'idle_duration' => round($item->idle_duration / 60),
                'percentage' => $totalActive > 0 ? round(($item->active_duration / $totalActive) * 100, 1) : 0,
            ];
        });
    }

    /**
     * Calculate productivity metrics based on activity data.
     */
    private function calculateProductivityMetrics($activities)
    {
        $weights = config('activity_categories.productivity_weights', []);
        
        $totalActiveTime = $activities->where('idle_state', 'active')->sum('duration');
        $totalIdleTime = $activities->where('idle_state', '!=', 'active')->sum('duration');
        
        $activeActivities = $activities->where('idle_state', 'active');
        $categoryTimes = $activeActivities->groupBy('category')->map(fn($group) => $group->sum('duration'));
        
        $weightedScore = 0;
        $totalWeightedTime = 0;
        
        foreach ($categoryTimes as $category => $duration) {
            $weight = $weights[$category] ?? 0.5;
            $weightedScore += $duration * $weight;
            $totalWeightedTime += $duration;
        }
        
        $productivityScore = $totalWeightedTime > 0 ? round(($weightedScore / $totalWeightedTime) * 100) : 0;
        
        // Use full activities for specific category sums if needed for labels, 
        // but for unproductive/productive calculation we stick to active if that's the goal.
        $socialMediaTime = $activeActivities->where('category', 'social_media')->sum('duration');
        $productiveTime = $activeActivities->whereIn('category', ['productive', 'development'])->sum('duration');
        $unproductiveTime = $activeActivities->where('category', 'unproductive')->sum('duration') + $socialMediaTime;
        
        return [
            'score' => $productivityScore,
            'productive_time' => round($productiveTime / 60), // minutes
            'unproductive_time' => round($unproductiveTime / 60),
            'idle_time' => round($totalIdleTime / 60),
            'social_media_time' => round($socialMediaTime / 60),
            'active_time' => round($totalActiveTime / 60),
        ];
    }

    private function formatHour($hour)
    {
        if ($hour == 0) return '12am';
        if ($hour < 12) return $hour . 'am';
        if ($hour == 12) return '12pm';
        return ($hour - 12) . 'pm';
    }
}
