<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesSchedules;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Milestone;
use App\Models\TaskType;
use App\Models\Tag;
use App\Models\ProjectExpendable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Enums\TaskStatus;

class TaskController extends Controller
{
    use HandlesSchedules;
    /**
     * Get today's due tasks and overdue tasks for a project.
     *
     * @param int $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectDueAndOverdueTasks($projectId)
    {
        // Get all milestones for the project
        $milestoneIds = Milestone::where('project_id', $projectId)->pluck('id');

        // Get today's date
        $today = now()->startOfDay();

        // Query tasks that are due today or overdue
        $tasks = Task::with(['assignedTo', 'taskType', 'milestone'])
            ->whereIn('milestone_id', $milestoneIds)
            ->where(function($query) use ($today) {
                // Tasks due today
                $query->whereDate('due_date', $today)
                // Or tasks that are overdue (due date is in the past and not completed)
                ->orWhere(function($q) use ($today) {
                    $q->whereDate('due_date', '<', $today)
                      ->where('status', '!=', TaskStatus::Done->value);
                });
            })
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Get task statistics for dashboard
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTaskStatistics()
    {
        // Get the authenticated user
        $user = Auth::user();

        // Get current date for "due today" calculations
        $today = now()->startOfDay();

        // Initialize statistics
        $statistics = [
            'total_due_tasks' => 0,
            'projects' => []
        ];

        // Get all projects the user has access to
        $projects = [];

        if ($user->isSuperAdmin() || $user->isManager()) {
            // Super admins and managers can see all projects
            $projects = \App\Models\Project::select('id', 'name', 'status')->get();
        } else {
            // Other users can only see projects they're assigned to
            $projects = $user->projects()->select('projects.id', 'projects.name', 'projects.status')->get();
        }

        // For each project, get task statistics
        foreach ($projects as $project) {
            // Get all milestones for this project
            $milestoneIds = \App\Models\Milestone::where('project_id', $project->id)->pluck('id')->toArray();

            if (empty($milestoneIds)) {
                // Skip projects with no milestones
                continue;
            }

            // Get tasks for these milestones
            $tasks = \App\Models\Task::whereIn('milestone_id', $milestoneIds)
                ->where(function($query) {
                    // Only include tasks that are not completed or archived
                    $query->where('status', '!=', TaskStatus::Done->value)
                          ->where('status', '!=', TaskStatus::Archived->value);
                })
                ->get();

            if ($tasks->isEmpty()) {
                // Skip projects with no active tasks
                continue;
            }

            // Count due tasks
            $dueTasks = $tasks->filter(function($task) {
                return $task->due_date !== null;
            })->count();

            // Count tasks due today
            $dueToday = $tasks->filter(function($task) use ($today) {
                return $task->due_date !== null && $task->due_date->startOfDay()->equalTo($today);
            })->count();

            // Count tasks assigned to current user
            $assignedToMe = $tasks->filter(function($task) use ($user) {
                return $task->assigned_to_user_id === $user->id;
            })->count();

            // Only include projects with due tasks
            if ($dueTasks > 0) {
                $statistics['projects'][] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'due_tasks' => $dueTasks,
                    'due_today' => $dueToday,
                    'assigned_to_me' => $assignedToMe
                ];

                // Add to total due tasks
                $statistics['total_due_tasks'] += $dueTasks;
            }
        }

        return response()->json($statistics);
    }
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Get query parameters
        $milestoneId = $request->query('milestone_id');
        $status = $request->query('status');
        $assignedToUserId = $request->query('assigned_to_user_id');

        // Start with a base query
        $query = Task::with(['assignedTo', 'taskType', 'milestone', 'tags']);

        // Apply filters if provided
        if ($milestoneId) {
            $query->where('milestone_id', $milestoneId);
        }

        if ($status) {
            $statusFilter = $status;
            // Coerce common input formats (case-insensitive, snake/kebab to spaces) to enum value
            $raw = (string) $status;
            $normalized = strtolower(str_replace(['_', '-'], ' ', $raw));
            $enum = TaskStatus::tryFrom($raw);
            if (!$enum) {
                foreach (TaskStatus::cases() as $case) {
                    if ($normalized === strtolower($case->value)) {
                        $enum = $case;
                        break;
                    }
                }
            }
            if ($enum) {
                $statusFilter = $enum->value;
            }
            $query->where('status', $statusFilter);
        }

        if ($assignedToUserId) {
            $query->where('assigned_to_user_id', $assignedToUserId);
        }

        // Get the tasks
        $tasks = $query->orderBy('due_date', 'asc')->get();

        return response()->json($tasks);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'status' => 'required|string',
            'task_type_id' => 'required|exists:task_types,id',
            'milestone_id' => 'required|exists:milestones,id',
            'needs_approval' => 'sometimes|boolean',
        ]);

        // Coerce and soft-validate status via value dictionary
        if (array_key_exists('status', $validated)) {
            $enum = TaskStatus::tryFrom($validated['status']) ?? TaskStatus::tryFrom(ucwords(strtolower((string)$validated['status'])));
            if ($enum) {
                $validated['status'] = $enum->value;
            }
            app(\App\Services\ValueSetValidator::class)->validate('Task','status', $validated['status']);
        }

        // Create the task
        $task = Task::create($validated);

        $task->syncTags($request->tags ?? []);

        // Attach tags if provided
        if (isset($validated['tags']) && is_array($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName, 'created_by_user_id' => Auth::id()]);
                $tagIds[] = $tag->id;
            }
            $task->tags()->attach($tagIds);
        }

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags']);

        // Optionally create a schedule when provided as nested payload
        $attachedScheduleId = null;
        $schedulePayload = $request->input('schedule');
        if (is_array($schedulePayload) && !empty($schedulePayload)) {
            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'start_at' => ['required', 'date'],
                'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
                'mode' => ['required', 'in:once,daily,weekly,monthly,yearly,cron'],
                'time' => ['nullable', 'string'],
                'days_of_week' => ['array'],
                'days_of_week.*' => ['integer', 'between:0,6'],
                'day_of_month' => ['nullable', 'integer', 'between:1,31'],
                'nth' => ['nullable', 'integer', 'between:1,5'],
                'dow_for_monthly' => ['nullable', 'integer', 'between:0,6'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'cron' => ['nullable', 'string'],
            ];
            $payload = validator($schedulePayload, $rules)->validate();
            $payload['scheduled_item_type'] = 'task';
            $payload['scheduled_item_id'] = $task->id;
            $schedule = $this->persistScheduleFromArray($payload);
            $attachedScheduleId = $schedule->id;
        }

        return response()->json(array_filter([
            'attached_schedule_id' => $attachedScheduleId,
        ]) + $task->toArray(), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Task $task)
    {
        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks', 'notes' => function ($q) {
            $q->select('id', 'content', 'noteable_type', 'noteable_id', 'created_at', 'creator_type', 'creator_id');
        }]);

        return response()->json($task);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Task $task)
    {
        // Check if task is completed and trying to change priority or assignment
        $statusEnum = $task->status instanceof TaskStatus ? $task->status : TaskStatus::tryFrom((string)$task->status);
        if ($statusEnum === TaskStatus::Done) {
            if ($request->has('priority') || $request->has('assigned_to_user_id')) {
                return response()->json([
                    'message' => 'Cannot change priority or assignment for a completed task. Use the Revise button to change the task status first.',
                    'status' => 'error'
                ], 422);
            }
        }

        // Validate the request
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'status' => 'sometimes|required|string',
            'task_type_id' => 'sometimes|required|exists:task_types,id',
            'milestone_id' => 'nullable|exists:milestones,id',
            'details' => 'nullable|array',
            'needs_approval' => 'sometimes|boolean',
        ]);

        // Coerce and soft-validate status via value dictionary (update)
        if (array_key_exists('status', $validated)) {
            $enum = TaskStatus::tryFrom($validated['status']) ?? TaskStatus::tryFrom(ucwords(strtolower((string)$validated['status'])));
            if ($enum) {
                $validated['status'] = $enum->value;
            }
            app(\App\Services\ValueSetValidator::class)->validate('Task','status', $validated['status']);
        }

        // Update the task
        $task->update($validated);

        $task->syncTags($request->tags ?? []);

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        // Optionally create a schedule when provided during update
        $attachedScheduleId = null;
        $schedulePayload = $request->input('schedule');
        if (is_array($schedulePayload) && !empty($schedulePayload)) {
            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'start_at' => ['required', 'date'],
                'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
                'mode' => ['required', 'in:once,daily,weekly,monthly,yearly,cron'],
                'time' => ['nullable', 'string'],
                'days_of_week' => ['array'],
                'days_of_week.*' => ['integer', 'between:0,6'],
                'day_of_month' => ['nullable', 'integer', 'between:1,31'],
                'nth' => ['nullable', 'integer', 'between:1,5'],
                'dow_for_monthly' => ['nullable', 'integer', 'between:0,6'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'cron' => ['nullable', 'string'],
            ];
            $payload = validator($schedulePayload, $rules)->validate();
            $payload['scheduled_item_type'] = 'task';
            $payload['scheduled_item_id'] = $task->id;
            $schedule = $this->persistScheduleFromArray($payload);
            $attachedScheduleId = $schedule->id;
        }

        return response()->json(array_filter([
            'attached_schedule_id' => $attachedScheduleId,
        ]) + $task->toArray());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Task $task)
    {
        // Log activity with the authenticated user as causer
        activity()
            ->causedBy(Auth::user())
            ->performedOn($task)
            ->log('Task was deleted');

        // Delete the task (this will also delete related subtasks due to cascade)
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully'], 200);
    }

    /**
     * Add a note to a task.
     *
     * @param Request $request
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNote(Request $request, Task $task)
    {
        // Validate the request
        $validated = $request->validate([
            'note' => 'required|string',
        ]);

        // Get the authenticated user
        $user = Auth::user();

        // Add the note to the task
        $result = $task->addNote($validated['note'], $user);

        if ($result) {
            return response()->json(['message' => 'Note added successfully', 'result' => $result]);
        } else {
            return response()->json(['message' => 'Failed to add note'], 500);
        }
    }

    /**
     * Mark a task as completed.
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsCompleted(Task $task)
    {
        // Check if task can be completed (must be in progress)
        $statusEnum = $task->status instanceof TaskStatus ? $task->status : TaskStatus::tryFrom((string)$task->status);
        if ($statusEnum !== TaskStatus::InProgress) {
            return response()->json([
                'message' => 'Task must be started before it can be completed',
                'status' => 'error'
            ], 422);
        }

        // Soft-validate target status via the value dictionary (non-enforcing)
        app(\App\Services\ValueSetValidator::class)->validate('Task','status', TaskStatus::Done);

        // The LogsActivity trait will automatically log this activity
        $task->markAsCompleted(Auth::user());

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    /**
     * Start a task (change status to In Progress).
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function start(Task $task)
    {
        // Soft-validate target status via the value dictionary (non-enforcing)
        app(\App\Services\ValueSetValidator::class)->validate('Task','status', TaskStatus::InProgress);

        // The LogsActivity trait will automatically log this activity
        $task->start(Auth::user());

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    /**
     * Block a task (change status to Blocked).
     *
     * @param Request $request
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function block(Request $request, Task $task)
    {
        // Validate the request
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        // Save the current status before blocking
        $previousStatus = $task->status instanceof TaskStatus ? $task->status->value : (string)$task->status;

        // Update task status and reason
        $task->previous_status = $previousStatus;
        app(\App\Services\ValueSetValidator::class)->validate('Task','status', TaskStatus::Blocked);
        $task->status = TaskStatus::Blocked;
        $task->block_reason = $validated['reason'];
        $task->save();

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    /**
     * Unblock a task (change status back to previous status or To Do).
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function unblock(Task $task)
    {
        // Check if task is blocked
        $statusEnum = $task->status instanceof TaskStatus ? $task->status : TaskStatus::tryFrom((string)$task->status);
        if ($statusEnum !== TaskStatus::Blocked) {
            return response()->json([
                'message' => 'Only blocked tasks can be unblocked',
                'status' => 'error'
            ], 422);
        }

        // Restore previous status or default to To Do
        $nextStatus = $task->previous_status ?: TaskStatus::ToDo->value;
        $coerced = TaskStatus::tryFrom($nextStatus) ?? TaskStatus::tryFrom(ucwords(strtolower((string)$nextStatus)));
        $finalStatus = $coerced ? $coerced : TaskStatus::ToDo;
        app(\App\Services\ValueSetValidator::class)->validate('Task','status', $finalStatus);
        $task->status = $finalStatus;
        $task->block_reason = null;
        $task->previous_status = null;
        $task->save();

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    /**
     * Pause a task (change status from In Progress to Paused).
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function pause(Task $task)
    {
        // Check if task is in progress
        $statusEnum = $task->status instanceof TaskStatus ? $task->status : TaskStatus::tryFrom((string)$task->status);
        if ($statusEnum !== TaskStatus::InProgress) {
            return response()->json([
                'message' => 'Only tasks in progress can be paused',
                'status' => 'error'
            ], 422);
        }

        // Update task status
        app(\App\Services\ValueSetValidator::class)->validate('Task','status', TaskStatus::Paused);
        $task->status = TaskStatus::Paused;
        $task->save();

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    /**
     * Resume a task (change status from Paused to In Progress).
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function resume(Task $task)
    {
        // Check if task is paused
        $statusEnum = $task->status instanceof TaskStatus ? $task->status : TaskStatus::tryFrom((string)$task->status);
        if ($statusEnum !== TaskStatus::Paused) {
            return response()->json([
                'message' => 'Only paused tasks can be resumed',
                'status' => 'error'
            ], 422);
        }

        // Update task status
        app(\App\Services\ValueSetValidator::class)->validate('Task','status', TaskStatus::InProgress);
        $task->status = TaskStatus::InProgress;
        $task->save();

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    /**
     * Archive a task (change status to Archived).
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function archive(Task $task)
    {
        // Soft-validate target status via the value dictionary (non-enforcing)
        app(\App\Services\ValueSetValidator::class)->validate('Task','status', TaskStatus::Archived);

        $task->archive();

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    /**
     * Get tasks assigned to the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Revise a completed task (change status back to To Do).
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function revise(Task $task)
    {
        // Check if task is completed
        $statusEnum = $task->status instanceof TaskStatus ? $task->status : TaskStatus::tryFrom((string)$task->status);
        if ($statusEnum !== TaskStatus::Done) {
            return response()->json([
                'message' => 'Only completed tasks can be revised',
                'status' => 'error'
            ], 422);
        }

        // Change status back to To Do
        app(\App\Services\ValueSetValidator::class)->validate('Task','status', TaskStatus::ToDo);
        $task->status = TaskStatus::ToDo;
        $task->save();

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    public function getAssignedTasks()
    {
        // Get the authenticated user
        $user = Auth::user();

        // Get tasks assigned to the user
        $tasks = Task::with(['milestone.project', 'taskType'])
            ->where('assigned_to_user_id', $user->id)
            ->where('status', '!=', TaskStatus::Done->value)
            ->where('status', '!=', TaskStatus::Archived->value)
            ->orderBy('due_date', 'asc')
            ->get();

        // Transform the tasks to include project information
        $transformedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'status' => $task->status,
                'due_date' => $task->due_date,
                'project_id' => $task->milestone->project_id ?? null,
                'milestone' => $task->milestone ? [
                    'id' => $task->milestone->id,
                    'name' => $task->milestone->name
                ] : null,
                'project' => $task->milestone && $task->milestone->project ? [
                    'id' => $task->milestone->project->id,
                    'name' => $task->milestone->project->name
                ] : null
            ];
        });

        return response()->json($transformedTasks);
    }

    /**
     * Create multiple tasks from a Project Expendable (contract) reference.
     * Expects payload: { tasks: [ { name, description?, dueDate?, priority?, contract_id } ] }
     */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'tasks' => 'required|array|min:1',
            'tasks.*.name' => 'required|string|max:255',
            'tasks.*.description' => 'nullable|string',
            'tasks.*.dueDate' => 'required|date|after_or_equal:today',
            'tasks.*.priority' => 'nullable|string|in:Low,Medium,High',
            'tasks.*.contract_id' => 'required|integer|exists:project_expendables,id',
        ]);

        $created = [];

        // Find or create a default Task Type (General)
        $defaultTaskType = TaskType::firstOrCreate(['name' => 'New']);

        foreach ($validated['tasks'] as $item) {
            $expendable = ProjectExpendable::with('expendable')->find($item['contract_id']);

            if (!$expendable) {
                // Should not happen due to validation, but be safe
                return response()->json(['message' => 'Contract not found: ' . $item['contract_id']], 404);
            }

            // Ensure expendable is linked to a Milestone
            if (!$expendable->expendable || !$expendable->expendable instanceof Milestone) {
                return response()->json([
                    'message' => 'Provided contract is not linked to a milestone',
                    'contract_id' => $expendable->id,
                ], 422);
            }

            $milestone = $expendable->expendable; // Milestone instance

            // Additional validation: due date should not exceed milestone completion date (if set)
            if (!empty($item['dueDate']) && !empty($milestone->completion_date)) {
                $due = \Carbon\Carbon::parse($item['dueDate'])->startOfDay();
                $completion = \Carbon\Carbon::parse($milestone->completion_date)->startOfDay();
                if ($due->gt($completion)) {
                    return response()->json([
                        'message' => 'The task due date may not be after the milestone completion date.',
                        'contract_id' => $expendable->id,
                        'dueDate' => $item['dueDate'],
                        'completion_date' => $milestone->completion_date?->toDateString() ?? (string) $milestone->completion_date,
                    ], 422);
                }
            }

            $taskData = [
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'due_date' => $item['dueDate'] ?? null,
                'priority' => $item['priority'] ?? 'Medium',
                'status' => TaskStatus::ToDo->value,
                'task_type_id' => $defaultTaskType->id,
                'milestone_id' => $milestone->id,
                'assigned_to_user_id' => $expendable->user_id,
            ];

            // Soft-validate task status using the value dictionary (non-enforcing)
            app(\App\Services\ValueSetValidator::class)->validate('Task','status', TaskStatus::ToDo);

            $task = Task::create($taskData);
            $task->load(['assignedTo', 'taskType', 'milestone']);
            $created[] = $task;
        }

        return response()->json(['tasks' => $created], 201);
    }
}
