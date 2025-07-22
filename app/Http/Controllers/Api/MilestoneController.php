<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MilestoneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * This method handles two different route patterns:
     * 1. GET /api/milestones?project_id=X - General route with query parameter
     * 2. GET /api/projects/{project}/milestones - Project-specific route with route parameter
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Get query parameters
        $projectId = $request->query('project_id');
        $status = $request->query('status');

        // Check if we're using the project-specific route
        // If route parameter 'project' exists, use it as the project ID
        // This handles the case when the route is /api/projects/{project}/milestones
        if ($request->route('project')) {
            // The project parameter could be a Project model instance or just an ID
            $projectId = $request->route('project')->id ?? $request->route('project');
        }

        // Start with a base query
        $query = Milestone::with(['tasks']);

        // Apply filters if provided
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        // Get the milestones
        $milestones = $query->orderBy('completion_date', 'asc')->get();

        return response()->json($milestones);
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
            'completion_date' => 'nullable|date',
            'status' => 'required|in:Not Started,In Progress,Completed,Overdue',
            'project_id' => 'required|exists:projects,id',
        ]);

        // Create the milestone
        $milestone = Milestone::create($validated);

        // Load relationships
        $milestone->load(['tasks']);

        return response()->json($milestone, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param Milestone $milestone
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Milestone $milestone)
    {
        // Load relationships
        $milestone->load(['tasks']);

        return response()->json($milestone);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Milestone $milestone
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Milestone $milestone)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'completion_date' => 'nullable|date',
            'actual_completion_date' => 'nullable|date',
            'status' => 'sometimes|required|in:Not Started,In Progress,Completed,Overdue',
            'project_id' => 'sometimes|required|exists:projects,id',
        ]);

        // Update the milestone
        $milestone->update($validated);

        // Load relationships
        $milestone->load(['tasks']);

        return response()->json($milestone);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Milestone $milestone
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Milestone $milestone)
    {
        // Delete the milestone (this will also delete related tasks due to cascade)
        $milestone->delete();

        return response()->json(['message' => 'Milestone deleted successfully'], 200);
    }

    /**
     * Mark a milestone as completed.
     *
     * @param Milestone $milestone
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsCompleted(Milestone $milestone)
    {
        $milestone->markAsCompleted();

        // Load relationships
        $milestone->load(['tasks']);

        return response()->json($milestone);
    }

    /**
     * Start a milestone (change status to In Progress).
     *
     * @param Milestone $milestone
     * @return \Illuminate\Http\JsonResponse
     */
    public function start(Milestone $milestone)
    {
        $milestone->start();

        // Load relationships
        $milestone->load(['tasks']);

        return response()->json($milestone);
    }
}
