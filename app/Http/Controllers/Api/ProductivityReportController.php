<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ProductivityReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['user_ids', 'date_start', 'date_end']);
        
        $users = User::orderBy('name')->get()->map(function ($user) {
            return [
                'value' => $user->id,
                'label' => $user->name,
                'avatar' => $user->avatar
            ];
        });

        $reportData = [];
        // Only generate report if filters are present or we want a default one
        // Let's generate default for current week if no filters
        
        if (empty($filters['date_start'])) {
            $filters['date_start'] = Carbon::now()->startOfWeek()->toDateString();
        }
        if (empty($filters['date_end'])) {
             $filters['date_end'] = Carbon::now()->endOfWeek()->toDateString();
        }

        $reportData = $this->generateReport($filters);

        return response()->json([
            'users' => $users,
            'reportData' => $reportData,
            'filters' => $filters 
        ]);
    }

    private function generateReport($filters)
    {
        $userIds = $filters['user_ids'] ?? [];
        // Handle comma separated if passed as string
        if (is_string($userIds)) {
            $userIds = explode(',', $userIds);
        }

        $startDate = $filters['date_start'] ? Carbon::parse($filters['date_start'])->startOfDay() : Carbon::now()->startOfWeek();
        $endDate = $filters['date_end'] ? Carbon::parse($filters['date_end'])->endOfDay() : Carbon::now()->endOfWeek();

        // fetch activities
        $activities = Activity::query()
            ->where('subject_type', Task::class)
            ->whereIn('description', [
                'Task was started',
                'Task was paused',
                'Task was resumed',
                'Task was completed'
            ])
            ->when(!empty($userIds), function ($q) use ($userIds) {
                $q->whereIn('causer_id', $userIds);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['causer', 'subject.milestone.project']) // Eager load
            ->orderBy('causer_id')
            ->orderBy('subject_id')
            ->orderBy('created_at')
            ->get();

        // Process data
        $groupedByUser = $activities->groupBy('causer_id');
        $report = [];

        foreach ($groupedByUser as $userId => $userActivities) {
            $user = $userActivities->first()->causer;
            if (!$user) continue;

            $tasks = $userActivities->groupBy('subject_id');
            $userTaskReports = [];
            $totalSeconds = 0;

            foreach ($tasks as $taskId => $taskActivities) {
                $task = $taskActivities->first()->subject;
                if (!$task) continue; // Task might be deleted

                $sessions = [];
                $currentSessionStart = null;
                $taskTotalSeconds = 0;
                $lastActivity = null;

                foreach ($taskActivities as $activity) {
                    $desc = $activity->description;
                    $time = $activity->created_at;

                    if ($desc === 'Task was started' || $desc === 'Task was resumed') {
                        if ($currentSessionStart === null) {
                            $currentSessionStart = $time;
                        }
                    } elseif ($desc === 'Task was paused' || $desc === 'Task was completed') {
                        if ($currentSessionStart !== null) {
                            $duration = $currentSessionStart->diffInSeconds($time);
                            $sessions[] = [
                                'start' => $currentSessionStart->toDateTimeString(),
                                'end' => $time->toDateTimeString(),
                                'duration_seconds' => $duration,
                                'type' => 'normal'
                            ];
                            $taskTotalSeconds += $duration;
                            $currentSessionStart = null;
                        }
                    }
                    $lastActivity = $activity;
                }

                // Handle open-ended session (Forgot to pause)
                if ($currentSessionStart !== null) {
                    $now = Carbon::now();
                    $isToday = $currentSessionStart->isToday();
                    
                    if ($isToday) {
                        // Still working?
                        $duration = $currentSessionStart->diffInSeconds($now);
                        $sessions[] = [
                            'start' => $currentSessionStart->toDateTimeString(),
                            'end' => 'Now',
                            'duration_seconds' => $duration,
                            'type' => 'ongoing'
                        ];
                        $taskTotalSeconds += $duration;
                    } else {
                        // Cap it at end of that day (e.g. 5pm or 17:00:00 of that day)
                        // Using explicit Capping logic
                        $endOfDay = $currentSessionStart->copy()->setTime(17, 0, 0);
                        
                        // If they started AFTER 5pm, maybe give them 1 hour?
                        if ($currentSessionStart->gt($endOfDay)) {
                            $endOfDay = $currentSessionStart->copy()->addHour();
                        }

                        // If endOfDay is AFTER now (future?), clamp to now
                        if ($endOfDay->gt($now)) {
                            $endOfDay = $now;
                        }
                        
                        // If the calculated end time is BEFORE the start time (anomaly), assume 1 hour
                         if ($endOfDay->lt($currentSessionStart)) {
                             $endOfDay = $currentSessionStart->copy()->addHour();
                         }

                        $duration = $currentSessionStart->diffInSeconds($endOfDay);
                        
                        $sessions[] = [
                            'start' => $currentSessionStart->toDateTimeString(),
                            'end' => $endOfDay->toDateTimeString(),
                            'duration_seconds' => $duration,
                            'type' => 'auto_capped',
                            'original_end' => 'Missing'
                        ];
                        $taskTotalSeconds += $duration;
                    }
                }

                $userTaskReports[] = [
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'project_name' => $task->milestone?->project?->name ?? 'N/A',
                    'sessions' => $sessions,
                    'total_seconds' => $taskTotalSeconds,
                ];
                $totalSeconds += $taskTotalSeconds;
            }

            $report[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'avatar' => $user->avatar,
                'tasks' => $userTaskReports,
                'total_seconds' => $totalSeconds,
                'total_hours' => round($totalSeconds / 3600, 2)
            ];
        }

        // --- Chart Data Aggregation ---
        
        // 1. Daily Hours (Line Chart)
        $dailyData = []; 
        // We will sum hours per day for all selected users combined (or individual series if needed)
        // For simplicity: Breakdown by User per Day
        
        // Flatten all sessions
        $allSessions = [];
        foreach ($report as $r) {
            foreach ($r['tasks'] as $t) {
                foreach ($t['sessions'] as $s) {
                    $date = substr($s['start'], 0, 10);
                    $allSessions[] = [
                        'date' => $date,
                        'user_name' => $r['user_name'],
                        'seconds' => $s['duration_seconds']
                    ];
                }
            }
        }
        
        $dailyStats = collect($allSessions)->groupBy('date')->map(function ($daySessions) {
            return $daySessions->sum('seconds') / 3600;
        })->sortKeys();
        
        $chartData = [
            'daily_trend' => [
                'labels' => $dailyStats->keys()->values()->all(),
                'datasets' => [
                    [
                        'label' => 'Total Hours Worked',
                        'data' => $dailyStats->values()->all(),
                        'borderColor' => 'rgb(75, 192, 192)',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'tension' => 0.1,
                        'fill' => true
                    ]
                ]
            ],
            // 2. Project Distribution (Pie Chart)
            'project_dist' => [
                'labels' => [],
                'datasets' => []
            ],
            // 3. User Comparison (Bar Chart)
            'user_comparison' => [
                'labels' => collect($report)->pluck('user_name')->all(),
                'datasets' => [
                    [
                        'label' => 'Total Hours',
                        'data' => collect($report)->pluck('total_hours')->all(),
                        'backgroundColor' => [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)',
                        ],
                        'borderColor' => [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                        ],
                        'borderWidth' => 1
                    ]
                ]
            ]
        ];

        // Process Project Distribution
        $projectStats = [];
        foreach ($report as $r) {
            foreach ($r['tasks'] as $t) {
                $pName = $t['project_name'];
                $seconds = $t['total_seconds'];
                if (!isset($projectStats[$pName])) {
                    $projectStats[$pName] = 0;
                }
                $projectStats[$pName] += $seconds;
            }
        }
        
        $chartData['project_dist'] = [
            'labels' => array_keys($projectStats),
            'datasets' => [
                [
                    'label' => 'Hours by Project',
                    'data' => array_map(fn($s) => round($s/3600, 2), array_values($projectStats)),
                    'backgroundColor' => [
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 206, 86, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(153, 102, 255, 0.6)',
                            'rgba(255, 159, 64, 0.6)'
                    ]
                ]
            ]
        ];

        
        return [
            'details' => $report,
            'charts' => $chartData
        ];
    }
}
