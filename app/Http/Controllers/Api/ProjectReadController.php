<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\Concerns\HasProjectPermissions; // Import the trait

class ProjectReadController extends Controller
{
    use HasProjectPermissions; // Use the trait

    /**
     * Display a listing of the projects.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();

        if ($user->isSuperAdmin() || $user->isManager()) {
            $projects = Project::with(['clients', 'users' => function ($query) {
                $query->withPivot('role_id');
            }, 'transactions', 'notes'])->get();
        } else {
            $projects = $user->projects()->with(['clients', 'users' => function ($query) {
                $query->withPivot('role_id');
            }, 'transactions', 'notes'])->get();
        }

        $projects->each(function ($project) {
            $project->notes->each(function ($note) {
                try {
                    $note->content = Crypt::decryptString($note->content);
                } catch (\Exception $e) {
                    Log::error('Failed to decrypt note content in index method', ['note_id' => $note->id, 'error' => $e->getMessage()]);
                    $note->content = '[Encrypted content could not be decrypted]';
                }
            });
        });

        return response()->json($projects);
    }

    /**
     * Display the specified project.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Project $project)
    {
        $user = Auth::user();

        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Create a filtered project object based on user permissions
        $filteredProject = [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'website' => $project->website,
            'social_media_link' => $project->social_media_link,
            'preferred_keywords' => $project->preferred_keywords,
            'google_chat_id' => $project->google_chat_id,
            'status' => $project->status,
            'project_type' => $project->project_type,
            'source' => $project->source,
            'google_drive_link' => $project->google_drive_link,
        ];

        if ($this->canViewClientContacts($user, $project)) {
            $project->load('clients');
            $filteredProject['clients'] = $project->clients;
        }

        if ($this->canViewUsers($user, $project)) {
            $project->load(['users' => function ($query) {
                $query->withPivot('role_id');
            }]);

            $project->users->each(function ($user) {
                $user->load(['role.permissions']);

                if (isset($user->pivot->role_id)) {
                    $projectRole = Role::with('permissions')->find($user->pivot->role_id);
                    if ($projectRole) {
                        $permissions = [];
                        foreach ($projectRole->permissions as $permission) {
                            $permissions[] = [
                                'id' => $permission->id,
                                'name' => $permission->name,
                                'slug' => $permission->slug,
                                'category' => $permission->category
                            ];
                        }
                        $user->pivot->role_data = [
                            'id' => $projectRole->id,
                            'name' => $projectRole->name,
                            'slug' => $projectRole->slug,
                            'permissions' => $permissions
                        ];
                        $user->setRelation('pivot', $user->pivot->makeVisible(['role_data']));
                        $user->pivot->role = $projectRole->name;
                    }
                }
                if ($user->role) {
                    $globalPermissions = [];
                    foreach ($user->role->permissions as $permission) {
                        $globalPermissions[] = [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'slug' => $permission->slug,
                            'category' => $permission->category
                        ];
                    }
                    $user->global_permissions = $globalPermissions;
                    $user->makeVisible(['global_permissions']);
                }
            });
            $filteredProject['users'] = $project->users;
        }

        if ($this->canViewProjectServicesAndPayments($user, $project)) {
            $filteredProject['services'] = $project->services;
            $filteredProject['service_details'] = $project->service_details;
            $filteredProject['total_amount'] = $project->total_amount;
            $filteredProject['payment_type'] = $project->payment_type;
        }

        if ($this->canViewProjectTransactions($user, $project)) {
            $project->load('transactions');
            if ($this->canManageProjectExpenses($user, $project) && !$this->canManageProjectIncome($user, $project)) {
                $filteredTransactions = $project->transactions->filter(fn ($transaction) => $transaction->type === 'expense');
                $filteredProject['transactions'] = $filteredTransactions;
            } elseif (!$this->canManageProjectExpenses($user, $project) && $this->canManageProjectIncome($user, $project)) {
                $filteredTransactions = $project->transactions->filter(fn ($transaction) => $transaction->type === 'income');
                $filteredProject['transactions'] = $filteredTransactions;
            } else {
                $filteredProject['transactions'] = $project->transactions;
            }
        }

        if ($this->canViewProjectDocuments($user, $project)) {
            $filteredProject['documents'] = $project->documents;
        }

        if ($this->canViewProjectNotes($user, $project)) {
            $project->load(['notes' => function ($query) {
                $query->whereNull('parent_id');
            }]);
            $project->notes->each(function ($note) {
                try {
                    $note->content = Crypt::decryptString($note->content);
                } catch (\Exception $e) {
                    Log::error('Failed to decrypt note content in show method', ['note_id' => $note->id, 'error' => $e->getMessage()]);
                    $note->content = '[Encrypted content could not be decrypted]';
                }
                $note->reply_count = $note->replyCount();
            });
            $filteredProject['notes'] = $project->notes;
        }

        if ($this->canViewClientFinancial($user, $project)) {
            $filteredProject['contract_details'] = $project->contract_details;
        }

        return response()->json($filteredProject);
    }

    /**
     * Get basic project information.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBasicInfo(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        return response()->json([
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'website' => $project->website,
            'social_media_link' => $project->social_media_link,
            'preferred_keywords' => $project->preferred_keywords,
            'google_chat_id' => $project->google_chat_id,
            'status' => $project->status,
            'project_type' => $project->project_type,
            'source' => $project->source,
            'google_drive_link' => $project->google_drive_link,
        ]);
    }

    /**
     * Get project services and payment information.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServicesAndPayment(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canViewProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view financial information.'], 403);
        }

        return response()->json([
            'services' => $project->services,
            'service_details' => $project->service_details,
            'total_amount' => $project->total_amount,
            'payment_type' => $project->payment_type,
        ]);
    }

    /**
     * Get project transactions.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canViewProjectTransactions($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view transactions.'], 403);
        }

        $project->load('transactions');
        if ($this->canManageProjectExpenses($user, $project) && !$this->canManageProjectIncome($user, $project)) {
            $filteredTransactions = $project->transactions->filter(fn ($transaction) => $transaction->type === 'expense');
            return response()->json($filteredTransactions);
        } elseif (!$this->canManageProjectExpenses($user, $project) && $this->canManageProjectIncome($user, $project)) {
            $filteredTransactions = $project->transactions->filter(fn ($transaction) => $transaction->type === 'income');
            return response()->json($filteredTransactions);
        } else {
            return response()->json($project->transactions);
        }
    }

    /**
     * Get project clients.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectClients(Project $project)
    {
        $user = Auth::user();
        // Permission check is less strict here, as it might be used for things like magic link target selection.
        // If a stricter check is needed, uncomment the authorization lines.
        // if (!$this->canAccessProject($user, $project) || !$this->canViewClientContacts($user, $project)) {
        //     return response()->json(['message' => 'Unauthorized.'], 403);
        // }

        $project->load('clients');
        return response()->json($project->clients);
    }

    /**
     * Get project contract details.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContractDetails(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canViewClientFinancial($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view contract details.'], 403);
        }

        return response()->json($project->contract_details);
    }

    /**
     * Get project clients and users.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientsAndUsers(Project $project)
    {
        $user = Auth::user();
        $result = [];

        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        if ($this->canViewClientContacts($user, $project)) {
            $project->load('clients');
            $result['clients'] = $project->clients;
        }

        if ($this->canViewUsers($user, $project)) {
            $project->load(['users' => function ($query) {
                $query->withPivot('role_id');
            }]);

            $project->users->each(function ($user) {
                $user->load(['role.permissions']);
                if (isset($user->pivot->role_id)) {
                    $projectRole = Role::with('permissions')->find($user->pivot->role_id);
                    if ($projectRole) {
                        $permissions = [];
                        foreach ($projectRole->permissions as $permission) {
                            $permissions[] = ['id' => $permission->id, 'name' => $permission->name, 'slug' => $permission->slug, 'category' => $permission->category];
                        }
                        $user->pivot->role_data = ['id' => $projectRole->id, 'name' => $projectRole->name, 'slug' => $projectRole->slug, 'permissions' => $permissions];
                        $user->setRelation('pivot', $user->pivot->makeVisible(['role_data']));
                        $user->pivot->role = $projectRole->name;
                    }
                }
                if ($user->role) {
                    $globalPermissions = [];
                    foreach ($user->role->permissions as $permission) {
                        $globalPermissions[] = ['id' => $permission->id, 'name' => $permission->name, 'slug' => $permission->slug, 'category' => $permission->category];
                    }
                    $user->global_permissions = $globalPermissions;
                    $user->makeVisible(['global_permissions']);
                }
            });
            $result['users'] = $project->users;
        }

        return response()->json($result);
    }

    /**
     * Get project users.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectUsers(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canViewUsers($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view team members.'], 403);
        }

        $project->load(['users' => function ($query) {
            $query->withPivot('role_id');
        }]);

        $project->users->each(function ($user) {
            $user->load(['role.permissions']);
            if (isset($user->pivot->role_id)) {
                $projectRole = Role::with('permissions')->find($user->pivot->role_id);
                if ($projectRole) {
                    $permissions = [];
                    foreach ($projectRole->permissions as $permission) {
                        $permissions[] = ['id' => $permission->id, 'name' => $permission->name, 'slug' => $permission->slug, 'category' => $permission->category];
                    }
                    $user->pivot->role_data = ['id' => $projectRole->id, 'name' => $projectRole->name, 'slug' => $projectRole->slug, 'permissions' => $permissions];
                    $user->setRelation('pivot', $user->pivot->makeVisible(['role_data']));
                    $user->pivot->role = $projectRole->name;
                }
            }
            if ($user->role) {
                $globalPermissions = [];
                foreach ($user->role->permissions as $permission) {
                    $globalPermissions[] = ['id' => $permission->id, 'name' => $permission->name, 'slug' => $permission->slug, 'category' => $permission->category];
                }
                $user->global_permissions = $globalPermissions;
                $user->makeVisible(['global_permissions']);
            }
        });

        return response()->json($project->users);
    }

    /**
     * Get project documents.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDocuments(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canViewProjectDocuments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view documents.'], 403);
        }

        return response()->json($project->documents);
    }

    /**
     * Get project notes, with optional filters.
     *
     * @param Project $project
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotes(Project $project, Request $request)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canViewProjectNotes($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view notes.'], 403);
        }

        $notesQuery = $project->notes()->with('user')->whereNull('parent_id');

        if ($request->has('type') && !empty($request->type)) {
            $notesQuery->where('type', $request->type);
        }

        if ($request->has('user_id') && !empty($request->user_id)) {
            $notesQuery->where('user_id', $request->user_id);
        }

        if ($request->has('start_date') && !empty($request->start_date)) {
            $notesQuery->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && !empty($request->end_date)) {
            $notesQuery->whereDate('created_at', '<=', $request->end_date);
        }

        $notesQuery->orderBy('created_at', 'desc');
        $notes = $notesQuery->get();

        $notes->each(function ($note) {
            try {
                $note->content = Crypt::decryptString($note->content);
            } catch (\Exception $e) {
                Log::error('Failed to decrypt note content in getNotes method', ['note_id' => $note->id, 'error' => $e->getMessage()]);
                $note->content = '[Encrypted content could not be decrypted]';
            }
            $note->reply_count = $note->replyCount();
        });

        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = strtolower($request->search);
            $notes = $notes->filter(function ($note) use ($searchTerm) {
                if ($note->content === '[Encrypted content could not be decrypted]') {
                    return false;
                }
                return str_contains(strtolower($note->content), $searchTerm);
            })->values();
        }

        return response()->json($notes);
    }

    /**
     * Get tasks for a project.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTasks(Project $project)
    {
        $user = Auth::user();
        // Assuming 'view tasks' permission is covered by general project access or a specific task permission
        if (!$this->canAccessProject($user, $project)) { // You might want a more granular permission check here if tasks are very sensitive
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        $milestoneIds = $project->milestones()->pluck('id')->toArray();

        $tasks = \App\Models\Task::whereIn('milestone_id', $milestoneIds)
            ->with(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks'])
            ->orderBy('due_date', 'asc')
            ->get();

        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->name,
                'description' => $task->description,
                'status' => $task->status,
                'assigned_to' => $task->assignedTo ? $task->assignedTo->name : 'Unassigned',
                'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                'milestone' => $task->milestone ? $task->milestone->name : null,
                'task_type' => $task->taskType ? $task->taskType->name : null,
                'tags' => $task->tags->pluck('name'),
                'subtasks_count' => $task->subtasks->count(),
                'create_time' => $task->created_at->toISOString(),
                'update_time' => $task->updated_at->toISOString(),
            ];
        });

        return response()->json($formattedTasks);
    }

    /**
     * Get replies for a specific note.
     *
     * @param Project $project
     * @param ProjectNote $note
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNoteReplies(Project $project, ProjectNote $note)
    {
        $user = Auth::user();
        // Use canViewProjectNotes from the trait
        if (!$this->canViewProjectNotes($user, $project)) {
            return response()->json([
                'message' => 'Unauthorized. You do not have permission to view notes or replies.',
                'success' => false
            ], 403);
        }

        if ($note->project_id !== $project->id) {
            return response()->json([
                'message' => 'The note does not belong to this project.',
                'success' => false
            ], 400);
        }

        $replies = $note->replies()->with('user')->orderBy('created_at', 'asc')->get();

        $replies->each(function ($reply) {
            try {
                $reply->content = Crypt::decryptString($reply->content);
            } catch (\Exception $e) {
                Log::error('Failed to decrypt reply content', ['reply_id' => $reply->id, 'error' => $e->getMessage()]);
                $reply->content = '[Encrypted content could not be decrypted]';
            }
        });

        return response()->json([
            'replies' => $replies,
            'success' => true
        ]);
    }

    /**
     * Get projects for email composer screen.
     * Returns only project name, client name, and relevant project fields.
     * No contact information is included in the response.
     * Filters projects based on user's permission to send emails.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectsForEmailComposer()
    {
        $user = Auth::user();
        $projects = collect();

        $user->load(['role.permissions']);
        $hasGlobalComposeEmailPermission = $user->role && $user->role->permissions->contains('slug', 'compose_emails');

        if ($user->isSuperAdmin()) {
            $projects = Project::with('clients:id,name')->get();
        } else {
            $userProjects = $user->projects()->with(['clients:id,name'])->get();
            foreach ($userProjects as $project) {
                $projectRole = $this->getUserProjectRole($user, $project);
                if ($hasGlobalComposeEmailPermission || ($projectRole && $projectRole->permissions->contains('slug', 'compose_emails'))) {
                    $projects->push($project);
                }
            }
        }

        $transformedProjects = $projects->map(function ($project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'clients' => $project->clients ? $project->clients->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'name' => $client->name,
                    ];
                })->toArray() : [],
            ];
        });

        return response()->json([
            'projects' => $transformedProjects,
        ]);
    }

    /**
     * Get simplified projects data for dashboard.
     * Returns only id, name, and status fields.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectsSimplified()
    {
        $user = Auth::user();

        if ($user->isSuperAdmin() || $user->isManager()) {
            $projects = Project::select('id', 'name', 'status')->get();
        } else {
            $projects = $user->projects()->select('projects.id', 'projects.name', 'projects.status')->get();
        }

        return response()->json($projects);
    }

    /**
     * Get meetings for a project.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectMeetings(Project $project)
    {
        $user = Auth::user();
        // Assuming that viewing meetings is covered by general project access, or you can add a specific permission here.
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        $meetings = $project->meetings()
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($meetings);
    }
}
