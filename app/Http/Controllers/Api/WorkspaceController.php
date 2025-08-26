<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
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

        // Mirror logic from ProjectReadController@getProjectsSimplified
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() || (method_exists($user, 'isManager') && $user->isManager())) {
            $projects = Project::select('id', 'name', 'status', 'project_manager_id', 'project_admin_id', 'project_type')
                ->with('tags')
                ->get();
        } else {
            $projects = $user->projects()
                ->with('milestones.tasks')
                ->select('projects.id', 'projects.name', 'projects.status', 'projects.project_manager_id', 'projects.project_admin_id', 'projects.project_type')
                ->with('tags')
                ->get();
        }

        $result = $projects->map(function (Project $project) use ($user) {
            $role = 'doer';
            if ($project->project_manager_id === $user->id) {
                $role = 'manager';
            } elseif ($project->project_admin_id === $user->id) {
                $role = 'admin';
            }

            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'project_type' => $project->project_type,
                'role' => $role,
                'milestones' =>  $project->milestones,
                'tags' => $project->tags?->pluck('name') ?? [],
            ];
        });

        return response()->json($result->values());
    }
}
