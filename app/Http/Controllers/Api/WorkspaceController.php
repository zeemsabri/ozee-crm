<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\Context;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            $query = Project::select('projects.id', 'projects.name', 'projects.status', 'projects.project_manager_id', 'projects.project_admin_id', 'projects.project_type', 'projects.last_email_sent', 'projects.last_email_received')
                ->from('projects')
                ->with('tags')
                ->orderBy('projects.id', 'desc');
        } else {
            $query = $user->projects()
                ->select('projects.id', 'projects.name', 'projects.status', 'projects.project_manager_id', 'projects.project_admin_id', 'projects.project_type', 'projects.last_email_sent', 'projects.last_email_received')
                ->with('tags')
                ->orderBy('projects.id', 'desc');
        }

        // Apply role-based filter if provided
        $filter = strtolower((string) $request->get('filter', 'all'));
        if ($filter === 'manager') {
            // Projects where current user is project manager or admin
            $query->where(function ($q) use ($user) {
                $q->where('projects.project_manager_id', $user->id)
                  ->orWhere('projects.project_admin_id', $user->id);
            });
        } elseif ($filter === 'contributor' || $filter === 'my') {
            // Projects where user is a member but not manager/admin
            if ($isGlobalManager) {
                // Scope to user's membership explicitly for global managers
                $query->join('project_user as pu', function ($join) use ($user) {
                    $join->on('pu.project_id', '=', 'projects.id')
                         ->where('pu.user_id', '=', $user->id);
                });
            }
            // Exclude those where the user is manager/admin
            $query->where(function ($q) use ($user) {
                $q->where(function ($qq) use ($user) {
                    $qq->where('projects.project_manager_id', '!=', $user->id)
                       ->orWhereNull('projects.project_manager_id');
                })->where(function ($qq) use ($user) {
                    $qq->where('projects.project_admin_id', '!=', $user->id)
                       ->orWhereNull('projects.project_admin_id');
                });
            });
        }

        // Apply search filter if provided
        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('projects.name', 'LIKE', "%{$search}%")
                  ->orWhere('projects.status', 'LIKE', "%{$search}%")
                  ->orWhere('projects.project_type', 'LIKE', "%{$search}%")
                  ->orWhereHas('tags', function ($t) use ($search) {
                      $t->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Pending tasks filter (overall filter): with|without; default 'with'
        $pending = strtolower((string) $request->get('pending', 'with'));
        $pending = in_array($pending, ['with', 'without', 'all']) ? $pending : 'with';
        if ($pending !== 'all') {
            $sub = function ($q) {
                $q->select(DB::raw(1))
                    ->from('milestones')
                    ->join('tasks', 'tasks.milestone_id', '=', 'milestones.id')
                    ->whereColumn('milestones.project_id', 'projects.id')
                    ->where('tasks.status', '!=', Task::STATUS_DONE);
            };
            if ($pending === 'with') {
                $query->whereExists($sub);
            } elseif ($pending === 'without') {
                $query->whereNotExists($sub);
            }
        }

        // Avoid duplicate rows when joins are applied
        $query->distinct();
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $projects = $paginator->getCollection();

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $mapped = $projects->map(function (Project $project) use ($user, $today, $tomorrow, $isGlobalManager) {
            // Derive role
            $role = 'doer';
            if ($project->project_manager_id === $user->id || $user->id === 1) {
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

            // Contexts: latest and latest 5 for communication
            $latest = $project->contexts()->latest()->first();
            $latestFive = $project->contexts()
                ->latest()
                ->with(['user'])
                ->limit(5)
                ->get();

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
                'latest_context' => $latest ? [
                    'summary' => $latest->summary,
                    'created_at' => optional($latest->created_at)?->toDateTimeString(),
                ] : null,
                'contexts' => $latestFive->map(function ($c) {
                    return [
                        'summary' => $c->summary,
                        'created_at' => optional($c->created_at)?->toDateTimeString(),
                        'user' => $c->user?->name,
                        'source_type' => class_basename($c->referencable_type ?? ''),
                    ];
                })->values(),
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
