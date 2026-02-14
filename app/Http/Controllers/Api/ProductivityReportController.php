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

        // Simple project list for manual entry
        $projects = \App\Models\Project::select('id', 'name')->orderBy('name')->get()->map(function($p) {
            return ['value' => $p->id, 'label' => $p->name];
        });

        $reportData = [];
        // Only generate report if filters are present or we want a default one
        
        if (empty($filters['date_start'])) {
            $filters['date_start'] = Carbon::now()->startOfWeek()->toDateString();
        }
        if (empty($filters['date_end'])) {
             $filters['date_end'] = Carbon::now()->endOfWeek()->toDateString();
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

        $startDate = $filters['date_start'] ? Carbon::parse($filters['date_start'])->startOfDay() : Carbon::now()->startOfWeek();
        $endDate = $filters['date_end'] ? Carbon::parse($filters['date_end'])->endOfDay() : Carbon::now()->endOfWeek();

        // 1. Fetch Activities
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
            ->with(['causer', 'subject.milestone.project', 'subject.subtasks'])
            ->orderBy('created_at')
            ->get();

        // 2. Fetch Tasks manually updated in this period (even if no activity)
        $manualTaskQuery = Task::query()
            ->whereNotNull('manual_effort_override')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->with(['milestone.project', 'subtasks']); // Load relations
            
        if (!empty($userIds)) {
            $manualTaskQuery->whereIn('assigned_to_user_id', $userIds);
        }
        $manualTasks = $manualTaskQuery->get();

        // 3. Identify all unique Users involved
        $activityUserIds = $activities->pluck('causer_id')->unique();
        $manualUserIds = $manualTasks->pluck('assigned_to_user_id')->unique();
        $allUserIds = $activityUserIds->merge($manualUserIds);

        if (!empty($userIds)) {
            $allUserIds = $allUserIds->merge($userIds);
        }
        $allUserIds = $allUserIds->unique()->filter();

        // Pre-fetch all user models
        $usersMap = User::whereIn('id', $allUserIds)->get()->keyBy('id');
        
        // Process data
        $groupedActivities = $activities->groupBy('causer_id');
        $groupedManualTasks = $manualTasks->groupBy('assigned_to_user_id');
        
        $report = [];

        foreach ($allUserIds as $userId) {
            $user = $usersMap->get($userId);
            if (!$user) continue;

            $userActivities = $groupedActivities->get($userId, collect());
            $userManualOnly = $groupedManualTasks->get($userId, collect());

            // Merge Tasks
            // Activities are grouped by Subject ID
            $tasksFromActivities = $userActivities->groupBy('subject_id');
            
            // Map manual tasks by ID
            $tasksFromManual = $userManualOnly->keyBy('id');
            
            // GetAllTaskIds
            $allTaskIds = $tasksFromActivities->keys()->merge($tasksFromManual->keys())->unique();
            
            $userTaskReports = [];
            $totalSeconds = 0;

            foreach ($allTaskIds as $taskId) {
                // Get Task Model
                $task = null;
                $taskActivities = collect();
                
                if ($tasksFromActivities->has($taskId)) {
                    $taskActivities = $tasksFromActivities[$taskId];
                    $task = $taskActivities->first()->subject;
                } elseif ($tasksFromManual->has($taskId)) {
                    $task = $tasksFromManual[$taskId];
                }
                
                if (!$task) continue;

                $sessions = [];
                // ... (Session Calculation Logic remains the same, calculating based on $taskActivities)
                // If $taskActivities is empty, sessions will be empty, which is correct for manual-only tasks.
                
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
                            // Check for multi-day span outlier
                            if ($currentSessionStart->diffInHours($time) > 12) {
                                // Cap at 5 PM of start day or +1h
                                $endOfDay = $currentSessionStart->copy()->setTime(17, 0, 0);
                                if ($currentSessionStart->gt($endOfDay)) {
                                     $endOfDay = $currentSessionStart->copy()->addHour();
                                }
                                
                                // Security check: don't let end be > now or before start
                                if ($endOfDay->gt($time)) {
                                    $endOfDay = $time; // Fallback to actual time if shorter than cap? No, this case is specifically > 12h.
                                }
                                if ($endOfDay->lt($currentSessionStart)) {
                                    $endOfDay = $currentSessionStart->copy()->addHour();
                                }

                                $duration = $currentSessionStart->diffInSeconds($endOfDay);
                                $sessions[] = [
                                    'start' => $currentSessionStart->toDateTimeString(),
                                    'end' => $endOfDay->toDateTimeString(),
                                    'duration_seconds' => $duration,
                                    'type' => 'auto_capped_outlier',
                                    'original_end' => $time->toDateTimeString()
                                ];

                            } else {
                                // Normal session
                                $duration = $currentSessionStart->diffInSeconds($time);
                                $sessions[] = [
                                    'start' => $currentSessionStart->toDateTimeString(),
                                    'end' => $time->toDateTimeString(),
                                    'duration_seconds' => $duration,
                                    'type' => 'normal'
                                ];
                            }
                            
                            $taskTotalSeconds += $duration;
                            $currentSessionStart = null;
                        }
                    }
                    $lastActivity = $activity;
                }

                // Handle open-ended session (Forgot to pause) - Same Day Logic
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
                         // Forgot to pause in the past
                        $endOfDay = $currentSessionStart->copy()->setTime(17, 0, 0);
                        if ($currentSessionStart->gt($endOfDay)) {
                             $endOfDay = $currentSessionStart->copy()->addHour();
                        }
                        
                        if ($endOfDay->gt($now)) {
                            $endOfDay = $now;
                        }
                        
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

                // Metadata
                $manualOverrideSeconds = $task->manual_effort_override;
                $usedSeconds = $manualOverrideSeconds !== null ? $manualOverrideSeconds : $taskTotalSeconds;
                
                // Subtasks & checklist (Assuming subtasks ARE the checklist based on exploration)
                // We need to load them or assume eagar loading. 
                // Let's lazy load for now or rely on if they are loaded. They aren't in the main query.
                // Optimally we should eager load 'subject.subtasks', 'subject.notes'.
                // Since $task IS 'subject', we can use it.
                // NOTE: 'subject' is eager loaded as 'task.milestone.project'. We need 'subtasks'.
                // But $task is the model instance from Activity relation. relations might be missing.
                
                $checklistItems = $task->subtasks;
                $isUsingSubtasks = $checklistItems->isNotEmpty();
                
                if (!$isUsingSubtasks) {
                     $checklistData = $task->details['checklist'] ?? [];
                     $checklistTotal = count($checklistData);
                     $checklistDone = count(array_filter($checklistData, function($i) { return !empty($i['completed']); }));
                } else {
                    $checklistTotal = $checklistItems->count();
                    $checklistDone = $checklistItems->where('status', \App\Enums\SubtaskStatus::Done)->count();
                }
                
                // Calculate Late status
                $isLate = false;
                if ($task->due_date && $task->due_date->isPast()) {
                     if ($task->status === \App\Enums\TaskStatus::Done) {
                         if ($task->actual_completion_date && $task->actual_completion_date->gt($task->due_date)) {
                             $isLate = true;
                         }
                     } else {
                         // Not done and past due
                         $isLate = true;
                     }
                }

                $userTaskReports[] = [
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'description' => $task->description,
                    'project_name' => $task->milestone?->project?->name ?? 'N/A',
                    'sessions' => $sessions,
                    'total_seconds' => $taskTotalSeconds, // Calculated from logs
                    'manual_effort_override' => $manualOverrideSeconds ? round($manualOverrideSeconds / 3600, 2) : null,
                    'used_seconds' => $usedSeconds, // The one to show in Totals
                    'effort' => $task->effort,
                    'priority' => $task->priority,
                    'checklist' => "{$checklistDone}/{$checklistTotal}",
                    'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                    'status' => $task->status->value ?? $task->status,
                    'is_late' => $isLate,
                    'subtasks' => $isUsingSubtasks
                        ? $checklistItems->map(function ($st) {
                            return [
                                'id' => $st->id,
                                'name' => $st->name,
                                'status' => $st->isCompleted() ? 'done' : 'todo'
                            ];
                        })->values()
                        : collect($task->details['checklist'] ?? [])->map(function ($item, $index) {
                            return [
                                'id' => 'detail_' . $index,
                                'name' => $item['name'] ?? 'Item', // Ensure name exists
                                'status' => ($item['completed'] ?? false) ? 'done' : 'todo'
                            ];
                        })->values(),
                ];
                $totalSeconds += $usedSeconds; // Sum the USED seconds (manual or log)
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
                        'label' => 'Logged Hours',
                        'data' => $dailyStats->values()->map(fn($v) => round($v, 2))->all(),
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
            // 3. User Comparison
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
                        ],
                         'borderColor' => [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
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
                $seconds = $t['used_seconds'];
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

    public function updateTaskMeta(Request $request, Task $task)
    {
        $validated = $request->validate([
            'manual_effort_override' => 'nullable|numeric', // in hours? UI sends hours, convert to seconds? let's stick to seconds in DB for consistency or just hours in this field?
            // "We can save that in task." - "Manual Hours". Let's assume input is hours, DB stores Minutes or Seconds. 
            // Existing duration is seconds. Let's store Seconds in DB for consistency.
            'effort' => 'nullable|integer', // Story points or hours
            'priority' => 'nullable|string',
            'comment' => 'nullable|string'
        ]);

        if ($request->has('manual_effort_override')) {
             // Input is likely hours from UI, convert to seconds
             $hours = $request->input('manual_effort_override');
             $task->manual_effort_override = $hours ? ($hours * 3600) : null;
        }

        if ($request->has('effort')) {
            $task->effort = $request->input('effort');
        }

        if ($request->has('priority')) {
            $task->priority = $request->input('priority');
        }

        if ($request->filled('comment')) {
            // Add a note/comment
            $user = $request->user();
            $task->addNote($request->input('comment'), $user);
        }

        $task->save();

        return response()->json(['message' => 'Task updated']);
    }
    public function storeManualTask(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'manual_effort_override' => 'required|numeric|min:0', // Hours
            'project_id' => 'nullable|exists:projects,id',
            'assigned_to_user_id' => 'required|exists:users,id',
            'date' => 'nullable|date'
        ]);

        $taskData = [
            'name' => $validated['name'],
            'assigned_to_user_id' => $validated['assigned_to_user_id'],
            'manual_effort_override' => $validated['manual_effort_override'] * 3600,
            'status' => \App\Enums\TaskStatus::Done,
            'description' => 'Manual entry via Productivity Report',
        ];

        if (!empty($validated['project_id'])) {
            $project = \App\Models\Project::find($validated['project_id']);
            $milestone = $project->supportMilestone();
            $taskData['milestone_id'] = $milestone->id;
        }

        $task = Task::create($taskData);
        
        // Handle date if provided
        if (!empty($validated['date'])) {
            $date = Carbon::parse($validated['date']);
            // If date is not today, force update timestamps to ensure it falls in report range
            if (!$date->isToday()) {
                $task->timestamps = false;
                $task->created_at = $date;
                $task->updated_at = $date;
                $task->save();
            }
        }

        return response()->json(['message' => 'Task created', 'task' => $task]);
    }
}
