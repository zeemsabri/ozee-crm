<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kudo;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Notifications\KudoApprovalRequired;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class KudoController extends Controller
{
    public function index(Request $request)
    {
        // List current user's approved kudos (received)
        $user = $request->user();
        $kudos = Kudo::with(['sender', 'recipient', 'project'])
            ->where('recipient_id', $user->id)
            ->where('is_approved', true)
            ->orderByDesc('id')
            ->get();

        return response()->json($kudos);
    }

    public function mine(Request $request)
    {
        $user = $request->user();
        // Return kudos received by the user (not including soft-deleted), with both approved and pending
        $kudos = Kudo::with(['sender', 'recipient', 'project'])
            ->where('recipient_id', $user->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($kudos);
    }

    public function store(Request $request)
    {
        $user = $request->user();
//        $this->authorize('create', Kudo::class);

        $validated = $request->validate([
            'recipient_id' => 'required|exists:users,id|different:sender_id',
            'project_id' => 'required|exists:projects,id',
            'comment' => 'required|string|min:3',
        ]);

        // ensure sender is current user
        $validated['sender_id'] = $user->id;
        $validated['is_approved'] = false;

        // Optional: make sure recipient is part of project (policy may handle who can leave kudos for who)
        $kudo = Kudo::create($validated);

        // Notify approvers (global approve_kudos or project-based approve_kudos)
        $this->notifyApprovers($kudo);

        return response()->json($kudo->load(['sender', 'recipient', 'project']), 201);
    }

    public function pending(Request $request)
    {
        $user = $request->user();

        // If user has global approve_kudos, show all pending
        if ($user->hasPermission('approve_kudos')) {
            $kudos = Kudo::with(['sender', 'recipient', 'project'])
                ->where('is_approved', false)
                ->orderByDesc('id')
                ->get();
            return response()->json($kudos);
        }

        // Else fetch pending kudos in projects where user has project-level approve_kudos
        $projectIds = $this->getProjectsWhereUserHasPermission($user, 'approve_kudos');

        $kudos = Kudo::with(['sender', 'recipient', 'project'])
            ->where('is_approved', false)
            ->whereIn('project_id', $projectIds)
            ->orderByDesc('id')
            ->get();

        return response()->json($kudos);
    }

    public function approve(Request $request, Kudo $kudo)
    {
        $this->authorize('approve', $kudo);
        $kudo->is_approved = true;
        $kudo->save();

        return response()->json(['message' => 'Kudo approved successfully.', 'kudo' => $kudo->load(['sender','recipient','project'])]);
    }

    public function reject(Request $request, Kudo $kudo)
    {
        $this->authorize('approve', $kudo);
        // For MVP, soft delete on reject to keep a record
        $kudo->delete();
        return response()->json(['message' => 'Kudo rejected.']);
    }

    private function notifyApprovers(Kudo $kudo): void
    {
        // Global approvers
        $globalApprovers = User::whereHas('role.permissions', function ($q) {
            $q->where('slug', 'approve_kudos');
        })->get();

        // Project-specific approvers
        $projectApprovers = collect();
        if ($kudo->project_id) {
            $project = Project::with(['users' => function ($q) {
                $q->withPivot('role_id');
            }])->find($kudo->project_id);

            if ($project) {
                $roleIds = $project->users->pluck('pivot.role_id')->unique()->filter();
                if ($roleIds->isNotEmpty()) {
                    $roles = Role::with('permissions')->whereIn('id', $roleIds)->get();
                    $userIdsWithApprove = $roles->filter(function ($role) {
                        return $role->permissions->contains('slug', 'approve_kudos');
                    });

                    // Map back users in project whose pivot role_id is in those roles
                    $projectApprovers = $project->users->filter(function ($u) use ($userIdsWithApprove) {
                        return $userIdsWithApprove->pluck('id')->contains($u->pivot->role_id);
                    });
                }
            }
        }

        $recipients = $globalApprovers->merge($projectApprovers)->unique('id')->filter(function ($u) use ($kudo) {
            // don't notify the sender
            return $u->id !== $kudo->sender_id;
        });

        Notification::send($recipients, new KudoApprovalRequired($kudo));
    }

    private function getProjectsWhereUserHasPermission(User $user, string $permissionSlug)
    {

        // Fallback approach: We already store role_id on project_user pivot
        $projects = Project::with(['users' => function ($q) use ($user) {
            $q->where('users.id', $user->id)->withPivot('role_id');
        }])->get();

        $projectIds = [];
        foreach ($projects as $project) {
            $userInProject = $project->users->first();
            if (!$userInProject) continue;
            $role = Role::with('permissions')->find($userInProject->pivot->role_id);
            if ($role && $role->permissions->contains('slug', $permissionSlug)) {
                $projectIds[] = $project->id;
            }
        }

        return $projectIds;
    }
}
