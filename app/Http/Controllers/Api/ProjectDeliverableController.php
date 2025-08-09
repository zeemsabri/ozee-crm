<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProjectDeliverableController extends Controller
{
    /**
     * Display a listing of the resource for a specific project.
     */
    public function index(string $projectId)
    {
        $project = Project::findOrFail($projectId);

        $deliverables = $project->projectDeliverables()
            ->with('milestone')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($deliverables);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $projectId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'milestone_id' => 'nullable|exists:milestones,id',
            'status' => 'required|string|in:pending,in_progress,completed,cancelled',
            'due_date' => 'nullable|date',
            'details' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $project = Project::findOrFail($projectId);

        $deliverable = new ProjectDeliverable($request->all());
        $deliverable->project_id = $project->id;
        $deliverable->save();

        return response()->json($deliverable, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $deliverable = ProjectDeliverable::with(['project', 'milestone', 'tasks'])->findOrFail($id);
        return response()->json($deliverable);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'milestone_id' => 'nullable|exists:milestones,id',
            'status' => 'required|string|in:pending,in_progress,completed,cancelled',
            'due_date' => 'nullable|date',
            'details' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deliverable = ProjectDeliverable::findOrFail($id);

        // Update the deliverable
        $deliverable->fill($request->all());

        // If status is changed to completed, set completed_at date
        if ($deliverable->isDirty('status') && $request->status === 'completed') {
            $deliverable->completed_at = now();
        }

        $deliverable->save();

        return response()->json($deliverable);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $deliverable = ProjectDeliverable::findOrFail($id);
        $deliverable->delete();

        return response()->json(null, 204);
    }
}
