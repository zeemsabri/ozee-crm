<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Role;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use Illuminate\Support\Facades\Storage;

// Import the trait

class ProjectReadController extends Controller
{
    use HasProjectPermissions; // Use the trait

    /**
     * Display a listing of the projects.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $withTrashed = $request->has('with_trashed') && $request->with_trashed === 'true';

        if ($user->isSuperAdmin() || $user->isManager()) {
            $query = Project::query();

            // Include trashed (archived) projects if requested
            if ($withTrashed) {
                $query->withTrashed();
            }

            $projects = $query->with(['clients', 'users' => function ($query) {
                $query->withPivot('role_id');
            }, 'transactions', 'notes'])->get();
        } else {
            $query = $user->projects();

            // Include trashed (archived) projects if requested
            if ($withTrashed) {
                $query->withTrashed();
            }

            $projects = $query->with(['clients', 'users' => function ($query) {
                $query->withPivot('role_id');
            }, 'transactions', 'notes'])->get();
        }

//        $projects->each(function ($project) {
//            $project->notes->each(function ($note) {
//                try {
//                    $note->content = $note->content;
//                } catch (\Exception $e) {
//                    Log::error('Failed to decrypt note content in index method', ['note_id' => $note->id, 'error' => $e->getMessage()]);
//                    $note->content = '[Encrypted content could not be decrypted]';
//                }
//            });
//        });

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
                $note->reply_count = $note->replyCount();
            });
            $filteredProject['notes'] = $project->notes;
        }

        if($this->canViewProjectDeliverables($user, $project)) {
            $project->load('projectDeliverables');
            $filteredProject['deliverables'] = $project->deliverables;
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
        if (!$this->canCreateProjects($user)) {
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
            'logo'  => $project->logo,
            'tags_data'  =>  $project->tags->map(function($tag) {
                return ['id' => $tag->id, 'name' => $tag->name];
            })->values()->all(),
            'timezone'  =>  $project->timezone,
            'project_tier_id'   =>  $project->project_tier_id
        ]);
    }

    /**
     * Get project clients
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectClients(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }
        if (!$this->canViewClientContacts($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view client contacts.'], 403);
        }

        $project->load('clients');
        return response()->json($project->clients);
    }

    /**
     * Get project users (team members)
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectUsers(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }
        if (!$this->canViewUsers($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view team members.'], 403);
        }

        $project->load(['users' => function ($query) {
            $query->withPivot('role_id'); // Ensure pivot data (earning, bonus) is loaded
        }]);


        $project->users->each(function ($user) {
            $user->load(['role.permissions']);
            if (isset($user->pivot->role_id)) {
                $projectRole = \App\Models\Role::with('permissions')->find($user->pivot->role_id);
                if ($projectRole) {
                    $permissions = $projectRole->permissions->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'category' => $p->category]);
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
                $globalPermissions = $user->role->permissions->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'category' => $p->category]);
                $user->global_permissions = $globalPermissions;
                $user->makeVisible(['global_permissions']);
            }
        });

        return response()->json($project->users);
    }

    /**
     * Get project services and payment information
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServicesAndPayment(Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }
        if (!$this->canViewProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view financial information.'], 403);
        }

        // Return financial information
        return response()->json([
            'services' => $project->services,
            'service_details' => $project->service_details,
            'total_amount' => $project->total_amount,
            'total_expendable_amount' => $project->total_expendable_amount,
            'currency' => $project->currency,
            'payment_type' => $project->payment_type,
            'contract_details' => $this->canViewClientFinancial($user, $project) ? $project->contract_details : null,
        ]);
    }

    /**
     * Get expendable budget for a project (amount and currency)
     */
    public function getExpendableBudget(Project $project)
    {
        $user = Auth::user();

        $this->authorize('addExpendables', $project);

        return response()->json([
            'total_expendable_amount' => $project->remaining_spendables,
            'total_budget' => $project->total_budget,
            'currency' => 'AUD',
        ]);
    }

