<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LiveStatusController extends Controller
{
    /**
     * Display the live status of all users.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $viewerTimezone = $request->user()?->timezone ?? config('app.timezone', 'UTC');
        $activityFilters = $this->resolveActivityFilters($request, $viewerTimezone);
        $users = User::with(['activeTask.milestone.project'])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'is_online' => $user->is_online,
                    'online_since' => $user->online_data['last_status_change'] ?? null,
                    'timezone' => $user->timezone ?? 'UTC',
                    'active_task' => $user->activeTask ? [
                        'id' => $user->activeTask->id,
                        'name' => $user->activeTask->name,
                        'project_name' => $user->activeTask->milestone?->project?->name ?? 'N/A',
                    ] : null,
                    'avatar' => $user->avatar,
                ];
            });

        $activityInsights = $this->getActivityInsights(
            $activityFilters['start_utc'],
            $activityFilters['end_utc'],
            $viewerTimezone
        );

        return Inertia::render('Admin/LiveStatus/Index', [
            'users' => $users,
            'activityInsights' => $activityInsights,
            'activityFilters' => [
                'start' => $activityFilters['start_local'],
                'end' => $activityFilters['end_local'],
                'timezone' => $viewerTimezone,
            ],
        ]);
    }

    /**
     * Get the online/offline logs for a specific user.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function logs(Request $request, User $user)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $logs = $user->onlineActivityLogs($startDate, $endDate);

        return response()->json([
            'logs' => $logs
        ]);
    }

    /**
     * Resolve the requested datetime filter window in the viewer's timezone.
     *
     * @return array<string, mixed>
     */
    private function resolveActivityFilters(Request $request, string $viewerTimezone): array
    {
        $endLocal = $request->query('activity_end')
            ? Carbon::parse($request->query('activity_end'), $viewerTimezone)
            : now($viewerTimezone);

        $startLocal = $request->query('activity_start')
            ? Carbon::parse($request->query('activity_start'), $viewerTimezone)
            : $endLocal->copy()->subDay();

        if ($startLocal->gt($endLocal)) {
            [$startLocal, $endLocal] = [$endLocal->copy(), $startLocal->copy()];
        }

        return [
            'start_local' => $startLocal->format('Y-m-d\TH:i'),
            'end_local' => $endLocal->format('Y-m-d\TH:i'),
            'start_utc' => $startLocal->copy()->setTimezone('UTC'),
            'end_utc' => $endLocal->copy()->setTimezone('UTC'),
        ];
    }

    /**
     * Build aggregated activity insights for version adoption and server load diagnostics.
     *
     * @return array<string, mixed>
     */
    private function getActivityInsights(Carbon $startUtc, Carbon $endUtc, string $viewerTimezone): array
    {
        $versionExpression = "JSON_UNQUOTE(JSON_EXTRACT(user_activities.metadata, '$.extension_version'))";
        $eventCountExpression = "COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(user_activities.metadata, '$.event_count')) AS UNSIGNED), 1)";

        $summaries = UserActivity::query()
            ->join('users', 'users.id', '=', 'user_activities.user_id')
            ->whereBetween('user_activities.recorded_at', [$startUtc, $endUtc])
            ->groupBy('user_activities.user_id', 'users.name', 'users.is_online')
            ->selectRaw('user_activities.user_id')
            ->selectRaw('users.name as user_name')
            ->selectRaw('users.is_online as user_is_online')
            ->selectRaw('COUNT(*) as session_count')
            ->selectRaw('SUM(user_activities.duration) as total_duration')
            ->selectRaw("SUM({$eventCountExpression}) as activity_count")
            ->selectRaw('MIN(user_activities.recorded_at) as first_activity_at')
            ->selectRaw('MAX(user_activities.recorded_at) as last_activity_at')
            ->selectSub(
                UserActivity::query()
                    ->selectRaw($versionExpression)
                    ->whereColumn('user_id', 'user_activities.user_id')
                    ->whereBetween('recorded_at', [$startUtc, $endUtc])
                    ->orderByDesc('recorded_at')
                    ->orderByDesc('id')
                    ->limit(1),
                'latest_extension_version'
            )
            ->orderByDesc('activity_count')
            ->get();

        $usersById = User::query()
            ->whereIn('id', $summaries->pluck('user_id')->all())
            ->get()
            ->keyBy('id');

        $latestVersion = config('services.extension.version')
            ?: $this->detectLatestExtensionVersion(
                $summaries->pluck('latest_extension_version')->filter()->unique()->values()->all()
            );

        $windowMinutes = max(1, $endUtc->diffInMinutes($startUtc));
        $windowHours = max(1 / 60, $windowMinutes / 60);
        $totalActivities = (int) $summaries->sum('activity_count');

        $users = $summaries->map(function ($summary) use ($usersById, $viewerTimezone, $windowHours, $totalActivities, $latestVersion) {
            $user = $usersById->get((int) $summary->user_id);
            $latestActivityAt = $summary->last_activity_at
                ? Carbon::parse($summary->last_activity_at)->setTimezone($viewerTimezone)->toIso8601String()
                : null;

            return [
                'user_id' => (int) $summary->user_id,
                'name' => $summary->user_name,
                'avatar' => $user?->avatar,
                'is_online' => (bool) $summary->user_is_online,
                'activity_count' => (int) $summary->activity_count,
                'session_count' => (int) $summary->session_count,
                'total_duration_minutes' => round(((int) $summary->total_duration) / 60, 1),
                'latest_extension_version' => $summary->latest_extension_version,
                'is_outdated' => $this->isOutdatedVersion($summary->latest_extension_version, $latestVersion),
                'activity_rate_per_hour' => round(((int) $summary->activity_count) / $windowHours, 1),
                'activity_share_percent' => $totalActivities > 0
                    ? round((((int) $summary->activity_count) / $totalActivities) * 100, 1)
                    : 0,
                'latest_activity_at' => $latestActivityAt,
            ];
        })->values();

        return [
            'stats' => [
                'total_activities' => $totalActivities,
                'reporting_users' => $users->count(),
                'versions_in_use' => $users->pluck('latest_extension_version')->filter()->unique()->count(),
                'latest_version' => $latestVersion,
                'outdated_users' => $users->where('is_outdated', true)->count(),
                'window_hours' => round($windowHours, 1),
            ],
            'users' => $users,
            'top_surge_users' => $users->take(5)->values(),
        ];
    }

    /**
     * Pick the highest semantic version from the observed versions.
     */
    private function detectLatestExtensionVersion(array $versions): ?string
    {
        $stableVersions = collect($versions)
            ->filter(fn ($version) => is_string($version) && $version !== '')
            ->values();

        if ($stableVersions->isEmpty()) {
            return null;
        }

        return $stableVersions->sort(function ($left, $right) {
            return version_compare($left, $right);
        })->last();
    }

    /**
     * Check whether a user is behind the latest observed extension version.
     */
    private function isOutdatedVersion(?string $version, ?string $latestVersion): bool
    {
        if (!$latestVersion) {
            return false;
        }

        if (!$version) {
            return true;
        }

        return version_compare($version, $latestVersion, '<');
    }
}
