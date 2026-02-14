<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ProductivityReportController extends Controller
{
    /**
     * Get the productivity report data including filters, charts, and details.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['user_ids', 'date_start', 'date_end']);

        $users = User::orderBy('name')->get()->map(fn($u) => [
            'value' => $u->id,
            'label' => $u->name,
            'avatar' => $u->avatar_url ?? "https://ui-avatars.com/api/?name=" . urlencode($u->name)
        ]);

        $projects = Project::select('id', 'name')->orderBy('name')->get()->map(fn($p) => [
            'value' => $p->id,
            'label' => $p->name
        ]);

        if (empty($filters['date_start'])) {
            $filters['date_start'] = Carbon::now()->startOfMonth()->toDateString();
        }
        if (empty($filters['date_end'])) {
            $filters['date_end'] = Carbon::now()->toDateString();
        }

        $reportData = $this->generateReport($filters);

        return response()->json([
            'users' => $users,
            'projects' => $projects,
            'reportData' => $reportData,
            'filters' => $filters
        ]);
    }

    private function generateReport($filters)
    {
        $userIds = $filters['user_ids'] ?? [];
        if (is_string($userIds)) {
            $userIds = explode(',', $userIds);
        }

        $startDate = Carbon::parse($filters['date_start'])->startOfDay();
        $endDate = Carbon::parse($filters['date_end'])->endOfDay();

        // 1. Fetch Activities (Time tracking logs)
        $activities = Activity::query()
            ->where('subject_type', Task::class)
            ->whereIn('description', [
                'Task was started',
                'Task was paused',
                'Task was resumed',
                'Task was completed'
            ])
            ->when(!empty($userIds), fn($q) => $q->whereIn('causer_id', $userIds))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['causer', 'subject.milestone.project', 'subject.subtasks'])
            ->orderBy('created_at')
            ->get();

        // 2. Fetch Tasks with Manual Overrides in this period
        $manualTasks = Task::query()
            ->whereNotNull('manual_effort_override')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->when(!empty($userIds), fn($q) => $q->whereIn('assigned_to_user_id', $userIds))
            ->with(['milestone.project', 'subtasks'])
            ->get();

        $allUserIds = collect($activities->pluck('causer_id'))
            ->merge($manualTasks->pluck('assigned_to_user_id'))
            ->merge($userIds)
            ->unique()
            ->filter();

        $usersMap = User::whereIn('id', $allUserIds)->get()->keyBy('id');
        $groupedActivities = $activities->groupBy('causer_id');
        $groupedManualTasks = $manualTasks->groupBy('assigned_to_user_id');

        $report = [];

        foreach ($allUserIds as $userId) {
            $user = $usersMap->get($userId);
            if (!$user) continue;

            $userActivities = $groupedActivities->get($userId, collect());
            $userManualOnly = $groupedManualTasks->get($userId, collect());
            $tasksFromActivities = $userActivities->groupBy('subject_id');
            $tasksFromManual = $userManualOnly->keyBy('id');

            $allTaskIds = $tasksFromActivities->keys()->merge($tasksFromManual->keys())->unique();

            $userTaskReports = [];
            $totalSeconds = 0;

            foreach ($allTaskIds as $taskId) {
                $task = $tasksFromActivities->has($taskId)
                    ? $tasksFromActivities[$taskId]->first()->subject
                    : $tasksFromManual->get($taskId);

                if (!$task) continue;

                $sessions = [];
                $currentSessionStart = null;
                $taskTotalSeconds = 0;

                foreach ($tasksFromActivities->get($taskId, collect()) as $activity) {
                    $desc = $activity->description;
                    $time = $activity->created_at;

                    if ($desc === 'Task was started' || $desc === 'Task was resumed') {
                        $currentSessionStart = $time;
                    } elseif (($desc === 'Task was paused' || $desc === 'Task was completed') && $currentSessionStart) {
                        $duration = $currentSessionStart->diffInSeconds($time);
                        $type = 'normal';
                        $endTime = $time;

                        // Auto-cap outliers (12h+ sessions)
                        if ($duration > 43200) {
                            $endTime = $currentSessionStart->copy()->setTime(17, 0, 0);
                            if ($endTime->lt($currentSessionStart)) $endTime = $currentSessionStart->copy()->addHour();
                            $duration = $currentSessionStart->diffInSeconds($endTime);
                            $type = 'auto_capped_outlier';
                        }

                        $sessions[] = [
                            'start' => $currentSessionStart->toDateTimeString(),
                            'end' => $endTime->toDateTimeString(),
                            'duration_seconds' => $duration,
                            'type' => $type
                        ];
                        $taskTotalSeconds += $duration;
                        $currentSessionStart = null;
                    }
                }

                // Handle ongoing tasks
                if ($currentSessionStart) {
                    $now = Carbon::now();
                    $duration = $currentSessionStart->diffInSeconds($now);
                    $sessions[] = [
                        'start' => $currentSessionStart->toDateTimeString(),
                        'end' => 'Now',
                        'duration_seconds' => $duration,
                        'type' => 'ongoing'
                    ];
                    $taskTotalSeconds += $duration;
                }

                $manualOverrideSeconds = $task->manual_effort_override; // DB stores seconds
                $usedSeconds = $manualOverrideSeconds !== null ? $manualOverrideSeconds : $taskTotalSeconds;

                // Checklist progress
                $checklistItems = $task->subtasks;
                if ($checklistItems->isNotEmpty()) {
                    $total = $checklistItems->count();
                    $done = $checklistItems->where('status', 'done')->count();
                } else {
                    $data = $task->details['checklist'] ?? [];
                    $total = count($data);
                    $done = count(array_filter($data, fn($i) => !empty($i['completed'])));
                }

                $userTaskReports[] = [
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'project_name' => $task->milestone?->project?->name ?? 'Internal',
                    'sessions' => $sessions,
                    'subtasks' => $task->subtasks, // Added subtasks for tooltip
                    'total_seconds' => $taskTotalSeconds,
                    'manual_effort_override' => $manualOverrideSeconds ? ($manualOverrideSeconds / 3600) : null,
                    'used_seconds' => $usedSeconds,
                    'effort' => $task->effort,
                    'priority' => $task->priority,
                    'checklist' => "{$done}/{$total}",
                    'due_date' => $task->due_date?->format('Y-m-d'),
                    'status' => $task->status->value ?? $task->status,
                    'is_late' => $task->due_date && $task->due_date->isPast() && $task->status !== 'Done'
                ];
                $totalSeconds += $usedSeconds;
            }

            $report[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'avatar' => $user->avatar_url ?? "https://ui-avatars.com/api/?name=" . urlencode($user->name),
                'tasks' => $userTaskReports,
                'total_seconds' => $totalSeconds,
                'total_hours' => round($totalSeconds / 3600, 2)
            ];
        }

        return [
            'details' => $report,
            'charts' => $this->aggregateChartData($report)
        ];
    }

    private function aggregateChartData($report)
    {
        $projectStats = [];
        $dailyTrend = [];
        $pointTrend = [];

        foreach ($report as $user) {
            foreach ($user['tasks'] as $task) {
                $pName = $task['project_name'];
                $projectStats[$pName] = ($projectStats[$pName] ?? 0) + $task['used_seconds'];

                foreach ($task['sessions'] as $s) {
                    $day = substr($s['start'], 0, 10);
                    $dailyTrend[$day] = ($dailyTrend[$day] ?? 0) + $s['duration_seconds'];
                }

                if ($task['status'] === 'Done' && $task['effort']) {
                    $day = $task['due_date'] ?? Carbon::now()->toDateString();
                    $pointTrend[$day] = ($pointTrend[$day] ?? 0) + $task['effort'];
                }
            }
        }

        ksort($dailyTrend);
        ksort($pointTrend);
        $allDays = array_unique(array_merge(array_keys($dailyTrend), array_keys($pointTrend)));
        sort($allDays);

        return [
            'daily_trend' => [
                'labels' => $allDays,
                'datasets' => [
                    [
                        'label' => 'Hours Logged',
                        'data' => array_map(fn($d) => round(($dailyTrend[$d] ?? 0)/3600, 2), $allDays),
                        'borderColor' => '#4f46e5',
                        'backgroundColor' => 'rgba(79, 70, 229, 0.1)',
                        'fill' => true,
                        'tension' => 0.4
                    ],
                    [
                        'label' => 'Points Done',
                        'data' => array_map(fn($d) => $pointTrend[$d] ?? 0, $allDays),
                        'borderColor' => '#10b981',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                        'fill' => true,
                        'tension' => 0.4
                    ]
                ]
            ],
            'project_dist' => [
                'labels' => array_keys($projectStats),
                'datasets' => [[
                    'data' => array_map(fn($s) => round($s/3600, 2), array_values($projectStats)),
                    'backgroundColor' => ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
                ]]
            ]
        ];
    }

    public function updateTaskMeta(Request $request, Task $task)
    {
        $v = $request->validate([
            'manual_effort_override' => 'nullable|numeric',
            'effort' => 'nullable|integer',
            'priority' => 'nullable|string'
        ]);

        if ($request->has('manual_effort_override')) {
            $task->manual_effort_override = $v['manual_effort_override'] ? ($v['manual_effort_override'] * 3600) : null;
        }
        if ($request->has('effort')) $task->effort = $v['effort'];
        if ($request->has('priority')) $task->priority = $v['priority'];

        $task->save();
        return response()->json(['success' => true]);
    }

    public function storeManualTask(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string',
            'manual_effort_override' => 'required|numeric',
            'project_id' => 'nullable|exists:projects,id',
            'assigned_to_user_id' => 'required|exists:users,id',
            'date' => 'nullable|date'
        ]);

        $task = Task::create([
            'name' => $v['name'],
            'assigned_to_user_id' => $v['assigned_to_user_id'],
            'manual_effort_override' => $v['manual_effort_override'] * 3600,
            'status' => 'Done',
            'task_type_id' => 1 // Default
        ]);

        if ($v['date']) {
            $task->created_at = $v['date'];
            $task->updated_at = $v['date'];
            $task->save();
        }

        return response()->json(['success' => true]);
    }
}
