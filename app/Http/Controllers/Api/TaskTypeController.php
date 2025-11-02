<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $taskTypes = TaskType::orderBy('name', 'asc')->get();

        return response()->json($taskTypes);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:task_types,name',
            'description' => 'nullable|string',
        ]);

        // Add the authenticated user as the creator
        $validated['created_by_user_id'] = Auth::id();

        // Create the task type
        $taskType = TaskType::create($validated);

        return response()->json($taskType, 201);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(TaskType $taskType)
    {
        return response()->json($taskType);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, TaskType $taskType)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:task_types,name,'.$taskType->id,
            'description' => 'nullable|string',
        ]);

        // Update the task type
        $taskType->update($validated);

        return response()->json($taskType);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(TaskType $taskType)
    {
        // Check if there are any tasks using this task type
        $tasksCount = $taskType->tasks()->count();

        if ($tasksCount > 0) {
            return response()->json([
                'message' => 'Cannot delete task type because it is being used by '.$tasksCount.' task(s).',
                'tasks_count' => $tasksCount,
            ], 422);
        }

        // Delete the task type
        $taskType->delete();

        return response()->json(['message' => 'Task type deleted successfully'], 200);
    }
}
