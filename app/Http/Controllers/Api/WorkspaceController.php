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

        // Mirror logic from ProjectReadController@getProjectsSimplified
        if ($isGlobalManager) {
            $projects = Project::select('id', 'name', 'status', 'project_manager_id', 'project_admin_id', 'project_type')
                ->with('tags')
                ->get();
        } else {
            $projects = $user->projects()
                ->select('projects.id', 'projects.name', 'projects.status', 'projects.project_manager_id', 'projects.project_admin_id', 'projects.project_type')
                ->with('tags')
                ->get();
        }

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();

        $result = $projects->map(function (Project $project) use ($user, $today, $tomorrow, $isGlobalManager) {
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

            // Minimal milestones info (optional in UI)
            $milestones = $project->milestones()->get(['id', 'name', 'status', 'completion_date']);

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'project_type' => $project->project_type,
                'role' => $role, // keep actual role for label; visibility already handled
                'milestones' => $milestones,
                'tasks' => [
                    'today' => $todayTasks,
                    'tomorrow' => $tomorrowTasks,
                ],
                'tags' => $project->tags?->pluck('name') ?? [],
            ];
        });

        return response()->json($result->values());
    }
}
