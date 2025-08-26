<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
    /**
     * Return projects for the current user along with their workspace role in each project.
     * Role is derived from project leads we recently added:
     *  - 'manager' if user is project_manager_id
     *  - 'admin' if user is project_admin_id
     *  - 'doer' otherwise
     * Super Admins and Managers can see all projects; other users only see their assigned projects
     */
    public function projects(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $isGlobalManager = (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) || (method_exists($user, 'isManager') && $user->isManager());

        // Pagination params
        $perPage = (int) $request->get('per_page', 5);
        if ($perPage <= 0 || $perPage > 50) {
            $perPage = 5;
        }
        $page = (int) $request->get('page', 1);

        // Base query: mirror logic from ProjectReadController@getProjectsSimplified
        if ($isGlobalManager) {
            $query = Project::select('id', 'name', 'status', 'project_manager_id', 'project_admin_id', 'project_type', 'last_email_sent', 'last_email_received')
                ->with('tags')
                ->orderBy('id', 'desc');
        } else {
            $query = $user->projects()
                ->select('projects.id', 'projects.name', 'projects.status', 'projects.project_manager_id', 'projects.project_admin_id', 'projects.project_type', 'projects.last_email_sent', 'projects.last_email_received')
                ->with('tags')
                ->orderBy('projects.id', 'desc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $projects = $paginator->getCollection();

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $mapped = $projects->map(function (Project $project) use ($user, $today, $tomorrow, $isGlobalManager) {
            // Derive role
            $role = 'doer';
            if ($project->project_manager_id === $user->id) {
                $role = 'manager';
            } elseif ($project->project_admin_id === $user->id) {
                $role = 'admin';
            }

            // Treat global managers/super-admins as managers for task visibility
            $effectiveRole = $isGlobalManager ? 'manager' : $role;

            // Collect milestone ids once
            $milestoneIds = $project->milestones()->pluck('id');

            // Base task query for this project
            $baseQuery = Task::query()
                ->whereIn('milestone_id', $milestoneIds)
                ->whereNot('status', Task::STATUS_DONE);

            // Scope to own tasks if doer
            if ($effectiveRole === 'doer') {
                $baseQuery = $baseQuery->where('assigned_to_user_id', $user->id);
            }

            // Today (including overdue): due_date <= today
            $todayTasks = (clone $baseQuery)
                ->whereDate('due_date', '<=', $today)
                ->orderBy('due_date', 'asc')
                ->limit(50)
                ->get(['id', 'name', 'status', 'due_date'])
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'status' => $t->status,
                        'due_date' => optional($t->due_date)->toDateString(),
                    ];
                })->values();

            // Tomorrow: due_date == tomorrow
            $tomorrowTasks = (clone $baseQuery)
                ->whereDate('due_date', '=', $tomorrow)
                ->orderBy('due_date', 'asc')
                ->limit(50)
                ->get(['id', 'name', 'status', 'due_date'])
                ->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'name' => $t->name,
                        'status' => $t->status,
                        'due_date' => optional($t->due_date)->toDateString(),
                    ];
                })->values();

            // Minimal milestones list (optional in UI)
            $milestones = $project->milestones()->get(['id', 'name', 'status', 'completion_date as deadline']);

            // Determine current milestone: prefer active with a deadline
            $currentMilestone = $project->milestones()
                ->when(true, function ($q) {
                    // Prefer active-like statuses
                    $q->orderByRaw("CASE LOWER(status)
                        WHEN 'in progress' THEN 0
                        WHEN 'pending' THEN 1
                        WHEN 'not started' THEN 2
                        WHEN 'completed' THEN 3
                        WHEN 'approved' THEN 4
                        ELSE 5 END");
                })
                ->orderByRaw('CASE WHEN completion_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('completion_date', 'asc')
                ->first();

            $current = null;
            if ($currentMilestone) {
                // Task progress for this milestone
                $totalTasks = Task::where('milestone_id', $currentMilestone->id)->count();
                $doneTasks = Task::where('milestone_id', $currentMilestone->id)->where('status', Task::STATUS_DONE)->count();
                $progress = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;

                // Budget and approved amounts
                $budget = $currentMilestone->budget; // morphOne: may be null
                $approvedSum = $currentMilestone->expendable()
                    ->where('status', 'Accepted')
                    ->get(['amount', 'currency'])
                    ->reduce(function ($carry, $e) {
                        // Return raw numbers; frontend can format
                        $amt = (float) ($e->amount ?? 0);
                        $carry += $amt; // note: mixed currencies possible; kept simple here
                        return $carry;
                    }, 0.0);

                $budgetAmount = $budget?->amount ? (float) $budget->amount : null;
                $budgetCurrency = $budget?->currency;
                $remaining = ($budgetAmount !== null) ? max(0, $budgetAmount - $approvedSum) : null;

                $current = [
                    'id' => $currentMilestone->id,
                    'name' => $currentMilestone->name,
                    'status' => $currentMilestone->status,
                    'deadline' => optional($currentMilestone->completion_date)->toDateString(),
                    'progress_percent' => $progress,
                    'tasks_total' => $totalTasks,
                    'tasks_done' => $doneTasks,
                    'budget' => [
                        'amount' => $budgetAmount,
                        'currency' => $budgetCurrency,
                        'approved_amount' => $approvedSum,
                        'remaining_amount' => $remaining,
                    ],
                ];
            }

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'project_type' => $project->project_type,
                'role' => $role, // keep actual role for label; visibility already handled
                'milestones' => $milestones,
                'current_milestone' => $current,
                'tasks' => [
                    'today' => $todayTasks,
                    'tomorrow' => $tomorrowTasks,
                ],
                'last_email_sent' => optional($project->last_email_sent)?->toDateTimeString(),
                'last_email_received' => optional($project->last_email_received)?->toDateTimeString(),
                'tags' => $project->tags?->pluck('name') ?? [],
            ];
        });

        // Replace paginator collection with mapped results
        $paginator->setCollection($mapped->values());

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Return completed tasks for a project for the current user context (role-aware).
     */
    public function completedTasks(Project $project, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $isGlobalManager = (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) || (method_exists($user, 'isManager') && $user->isManager());

        // Verify access: global managers ok; otherwise must be attached to project or be project lead
        $isAttached = $user->projects()->where('projects.id', $project->id)->exists();
        $isLead = $project->project_manager_id === $user->id || $project->project_admin_id === $user->id;
        if (!($isGlobalManager || $isAttached || $isLead)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Collect milestone ids
        $milestoneIds = $project->milestones()->pluck('id');

        // Base query for completed tasks
        $query = Task::query()
            ->whereIn('milestone_id', $milestoneIds)
            ->where('status', Task::STATUS_DONE);

        // If not global manager/lead and not attached as manager/admin, restrict to own tasks
        if (!($isGlobalManager || $isLead)) {
            $query->where('assigned_to_user_id', $user->id);
        }

        $tasks = $query->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get(['id', 'name', 'status', 'due_date'])
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'status' => $t->status,
                    'due_date' => optional($t->due_date)->toDateString(),
                ];
            })->values();

        return response()->json($tasks);
    }
}
