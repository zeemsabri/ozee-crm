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
            $statusFilter = $status;
            $enum = \App\Enums\MilestoneStatus::tryFrom($status) ?? \App\Enums\MilestoneStatus::tryFrom(strtolower((string)$status));
            if ($enum) {
                $statusFilter = $enum->value;
            }
            $query->where('status', $statusFilter);
        }

        // Get the milestones: order by completion_date ascending with NULLs last
        $milestones = $query
            ->orderByRaw('completion_date IS NULL')
            ->orderBy('completion_date', 'asc')
            ->get();

        return response()->json($milestones);
    }

    /**
     * Get milestones with their expendables for a project.
     */
    public function milestonesWithExpendables(Project $project)
    {
        $milestones = $project->milestones()
            ->with([
                'expendable',
                'expendable.user' => function ($q) {
                    $q->select('id', 'name');
                },
                'budget'
            ])
            // Task counts by status
            ->withCount([
                'tasks as tasks_todo_count' => function ($q) { $q->where('status', \App\Enums\TaskStatus::ToDo->value); },
                'tasks as tasks_in_progress_count' => function ($q) { $q->where('status', \App\Enums\TaskStatus::InProgress->value); },
                'tasks as tasks_paused_count' => function ($q) { $q->where('status', \App\Enums\TaskStatus::Paused->value); },
                'tasks as tasks_blocked_count' => function ($q) { $q->where('status', \App\Enums\TaskStatus::Blocked->value); },
                'tasks as tasks_done_count' => function ($q) { $q->where('status', \App\Enums\TaskStatus::Done->value); },
                'tasks as tasks_archived_count' => function ($q) { $q->where('status', 'Archived'); },
                'tasks as tasks_total_count'
            ])
            ->orderByRaw('completion_date IS NULL')
            ->orderBy('completion_date', 'asc')
            ->get()
            ->map(function ($m) {
                $total = $m->expendable->sum('amount');
                return array_merge($m->toArray(), [
                    'expendables_total' => $total,
                ]);
            });

        return response()->json($milestones);
    }

    /**
     * List reasons (notes) attached to a milestone in descending order.
     */
    public function reasons(Milestone $milestone)
    {
        $notes = $milestone->notes()
            ->where('type', 'milestone')
            ->latest()
            ->get(['id', 'content', 'created_at', 'creator_id', 'creator_type', 'user_id'])
            ->map(function ($n) {
                return [
                    'id' => $n->id,
                    'content' => $n->content, // decrypted via accessor
                    'created_at' => $n->created_at,
                    'creator_name' => $n->creator_name,
                ];
            });

        return response()->json($notes);
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
            'status' => 'required|string',
            'project_id' => 'required|exists:projects,id',
        ]);

        // Soft-validate status against registry
        app(\App\Services\ValueSetValidator::class)->validate('Milestone','status', $validated['status']);
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
            'status' => 'sometimes|required|string',
            'project_id' => 'sometimes|required|exists:projects,id',
        ]);

        // Soft-validate status when provided
        if (array_key_exists('status', $validated)) {
            app(\App\Services\ValueSetValidator::class)->validate('Milestone','status', $validated['status']);
        }
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
    public function markAsCompleted(Request $request, Milestone $milestone)
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:100',
        ]);

        // Block completion if any contracts are still pending approval
        $hasPendingContracts = $milestone->expendable()
            ->where('status', \App\Models\ProjectExpendable::STATUS_PENDING)
            ->exists();
        if ($hasPendingContracts) {
            return response()->json([
                'message' => 'You must approve or reject all contracts for this milestone before marking it complete.',
                'code' => 'PENDING_CONTRACTS',
            ], 422);
        }

        // Update milestone status and timestamps
        app(\App\Services\ValueSetValidator::class)->validate('Milestone','status', \App\Enums\MilestoneStatus::Completed);
        $milestone->status = \App\Enums\MilestoneStatus::Completed;
        $milestone->completed_at = now();
        $milestone->save();

        // Create a project note of type 'milestone' and notify
        $milestone->load('project');
        if ($milestone->project) {
            $content = "Milestone '{$milestone->name}' marked complete. Review: " . $validated['reason'];
            \App\Models\ProjectNote::createAndNotify($milestone->project, $content, [
                'type' => 'milestone',
                'noteable' => $milestone,
            ]);
        }

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

    /**
     * Reject a completed milestone.
     */
    public function reject(Request $request, Milestone $milestone)
    {
        $data = $request->validate([
            'reason' => 'required|string',
        ]);

        $milestone->status = Milestone::PENDING;
        $milestone->completed_at = null;
        $milestone->save();

        // Create a project note and notify Google Chat
        $milestone->load('project');
        if ($milestone->project) {
            $content = "Milestone '{$milestone->name}' rejected. Reason: " . $data['reason'];
            \App\Models\ProjectNote::createAndNotify($milestone->project, $content, [
                'type' => 'milestone',
                'noteable' => $milestone,
            ]);
        }

        $milestone->load('tasks');
        return response()->json($milestone);
    }

    /**
     * Approve a completed milestone.
     */
    public function approve(Request $request, Milestone $milestone)
    {
        $data = $request->validate([
            'reason' => 'required|string',
        ]);

        $milestone->actual_completion_date = now();
        $milestone->status = Milestone::APPROVED;
        $milestone->save();

        // Create a project note and notify Google Chat
        $milestone->load('project');
        if ($milestone->project) {
            $content = "Milestone '{$milestone->name}' approved. Reason: " . $data['reason'];
            \App\Models\ProjectNote::createAndNotify($milestone->project, $content, [
                'type' => 'milestone',
                'noteable' => $milestone,
            ]);
        }

        $milestone->load('tasks');
        return response()->json($milestone);
    }

    /**
     * Reopen a milestone back to active state.
     */
    public function reopen(Request $request, Milestone $milestone)
    {
        $data = $request->validate([
            'reason' => 'required|string',
        ]);

        $milestone->status = Milestone::IN_PROGRESS;
        $milestone->actual_cmopletion_date = null;
        $milestone->completed_at = null;
        $milestone->save();

        // Create a project note and notify Google Chat
        $milestone->load('project');
        if ($milestone->project) {
            $content = "Milestone '{$milestone->name}' reopened. Reason: " . $data['reason'];
            \App\Models\ProjectNote::createAndNotify($milestone->project, $content, [
                'type' => 'milestone',
                'noteable' => $milestone,
            ]);
        }

        $milestone->load('tasks');
        return response()->json($milestone);
    }
}