    /**
     * Get project transactions
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Project $project, Request $request)
    {
        $user = Auth::user();
        $userId = $request->user_id; // Use request() helper

        if (!$this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }
        // If no specific user_id is requested, and user doesn't have general view permission
        if (!$this->canViewProjectTransactions($user, $project) && !$userId && ($userId && $userId !== Auth::id())) { // Check general permission if not user-specific
            return response()->json(['message' => 'Unauthorized. You do not have permission to view transactions.'], 403);
        }

        // Start building the query for transactions
        $transactionsQuery = $project->transactions()->with(['transactionType', 'user' => function ($query) {
            $query->select('id', 'name');
        }]);

        // Apply user_id filter if present in the request
        if ($userId) {
            $transactionsQuery->where('user_id', $userId) // Filter by the requested user
            ->whereIn('type', ['expense', 'bonus']); // Only include 'expense' or 'bonus' for a specific user's financials
        }


        $transactions = $transactionsQuery->get();

        // Apply filtering based on manage expenses/income permissions (existing logic)
        // This is primarily for project-wide views. For user-specific view, the query above is dominant.
        $filteredTransactions = $transactions->filter(function ($transaction) use ($user, $project, $userId) {
            $canManageExpenses = $this->canManageProjectExpenses($user, $project);
            $canManageIncome = $this->canManageProjectIncome($user, $project);

            // If a specific user ID was requested, ensure the transaction belongs to that user.
            // This is mostly redundant if the query already filtered by user_id, but good for robust filtering.
            if ($userId && $transaction->user_id !== (int) $userId) {
                return false;
            }

            // General project-wide permission filtering:
            // If user can only manage expenses and transaction is income, filter out
            if (!$userId && $transaction->type === 'income' && !$canManageIncome && $canManageExpenses) {
                return false;
            }
            // If user can only manage income and transaction is expense, filter out
            if (!$userId && $transaction->type === 'expense' && !$canManageExpenses && $canManageIncome) {
                return false;
            }

            return true; // View all if both permissions, or filter if only one
        });

        // Return raw, filtered transactions. Frontend will handle conversion and stats.
        return response()->json($filteredTransactions->values()); // Use values() to re-index array
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

        try {
            $this->authorize('addExpendables', $project);
        }
        catch (AuthenticationException $e) {

            if (!$this->canAccessProject($user, $project)) {
                return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
            }

        }

        $type = request()->type;

        if ($this->canViewClients($user, $project) && (!$type || $type === 'clients')) {
            $project->load('clients');
            $result['clients'] = $project->clients;
        }

        if ($this->canViewUsers($user, $project) && (!$type || $type === 'users')) {
            $project->load(['users' => function ($query) {
                $query->withPivot('role_id');
            },
            'users.availabilities' => function($query) {
                $query->where('date', '=', Today());
            }
            ]);

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

        if($type && isset($result[$type])) {
            return response()->json($result[$type]);
        }

        return response()->json($result);
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

        return response()->json($project->documents()->get());
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
                $note->content = $note->content;
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

        // Return the full task objects with relationships instead of formatted data
        return response()->json($tasks);
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

//        $replies->each(function ($reply) {
//            try {
//                $reply->content = Crypt::decryptString($reply->content);
//            } catch (\Exception $e) {
//                Log::error('Failed to decrypt reply content', ['reply_id' => $reply->id, 'error' => $e->getMessage()]);
//                $reply->content = '[Encrypted content could not be decrypted]';
//            }
//        });

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

        // Get all roles to avoid multiple database queries
        $roles = Role::pluck('name', 'id')->toArray();

        if ($user->isSuperAdmin() || $user->isManager()) {
            // For super admins and managers, get all projects
            $projects = Project::select('projects.id', 'projects.name', 'projects.status', 'projects.departments', 'projects.project_type')
                ->leftJoin('project_user', function($join) use ($user) {
                    $join->on('projects.id', '=', 'project_user.project_id')
                         ->where('project_user.user_id', '=', $user->id);
                })
                ->addSelect('project_user.role_id')
                ->get();
        } else {
            // For regular users, get only their projects
            $projects = $user->projects()
                ->select('projects.id', 'projects.name', 'projects.status', 'project_user.role_id', 'projects.departments', 'projects.project_type')
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
                'tags'   =>  $project->tags->pluck('name'),
                'project_type'  =>  $project->project_type
            ];
        });

        return response()->json($transformedProjects);
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
        $now = NOW()->addHour();
        $meetings = $project->meetings()
            ->where('start_time', '>', $now)
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($meetings);
    }

    /**
     * Get meetings that the authenticated user is invited to.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserMeetings()
    {
        $user = Auth::user();
        $now = NOW();

        // Get meetings where the user is an attendee
        $meetings = $user->meetings()
            ->with(['project:id,name', 'creator:id,name'])
            ->where('start_time', '>', $now)
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($meetings);
    }

    /**
     * Get standups for the authenticated user for today.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserStandups()
    {
        $user = Auth::user();
        $today = now()->format('Y-m-d');

        // Get all standups for the authenticated user created today
        $standups = ProjectNote::where('user_id', $user->id)
            ->where('type', 'standup')
            ->whereDate('created_at', $today)
            ->get();

        return response()->json($standups);
    }
}
