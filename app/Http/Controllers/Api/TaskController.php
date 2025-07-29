<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Milestone;
use App\Models\TaskType;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
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
                      ->where('status', '!=', 'Done');
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
                    $query->where('status', '!=', 'Done')
                          ->where('status', '!=', 'Archived');
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
            $query->where('status', $status);
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
            'status' => 'required|in:To Do,In Progress,Done,Blocked,Archived',
            'task_type_id' => 'required|exists:task_types,id',
            'milestone_id' => 'required|exists:milestones,id',
            'tags' => 'nullable|array',
            'tags.*' => 'nullable|string',
        ]);

        // Create the task
        $task = Task::create($validated);

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

        return response()->json($task, 201);
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
        // Validate the request
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'status' => 'sometimes|required|in:To Do,In Progress,Done,Blocked,Archived',
            'task_type_id' => 'sometimes|required|exists:task_types,id',
            'milestone_id' => 'nullable|exists:milestones,id',
            'tags' => 'nullable|array',
        ]);

        // Update the task
        $task->update($validated);

//        // Sync tags if provided
//        if (isset($validated['tags'])) {
//            $task->tags()->sync($validated['tags']);
//        }

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Task $task)
    {
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
        $task->start(Auth::user());

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }

    /**
     * Block a task (change status to Blocked).
     *
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function block(Task $task)
    {
        $task->block();

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
        $task->archive();

        // Load relationships
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

        return response()->json($task);
    }
}
