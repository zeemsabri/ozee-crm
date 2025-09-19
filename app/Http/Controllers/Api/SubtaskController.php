<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Enums\SubtaskStatus;

class SubtaskController extends Controller
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
        $taskId = $request->query('task_id');
        $status = $request->query('status');
        $assignedToUserId = $request->query('assigned_to_user_id');

        // Start with a base query
        $query = Subtask::with(['assignedTo', 'parentTask']);

        // Apply filters if provided
        if ($taskId) {
            $query->where('parent_task_id', $taskId);
        }

        if ($status) {
            $statusFilter = $status;
            $raw = (string) $status;
            $normalized = strtolower(str_replace(['_', '-'], ' ', $raw));
            $enum = SubtaskStatus::tryFrom($raw);
            if (!$enum) {
                foreach (SubtaskStatus::cases() as $case) {
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

        // Get the subtasks
        $subtasks = $query->orderBy('due_date', 'asc')->get();

        return response()->json($subtasks);
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
            'parent_task_id' => 'required|exists:tasks,id',
        ]);

        // Coerce and soft-validate status via value dictionary
        if (array_key_exists('status', $validated)) {
            $raw = (string)$validated['status'];
            $enum = SubtaskStatus::tryFrom($raw) ?? SubtaskStatus::tryFrom(ucwords(strtolower($raw)));
            if ($enum) {
                $validated['status'] = $enum->value;
            }
            app(\App\Services\ValueSetValidator::class)->validate('Subtask','status', $validated['status']);
        }

        // Create the subtask
        $subtask = Subtask::create($validated);

        // Load relationships
        $subtask->load(['assignedTo', 'parentTask']);

        return response()->json($subtask, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param Subtask $subtask
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Subtask $subtask)
    {
        // Load relationships
        $subtask->load(['assignedTo', 'parentTask']);

        return response()->json($subtask);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Subtask $subtask
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Subtask $subtask)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'status' => 'sometimes|required|in:To Do,In Progress,Done,Blocked',
            'parent_task_id' => 'sometimes|required|exists:tasks,id',
        ]);

        // Update the subtask
        $subtask->update($validated);

        // Load relationships
        $subtask->load(['assignedTo', 'parentTask']);

        return response()->json($subtask);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Subtask $subtask
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Subtask $subtask)
    {
        // Delete the subtask
        $subtask->delete();

        return response()->json(['message' => 'Subtask deleted successfully'], 200);
    }

    /**
     * Add a note to a subtask.
     *
     * @param Request $request
     * @param Subtask $subtask
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNote(Request $request, Subtask $subtask)
    {
        // Validate the request
        $validated = $request->validate([
            'note' => 'required|string',
        ]);

        // Get the authenticated user
        $user = Auth::user();

        // Add the note to the subtask
        $result = $subtask->addNote($validated['note'], $user);

        if ($result) {
            return response()->json(['message' => 'Note added successfully', 'result' => $result]);
        } else {
            return response()->json(['message' => 'Failed to add note'], 500);
        }
    }

    /**
     * Mark a subtask as completed.
     *
     * @param Subtask $subtask
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsCompleted(Subtask $subtask)
    {
        $subtask->markAsCompleted();

        // Load relationships
        $subtask->load(['assignedTo', 'parentTask']);

        return response()->json($subtask);
    }

    /**
     * Start a subtask (change status to In Progress).
     *
     * @param Subtask $subtask
     * @return \Illuminate\Http\JsonResponse
     */
    public function start(Subtask $subtask)
    {
        $subtask->start();

        // Load relationships
        $subtask->load(['assignedTo', 'parentTask']);

        return response()->json($subtask);
    }

    /**
     * Block a subtask (change status to Blocked).
     *
     * @param Subtask $subtask
     * @return \Illuminate\Http\JsonResponse
     */
    public function block(Subtask $subtask)
    {
        $subtask->block();

        // Load relationships
        $subtask->load(['assignedTo', 'parentTask']);

        return response()->json($subtask);
    }
}
