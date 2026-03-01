<?php

namespace App\Services;

use App\Models\User;
use App\Models\Task;
use App\Models\UserActivity;
use App\Models\UserProductivity;
use App\Models\UserAvailability;
use Spatie\Activitylog\Models\Activity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductivityReportService
{
    /**
     * This function prepares the "Snapshot" for a specific user and date.
     * It combines Heartbeats (Activities), Spatie Logs (Events), and Availabilities.
     */
    public function generateDailySnapshot(User $user, $date)
    {
        $date = Carbon::parse($date)->format('Y-m-d');

        // 1. Fetch Raw Data with Eager Loading
        $activities = UserActivity::with('task')
            ->where('user_id', $user->id)
            ->whereDate('recorded_at', $date)
            ->orderBy('recorded_at', 'asc')
            ->get();

        $systemLogs = Activity::where('causer_id', $user->id)
            ->where('causer_type', $user->getMorphClass())
            ->where('subject_type', (new \App\Models\Task)->getMorphClass())
            ->whereDate('created_at', $date)
            ->orderBy('created_at', 'asc')
            ->get();

        $availability = UserAvailability::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->first();

        // 2. Prepare Task Deep-Dive (Grouping activities by task_id)
        $tasksData = $this->prepareTaskData($activities, $systemLogs);

        // 3. Prepare Timeline Barcode (Generating 144 blocks of 10-mins)
        $timeline = $this->prepareTimelineBarcode($activities, $availability);

        // 4. Calculate Aggregate Stats
        $stats = [
            'promised_minutes' => $this->calculatePromisedMinutes($availability),
            'actual_online_minutes' => round($activities->sum('duration') / 60, 2),
            'active_minutes' => round($activities->where('idle_state', 'active')->sum('duration') / 60, 2),
            'idle_minutes' => round($activities->where('idle_state', 'idle')->sum('duration') / 60, 2),
            'first_seen' => $activities->first()?->recorded_at?->toTimeString(),
            'last_seen' => $activities->last()?->recorded_at?->toTimeString(),
            'context_switches' => $this->calculateContextSwitches($activities),
        ];

        // 5. Accuracy Metrics (Unidentified Domains)
        $unidentified = $activities->whereNull('category')
            ->groupBy('domain')
            ->map(fn($group) => ['domain' => $group->first()->domain, 'duration' => $group->sum('duration')])
            ->values();

        // 6. Save or Update the Snapshot
        return UserProductivity::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            [
                'stats_json' => $stats,
                'tasks_json' => $tasksData,
                'timeline_json' => $timeline,
                'accuracy_json' => [
                    'score' => $this->calculateAccuracy($activities),
                    'pending_domains' => $unidentified
                ],
                'status' => 'pending' // Ready for AI processing in Step 2
            ]
        );
    }

    private function prepareTaskData($activities, $logs)
    {
        $grouped = [];
        $tasks = $activities->groupBy('task_id');

        foreach ($tasks as $taskId => $taskActivities) {
            if (!$taskId) continue;

            $task = $taskActivities->first()->task;

            $grouped[] = [
                'task_id' => $taskId,
                'name' => $task?->name ?? 'Unlinked Task',
                'active_mins' => round($taskActivities->where('idle_state', 'active')->sum('duration') / 60, 2),
                'idle_mins' => round($taskActivities->where('idle_state', 'idle')->sum('duration') / 60, 2),
                'top_domains' => $taskActivities->groupBy('domain')
                    ->map(fn($g) => $g->sum('duration'))
                    ->sortDesc()
                    ->take(5)
                    ->keys()
                    ->toArray(),
                'system_events' => $logs->where('subject_id', $taskId)->map(fn($l) => [
                    'event' => $l->description,
                    'time' => $l->created_at->toTimeString()
                ])->values()
            ];
        }
        return $grouped;
    }

    /**
     * Maps activity timestamps into an array of 144 integers (10-min resolution).
     * 0: Offline, 1: Active, 2: Idle/Neutral
     */
    private function prepareTimelineBarcode($activities, $availability)
    {
        $barcode = array_fill(0, 144, 0);

        foreach ($activities as $activity) {
            $time = Carbon::parse($activity->recorded_at);
            // Calculate index (0-143) based on 10-minute intervals
            $index = (int)(($time->hour * 60 + $time->minute) / 10);

            if ($index >= 0 && $index < 144) {
                // Prioritize 'active' state if multiple activities fall in same block
                if ($activity->idle_state === 'active') {
                    $barcode[$index] = 1;
                } elseif ($barcode[$index] === 0) {
                    $barcode[$index] = 2;
                }
            }
        }

        return $barcode;
    }

    /**
     * Sums up the duration of all promised time slots for the day.
     */
    private function calculatePromisedMinutes($availability)
    {
        if (!$availability || !$availability->is_available || empty($availability->time_slots)) {
            return 0;
        }

        $totalMinutes = 0;
        // time_slots is already cast to array in UserAvailability model
        $slots = $availability->time_slots;

        foreach ($slots as $slot) {
            if (isset($slot['start']) && isset($slot['end'])) {
                $start = Carbon::parse($slot['start']);
                $end = Carbon::parse($slot['end']);
                $totalMinutes += $start->diffInMinutes($end);
            }
        }

        return $totalMinutes;
    }

    private function calculateContextSwitches($activities)
    {
        // Count how many times the domain changes from row to row
        $switches = 0;
        $lastDomain = null;
        foreach($activities as $a) {
            if($lastDomain && $a->domain !== $lastDomain) $switches++;
            $lastDomain = $a->domain;
        }
        return $switches;
    }

    private function calculateAccuracy($activities)
    {
        if ($activities->isEmpty()) return 100;
        $total = $activities->count();
        $categorized = $activities->whereNotNull('category')->count();
        return round(($categorized / $total) * 100, 2);
    }
}
