<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use App\Models\UserAvailability;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class UserAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date_start'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $timezone = $user->timezone ?? config('app.timezone', 'UTC');
        $today = now()->setTimezone($timezone);

        $selectedStart = Carbon::parse(
            $request->input('date_start', $today->copy()->startOfWeek()->toDateString()),
            $timezone,
        )->startOfDay();
        $selectedEnd = Carbon::parse(
            $request->input('date_end', $today->toDateString()),
            $timezone,
        )->endOfDay();

        $selectedDays = $this->buildDays($user->id, $timezone, $selectedStart, $selectedEnd);
        $weekDays = $this->buildDays(
            $user->id,
            $timezone,
            $today->copy()->startOfWeek()->startOfDay(),
            $today->copy()->endOfWeek()->endOfDay(),
        );

        return response()->json([
            'meta' => [
                'timezone' => $timezone,
                'date_start' => $selectedStart->toDateString(),
                'date_end' => $selectedEnd->toDateString(),
                'today' => $today->toDateString(),
            ],
            'summary' => $this->summarizeDays($selectedDays),
            'current_week' => $this->summarizeDays($weekDays),
            'days' => $selectedDays->values(),
            'chart' => $selectedDays->map(fn (array $day) => [
                'date' => $day['date'],
                'label' => $day['label'],
                'online_minutes' => $day['online_minutes'],
                'active_minutes' => $day['active_minutes'],
                'idle_minutes' => $day['idle_minutes'],
                'expected_minutes' => $day['expected_minutes'],
            ])->values(),
        ]);
    }

    private function buildDays(int $userId, string $timezone, Carbon $start, Carbon $end): Collection
    {
        $activities = UserActivity::query()
            ->where('user_id', $userId)
            ->where('recorded_at', '>=', $start->copy()->utc())
            ->where('recorded_at', '<=', $end->copy()->utc())
            ->orderBy('recorded_at')
            ->get();

        $activitiesByDate = $activities->groupBy(function (UserActivity $activity) use ($timezone) {
            return $activity->recorded_at->copy()->setTimezone($timezone)->toDateString();
        });

        $availabilities = UserAvailability::query()
            ->where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (UserAvailability $availability) => $availability->date->toDateString());

        return collect(CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()))
            ->map(function (Carbon $date) use ($activitiesByDate, $availabilities, $timezone) {
                $dateKey = $date->toDateString();
                $dayActivities = $activitiesByDate->get($dateKey, collect());
                $availability = $availabilities->get($dateKey);
                $unlinkedActivities = $dayActivities->filter(fn (UserActivity $activity) => empty($activity->task_id))->values();

                $onlineSeconds = (int) $dayActivities->sum('duration');
                $activeSeconds = (int) $dayActivities->where('idle_state', 'active')->sum('duration');
                $idleSeconds = max($onlineSeconds - $activeSeconds, 0);
                $unlinkedSeconds = (int) $unlinkedActivities->sum('duration');
                $expectedMinutes = $this->calculateExpectedMinutes($availability);
                $completedMinutes = round($onlineSeconds / 60, 1);
                $unlinkedMinutes = round($unlinkedSeconds / 60, 1);

                $firstSeen = $dayActivities->first()?->recorded_at?->copy()->setTimezone($timezone)->format('g:i A');
                $lastSeen = $dayActivities->last()?->recorded_at?->copy()->setTimezone($timezone)->format('g:i A');

                return [
                    'date' => $dateKey,
                    'label' => $date->copy()->setTimezone($timezone)->format('D, d M'),
                    'is_weekend' => $date->isWeekend(),
                    'expected_minutes' => $expectedMinutes,
                    'online_minutes' => $completedMinutes,
                    'active_minutes' => round($activeSeconds / 60, 1),
                    'idle_minutes' => round($idleSeconds / 60, 1),
                    'unlinked_minutes' => $unlinkedMinutes,
                    'unlinked_percentage' => $completedMinutes > 0 ? round(($unlinkedMinutes / $completedMinutes) * 100, 1) : 0,
                    'unlinked_windows' => $this->buildUnlinkedWindows($unlinkedActivities, $timezone),
                    'first_seen' => $firstSeen,
                    'last_seen' => $lastSeen,
                    'status' => $this->determineDayStatus($completedMinutes, $expectedMinutes),
                    'shortfall_minutes' => max($expectedMinutes - $completedMinutes, 0),
                ];
            });
    }

    private function buildUnlinkedWindows(Collection $activities, string $timezone): array
    {
        if ($activities->isEmpty()) {
            return [];
        }

        $windows = [];

        foreach ($activities as $activity) {
            $start = $activity->recorded_at->copy()->setTimezone($timezone);
            $end = $start->copy()->addSeconds((int) $activity->duration);

            if (empty($windows)) {
                $windows[] = ['start' => $start, 'end' => $end];
                continue;
            }

            $lastIndex = count($windows) - 1;
            $previous = $windows[$lastIndex];

            if ($start->lessThanOrEqualTo($previous['end']->copy()->addMinutes(5))) {
                if ($end->greaterThan($previous['end'])) {
                    $windows[$lastIndex]['end'] = $end;
                }

                continue;
            }

            $windows[] = ['start' => $start, 'end' => $end];
        }

        return collect($windows)
            ->map(function (array $window) {
                return [
                    'label' => sprintf('%s - %s', $window['start']->format('g:i A'), $window['end']->format('g:i A')),
                    'start' => $window['start']->format('g:i A'),
                    'end' => $window['end']->format('g:i A'),
                ];
            })
            ->values()
            ->all();
    }

    private function calculateExpectedMinutes(?UserAvailability $availability): int
    {
        if (! $availability || ! $availability->is_available || empty($availability->time_slots)) {
            return 0;
        }

        return (int) collect($availability->time_slots)->sum(function (array $slot) {
            if (empty($slot['start_time']) || empty($slot['end_time'])) {
                return 0;
            }

            $start = Carbon::parse($slot['start_time']);
            $end = Carbon::parse($slot['end_time']);

            return $start->diffInMinutes($end);
        });
    }

    private function summarizeDays(Collection $days): array
    {
        $onlineMinutes = round($days->sum('online_minutes'), 1);
        $activeMinutes = round($days->sum('active_minutes'), 1);
        $idleMinutes = round($days->sum('idle_minutes'), 1);
        $unlinkedMinutes = round($days->sum('unlinked_minutes'), 1);
        $expectedMinutes = (int) round($days->sum('expected_minutes'));

        return [
            'online_minutes' => $onlineMinutes,
            'active_minutes' => $activeMinutes,
            'idle_minutes' => $idleMinutes,
            'unlinked_minutes' => $unlinkedMinutes,
            'unlinked_percentage' => $onlineMinutes > 0 ? round(($unlinkedMinutes / $onlineMinutes) * 100, 1) : 0,
            'unlinked_needs_attention' => $onlineMinutes > 0 ? (($unlinkedMinutes / $onlineMinutes) * 100) > 5 : false,
            'expected_minutes' => $expectedMinutes,
            'worked_days' => $days->filter(fn (array $day) => $day['online_minutes'] > 0)->count(),
            'completion_percentage' => $expectedMinutes > 0 ? round(($onlineMinutes / $expectedMinutes) * 100, 1) : null,
            'shortfall_minutes' => max($expectedMinutes - $onlineMinutes, 0),
            'surplus_minutes' => max($onlineMinutes - $expectedMinutes, 0),
        ];
    }

    private function determineDayStatus(float $completedMinutes, int $expectedMinutes): string
    {
        if ($completedMinutes <= 0 && $expectedMinutes <= 0) {
            return 'no-plan';
        }

        if ($completedMinutes <= 0 && $expectedMinutes > 0) {
            return 'missed';
        }

        if ($expectedMinutes <= 0) {
            return 'extra';
        }

        if ($completedMinutes >= $expectedMinutes) {
            return 'complete';
        }

        return 'short';
    }
}