<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Services\ValueSetValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExternalApiController extends Controller
{
    /**
     * Fetch all projects assigned to the user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjects()
    {
        $user = Auth::user();

        // Get all roles to avoid multiple database queries
        $roles = Role::pluck('name', 'id')->toArray();

        if ($user->hasPermission('view_all_projects')) {

            // For super admins and managers, get all projects
            $projects = Project::select('projects.id', 'projects.name', 'projects.status', 'projects.departments', 'projects.project_type')
                ->leftJoin('project_user', function ($join) use ($user) {
                    $join->on('projects.id', '=', 'project_user.project_id')
                        ->where('project_user.user_id', '=', $user->id);
                })
                ->addSelect('project_user.role_id')
                ->orderBy('projects.name')
                ->get();
        } else {
            // For regular users, get only their projects
            $projects = $user->projects()
                ->select('projects.id', 'projects.name', 'projects.status', 'project_user.role_id', 'projects.departments', 'projects.project_type')
                ->orderBy('projects.name')
                ->get();
        }

        $transformedProjects = $projects->map(function ($project) use ($roles) {
            // Get the role name from the roles array using the role_id
            $roleName = null;
            if (isset($project->role_id) && isset($roles[$project->role_id])) {
                $roleName = $roles[$project->role_id];
            }

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'user_role' => $roleName,
                'tags' => $project->tags->pluck('name'),
                'project_type' => $project->project_type,
            ];
        });

        return response()->json($transformedProjects);
    }

    /**
     * Fetch all tasks in a project.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectTasks(Project $project)
    {
        $user = Auth::user();

        // Authorization check
        if (!$user->isSuperAdmin() && !$user->isManager() && !$user->projects->contains($project->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $milestoneIds = $project->milestones()->pluck('id')->toArray();
        $tasks = Task::whereIn('milestone_id', $milestoneIds)
            ->with(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks'])
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json($tasks);
    }

    /**
     * Update status on a task (start, pause, resume, complete, block, unblock, revise, archive).
     *
     * @param Request $request
     * @param Task $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTaskStatus(Request $request, Task $task)
    {
        $user = Auth::user();

        // Authorization check (via project)
        $project = $task->milestone->project;
        if (!$user->isSuperAdmin() && !$user->isManager() && !$user->projects->contains($project->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:start,pause,resume,complete,stop,block,unblock,revise,archive',
            'reason' => 'required_if:status,block|max:255',
        ]);

        $statusAction = $validated['status'];
        $currentStatus = $task->status instanceof TaskStatus ? $task->status : TaskStatus::tryFrom((string) $task->status);
        $validator = app(ValueSetValidator::class);

        switch ($statusAction) {
            case 'start':
                if ($currentStatus === TaskStatus::ToDo) {
                    $validator->validate('Task', 'status', TaskStatus::InProgress->value);
                    $task->start($user);
                } elseif ($currentStatus === TaskStatus::Paused) {
                    $validator->validate('Task', 'status', TaskStatus::InProgress->value);
                    $task->status = TaskStatus::InProgress;
                    $task->save();
                } else {
                    return response()->json([
                        'message' => 'Task can only be started from "To Do" or "Paused" status.',
                        'status' => 'error'
                    ], 422);
                }
                break;

            case 'pause':
                if ($currentStatus !== TaskStatus::InProgress) {
                    return response()->json([
                        'message' => 'Only tasks in progress can be paused',
                        'status' => 'error'
                    ], 422);
                }
                $validator->validate('Task', 'status', TaskStatus::Paused->value);
                $task->status = TaskStatus::Paused;
                $task->save();
                break;

            case 'resume':
                if (!in_array($currentStatus, [TaskStatus::Paused, TaskStatus::Blocked])) {
                    return response()->json([
                        'message' => 'Only paused or blocked tasks can be resumed',
                        'status' => 'error'
                    ], 422);
                }
                $validator->validate('Task', 'status', TaskStatus::InProgress->value);
                $task->status = TaskStatus::InProgress;
                $task->save();
                break;

            case 'complete':
            case 'stop':
                if ($currentStatus !== TaskStatus::InProgress) {
                    return response()->json([
                        'message' => 'Task must be started (In Progress) before it can be completed',
                        'status' => 'error'
                    ], 422);
                }
                $validator->validate('Task', 'status', TaskStatus::Done->value);
                $task->markAsCompleted($user);
                break;

            case 'block':
                if (in_array($currentStatus, [TaskStatus::Done, TaskStatus::Archived])) {
                    return response()->json([
                        'message' => 'Completed or Archived tasks cannot be blocked',
                        'status' => 'error'
                    ], 422);
                }
                $task->previous_status = $currentStatus->value;
                $validator->validate('Task', 'status', TaskStatus::Blocked->value);
                $task->status = TaskStatus::Blocked;
                $task->block_reason = $validated['reason'];
                $task->save();
                break;

            case 'unblock':
                if ($currentStatus !== TaskStatus::Blocked) {
                    return response()->json([
                        'message' => 'Only blocked tasks can be unblocked',
                        'status' => 'error'
                    ], 422);
                }
                $nextStatus = $task->previous_status ?: TaskStatus::ToDo->value;
                $validator->validate('Task', 'status', $nextStatus);
                $task->status = $nextStatus;
                $task->block_reason = null;
                $task->previous_status = null;
                $task->save();
                break;

            case 'revise':
                if ($currentStatus !== TaskStatus::Done) {
                    return response()->json([
                        'message' => 'Only completed tasks can be revised',
                        'status' => 'error'
                    ], 422);
                }
                $validator->validate('Task', 'status', TaskStatus::ToDo->value);
                $task->status = TaskStatus::ToDo;
                $task->save();
                break;

            case 'archive':
                $validator->validate('Task', 'status', TaskStatus::Archived->value);
                $task->archive();
                break;
        }

        $updatedTask = $task->fresh(['assignedTo', 'taskType', 'milestone.project', 'tags', 'subtasks']);

        // Sync with DailyTask (Today)
        if (in_array($statusAction, ['complete', 'stop', 'revise'])) {
             $dailyStatus = ($statusAction === 'revise') ? \App\Models\DailyTask::STATUS_PENDING : \App\Models\DailyTask::STATUS_COMPLETED;
             \App\Models\DailyTask::where('task_id', $task->id)
                ->where('user_id', Auth::id())
                ->where('date', \Carbon\Carbon::today()->toDateString())
                ->update(['status' => $dailyStatus]);
        }

        return response()->json([
            'message' => 'Task status successfully updated to ' . $task->status->value,
            'task' => $updatedTask
        ]);
    }
}
