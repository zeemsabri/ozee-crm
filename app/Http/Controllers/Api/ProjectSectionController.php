<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\GoogleChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProjectSectionController extends Controller
{
    protected $googleChatService;

    public function __construct(GoogleChatService $googleChatService, ProjectController $projectController)
    {
        $this->googleChatService = $googleChatService;
        $this->projectController = $projectController;
    }

    /**
     * Get basic project information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBasicInfo(Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Return basic project information
        return response()->json([
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'website' => $project->website,
            'social_media_link' => $project->social_media_link,
            'preferred_keywords' => $project->preferred_keywords,
            'reporting_sites' => $project->reporting_sites,
            'google_chat_id' => $project->google_chat_id,
            'status' => $project->status,
            'project_type' => $project->project_type,
            'source' => $project->source,
            'google_drive_link' => $project->google_drive_link,
        ]);
    }

    public function updateBasicInfo(Request $request, Project $project)
    {
        try {
            $user = Auth::user();

            // Check if user has permission to manage the project
            if (! $this->projectController->canManageProjects($user, $project)) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to update this project.'], 403);
            }

            // Determine if the request is JSON or FormData
            $isJsonRequest = $request->isJson() || $request->header('Content-Type') === 'application/json';

            // Adjust validation rules based on request type and user permissions
            $validationRules = [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'website' => 'nullable|url',
                'preferred_keywords' => 'nullable|string',
                'reporting_sites' => 'nullable|string',
                'google_chat_id' => 'nullable|string|max:255',
                'status' => 'sometimes|required|string',
                'source' => 'nullable|string|max:255',
                'google_drive_link' => 'nullable|url',
            ];

            $validated = $request->validate($validationRules);
            if (array_key_exists('status', $validated)) {
                app(\App\Services\ValueSetValidator::class)->validate('Project', 'status', $validated['status']);
            }

            // Initialize project data with basic information
            $projectData = [
                'name' => $validated['name'] ?? $project->name,
                'description' => $validated['description'] ?? $project->description,
                'website' => $validated['website'] ?? $project->website,
                'preferred_keywords' => $validated['preferred_keywords'] ?? $project->preferred_keywords,
                'reporting_sites' => $validated['reporting_sites'] ?? $project->reporting_sites,
                'google_chat_id' => $validated['google_chat_id'] ?? $project->google_chat_id,
                'status' => $validated['status'] ?? $project->status,
                'source' => $validated['source'] ?? $project->source,
                'google_drive_link' => $validated['google_drive_link'] ?? $project->google_drive_link,
            ];

            $project->update($projectData);

            return response()->json($project);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating project: '.$e->getMessage(), ['project_id' => $project->id, 'request' => $request->all(), 'error' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Failed to update project', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get project services and payment information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServicesAndPayment(Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has permission to view project services and payments
        if (! $this->canViewProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view financial information.'], 403);
        }

        // Return financial information
        return response()->json([
            'services' => $project->services,
            'service_details' => $project->service_details,
            'total_amount' => $project->total_amount,
            'payment_type' => $project->payment_type,
        ]);
    }

    /**
     * Update project services and payment information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateServicesAndPayment(Request $request, Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has permission to manage project services and payments
        if (! $this->canManageProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage financial information.'], 403);
        }

        // Validate the request data
        $validatedData = $request->validate([
            'services' => 'nullable|array',
            'service_details' => 'nullable|array',
            'total_amount' => 'nullable|numeric',
            'payment_type' => 'nullable|string|in:one_off,monthly',
        ]);

        // Update the project with the validated data
        $project->services = $validatedData['services'] ?? $project->services;
        $project->service_details = $validatedData['service_details'] ?? $project->service_details;
        $project->total_amount = $validatedData['total_amount'] ?? $project->total_amount;
        $project->payment_type = $validatedData['payment_type'] ?? $project->payment_type;
        $project->save();

        // Return the updated project data
        return response()->json([
            'message' => 'Services and payment information updated successfully',
            'services' => $project->services,
            'service_details' => $project->service_details,
            'total_amount' => $project->total_amount,
            'payment_type' => $project->payment_type,
        ]);
    }

    /**
     * Get project transactions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has permission to view project transactions
        if (! $this->canViewProjectTransactions($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view transactions.'], 403);
        }

        $project->load('transactions');

        // If user can only view expenses or only income, filter accordingly
        if ($this->canManageProjectExpenses($user, $project) && ! $this->canManageProjectIncome($user, $project)) {
            $filteredTransactions = $project->transactions->filter(function ($transaction) {
                return $transaction->type === 'expense';
            });

            return response()->json($filteredTransactions);
        } elseif (! $this->canManageProjectExpenses($user, $project) && $this->canManageProjectIncome($user, $project)) {
            $filteredTransactions = $project->transactions->filter(function ($transaction) {
                return $transaction->type === 'income';
            });

            return response()->json($filteredTransactions);
        } else {
            return response()->json($project->transactions);
        }
    }

    /**
     * Update project transactions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTransactions(Request $request, Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has permission to manage project expenses or income
        if (! $this->canManageProjectExpenses($user, $project) && ! $this->canManageProjectIncome($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage transactions.'], 403);
        }

        // Validate the request data
        $validatedData = $request->validate([
            'transactions' => 'required|array',
            'transactions.*.description' => 'required|string',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.currency' => 'nullable|string',
            'transactions.*.user_id' => 'nullable|exists:users,id',
            'transactions.*.hours_spent' => 'nullable|numeric',
            'transactions.*.type' => 'required|in:expense,income',
        ]);

        // Get the transactions from the validated data
        $transactions = $validatedData['transactions'];

        // Filter transactions based on user permissions
        if (! $this->canManageProjectExpenses($user, $project)) {
            $transactions = array_filter($transactions, function ($transaction) {
                return $transaction['type'] !== 'expense';
            });
        }
        if (! $this->canManageProjectIncome($user, $project)) {
            $transactions = array_filter($transactions, function ($transaction) {
                return $transaction['type'] !== 'income';
            });
        }

        // Delete existing transactions
        $project->transactions()->delete();

        // Create new transactions
        foreach ($transactions as $transaction) {
            $project->transactions()->create([
                'description' => $transaction['description'],
                'amount' => $transaction['amount'],
                'user_id' => $transaction['user_id'] ?? null,
                'hours_spent' => $transaction['hours_spent'] ?? null,
                'type' => $transaction['type'],
                // Include currency if it's in the transaction data
                'currency' => $transaction['currency'] ?? null,
            ]);
        }

        // Load the updated transactions
        $project->load('transactions');

        // Return the updated transactions
        return response()->json([
            'message' => 'Transactions updated successfully',
            'transactions' => $project->transactions,
        ]);
    }

    /**
     * Get project clients
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectClients(Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        //        if (!$this->canAccessProject($user, $project)) {
        //            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        //        }
        //
        //        // Check if user has permission to view client contacts
        //        if (!$this->canViewClientContacts($user, $project)) {
        //            return response()->json(['message' => 'Unauthorized. You do not have permission to view client contacts.'], 403);
        //        }

        $project->load('clients');

        return response()->json($project->clients);
    }

    /**
     * Get project contract details
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContractDetails(Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has permission to view client financial
        if (! $this->canViewClientFinancial($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view contract details.'], 403);
        }

        return response()->json($project->contract_details);
    }

    /**
     * Get project clients and users
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientsAndUsers(Project $project)
    {
        $user = Auth::user();
        $result = [];

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Get clients if user has permission
        if ($this->canViewClientContacts($user, $project)) {
            $project->load('clients');
            $result['clients'] = $project->clients;
        }

        // Get users if user has permission
        if ($this->canViewUsers($user, $project)) {
            $project->load(['users' => function ($query) {
                $query->withPivot('role_id');
            }]);

            // Load role information for each user's project-specific role
            $project->users->each(function ($user) {
                // Load the user's global role information with permissions
                $user->load(['role.permissions']);

                // Get the project-specific role information with permissions
                if (isset($user->pivot->role_id)) {
                    $projectRole = \App\Models\Role::with('permissions')->find($user->pivot->role_id);
                    if ($projectRole) {
                        // Create permissions array
                        $permissions = [];
                        foreach ($projectRole->permissions as $permission) {
                            $permissions[] = [
                                'id' => $permission->id,
                                'name' => $permission->name,
                                'slug' => $permission->slug,
                                'category' => $permission->category,
                            ];
                        }

                        // Add the project role information to the pivot data with permissions included
                        $user->pivot->role_data = [
                            'id' => $projectRole->id,
                            'name' => $projectRole->name,
                            'slug' => $projectRole->slug,
                            'permissions' => $permissions,
                        ];

                        // Make sure role_data is included in the JSON response
                        $user->setRelation('pivot', $user->pivot->makeVisible(['role_data']));

                        // Also set the role property directly for display in the UI
                        $user->pivot->role = $projectRole->name;
                    }
                }

                // Add global role permissions to the user data if available
                if ($user->role) {
                    $globalPermissions = [];
                    foreach ($user->role->permissions as $permission) {
                        $globalPermissions[] = [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'slug' => $permission->slug,
                            'category' => $permission->category,
                        ];
                    }
                    $user->global_permissions = $globalPermissions;

                    // Make sure global_permissions is included in the JSON response
                    $user->makeVisible(['global_permissions']);
                }
            });

            $result['users'] = $project->users;
        }

        return response()->json($result);
    }

    /**
     * Get project users
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectUsers(Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has permission to view users
        if (! $this->canViewUsers($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view team members.'], 403);
        }

        $project->load(['users' => function ($query) {
            $query->withPivot('role_id');
        }]);

        // Load role information for each user's project-specific role
        $project->users->each(function ($user) {
            // Load the user's global role information with permissions
            $user->load(['role.permissions']);

            // Get the project-specific role information with permissions
            if (isset($user->pivot->role_id)) {
                $projectRole = \App\Models\Role::with('permissions')->find($user->pivot->role_id);
                if ($projectRole) {
                    // Create permissions array
                    $permissions = [];
                    foreach ($projectRole->permissions as $permission) {
                        $permissions[] = [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'slug' => $permission->slug,
                            'category' => $permission->category,
                        ];
                    }

                    // Add the project role information to the pivot data with permissions included
                    $user->pivot->role_data = [
                        'id' => $projectRole->id,
                        'name' => $projectRole->name,
                        'slug' => $projectRole->slug,
                        'permissions' => $permissions,
                    ];

                    // Make sure role_data is included in the JSON response
                    $user->setRelation('pivot', $user->pivot->makeVisible(['role_data']));

                    // Also set the role property directly for display in the UI
                    $user->pivot->role = $projectRole->name;
                }
            }

            // Add global role permissions to the user data if available
            if ($user->role) {
                $globalPermissions = [];
                foreach ($user->role->permissions as $permission) {
                    $globalPermissions[] = [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'category' => $permission->category,
                    ];
                }
                $user->global_permissions = $globalPermissions;

                // Make sure global_permissions is included in the JSON response
                $user->makeVisible(['global_permissions']);
            }
        });

        return response()->json($project->users);
    }

    /**
     * Get project documents
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDocuments(Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has permission to view project documents
        if (! $this->canViewProjectDocuments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view documents.'], 403);
        }

        return response()->json($project->documents);
    }

    /**
     * Get project notes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotes(Project $project, Request $request)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has permission to view project notes
        if (! $this->canViewProjectNotes($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view notes.'], 403);
        }

        // Build query for notes
        $notesQuery = $project->notes()->with('user')->whereNull('parent_id');

        // Filter by note type if specified
        if ($request->has('type') && ! empty($request->type)) {
            $notesQuery->where('type', $request->type);
        }

        // Filter by user_id if specified
        if ($request->has('user_id') && ! empty($request->user_id)) {
            $notesQuery->where('user_id', $request->user_id);
        }

        // Apply date range filters if provided
        if ($request->has('start_date') && ! empty($request->start_date)) {
            $notesQuery->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && ! empty($request->end_date)) {
            $notesQuery->whereDate('created_at', '<=', $request->end_date);
        }

        // Order by creation date (newest first)
        $notesQuery->orderBy('created_at', 'desc');

        // Get the notes with their associated users
        $notes = $notesQuery->get();

        // Decrypt note content and add reply count
        $notes->each(function ($note) {
            $note->reply_count = $note->replyCount();
        });

        // Apply search filter if provided (after decryption)
        if ($request->has('search') && ! empty($request->search)) {
            $searchTerm = strtolower($request->search);
            $notes = $notes->filter(function ($note) use ($searchTerm) {

                // Skip notes that couldn't be decrypted
                if ($note->content === '[Encrypted content could not be decrypted]') {
                    return false;
                }

                return str_contains(strtolower($note->content), $searchTerm);
            });

            $notes = $notes->values();
        }

        return response()->json($notes);
    }

    /**
     * Check if user has access to the project
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canAccessProject($user, $project)
    {
        // Super Admin and Manager can view all projects
        if ($user->isSuperAdmin() || $user->isManager()) {
            return true;
        }
        // Employees and Contractors can only view projects they're assigned to
        elseif ($user->isEmployee() || $user->isContractor()) {
            // Check if user is assigned to this project
            return $project->users->contains($user->id);
        }
        // Other roles are not allowed
        else {
            return false;
        }
    }

    /**
     * Check if user has permission to view client contacts
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canViewClientContacts($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_client_contacts')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('view_client_contacts');
    }

    /**
     * Check if user has permission to view client financial
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canViewClientFinancial($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_client_financial')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('view_client_financial');
    }

    /**
     * Check if user has permission to view users
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canViewUsers($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_users')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('view_users');
    }

    /**
     * Check if user has permission to view project services and payments
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canViewProjectServicesAndPayments($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_financial')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('view_project_financial');
    }

    /**
     * Check if user has permission to view project transactions
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canViewProjectTransactions($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_transactions')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('view_project_transactions');
    }

    /**
     * Check if user has permission to manage project expenses
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canManageProjectExpenses($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_expenses')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('manage_project_expenses');
    }

    /**
     * Check if user has permission to manage project income
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canManageProjectIncome($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_income')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('manage_project_income');
    }

    /**
     * Check if user has permission to view project documents
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canViewProjectDocuments($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_documents')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('view_project_documents');
    }

    /**
     * Check if user has permission to view project notes
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canViewProjectNotes($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_notes')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('view_project_notes');
    }

    /**
     * Check if user has permission to manage project services and payments
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canManageProjectServicesAndPayments($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_financial')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('manage_project_financial');
    }

    /**
     * Add a daily standup note to the project and send it to Google Space
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addStandup(Request $request, Project $project)
    {
        $user = Auth::user();

        // Check if user has access to the project
        if (! $this->canAccessProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has permission to add project notes
        if (! $this->canViewProjectNotes($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to add notes.'], 403);
        }

        // Validate the request data
        $validated = $request->validate([
            'yesterday' => 'required|string',
            'today' => 'required|string',
            'blockers' => 'nullable|string',
        ]);

        // Format the standup content
        $formattedContent = '**Daily Standup - '.date('F j, Y')."**\n\n";
        $formattedContent .= '**Yesterday:** '.$validated['yesterday']."\n\n";
        $formattedContent .= '**Today:** '.$validated['today']."\n\n";
        $formattedContent .= '**Blockers:** '.($validated['blockers'] ?? 'None');

        // Create the note with type 'standup'
        $note = $project->notes()->create([
            'content' => $formattedContent,
            'user_id' => $user->id,
            'type' => 'standup',
        ]);

        // Send notification to Google Chat space if the project has one
        if ($project->google_chat_id) {
            try {
                // Format the message for Google Chat
                $messageText = "🏃‍♂️ *Daily Standup from {$user->name} - ".date('F j, Y')."*\n\n";
                $messageText .= '💼 *Yesterday:* '.$validated['yesterday']."\n\n";
                $messageText .= '📝 *Today:* '.$validated['today']."\n\n";
                $messageText .= '🚧 *Blockers:* '.($validated['blockers'] ?? 'None');

                $response = $this->googleChatService->sendMessage($project->google_chat_id, $messageText);

                // Save the message ID to the note
                $note->chat_message_id = $response['name'] ?? null;
                $note->save();

            } catch (\Exception $e) {
                // Log the error but don't fail the note creation
                Log::error('Failed to send standup notification to Google Chat space', [
                    'project_id' => $project->id,
                    'space_name' => $project->google_chat_id,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Standup submitted successfully',
            'note' => $note,
        ], 201);
    }
}
