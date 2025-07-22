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
            'milestone_id' => 'nullable|exists:milestones,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        // Create the task
        $task = Task::create($validated);

        // Attach tags if provided
        if (isset($validated['tags']) && is_array($validated['tags'])) {
            $task->tags()->attach($validated['tags']);
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
        $task->load(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks']);

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
            'tags.*' => 'exists:tags,id',
        ]);

        // Update the task
        $task->update($validated);

        // Sync tags if provided
        if (isset($validated['tags'])) {
            $task->tags()->sync($validated['tags']);
        }

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
