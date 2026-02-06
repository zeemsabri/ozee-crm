<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use App\Services\GmailService;
use App\Services\GoogleChatService;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function __construct(
        protected GmailService $gmailService,
        protected GoogleDriveService $googleDriveService,
        protected GoogleChatService $googleChatService,
        protected ProjectCalendarController $projectCalendarController
    ) {
        //        $this->authorizeResource(Project::class, 'project');

    }

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
                    $note->content = $note->content;
                } catch (\Exception $e) {
                    // If decryption fails, set a placeholder or leave as is
                    Log::error('Failed to decrypt note content in index method', ['note_id' => $note->id, 'error' => $e->getMessage()]);
                    $note->content = '[Encrypted content could not be decrypted]';
                }
            });
        });

        return response()->json($projects);
    }

    public function store(StoreProjectRequest $request)
    {
        try {
            // Get validated data
            $validated = $request->validated();
            if (array_key_exists('status', $validated)) {
                app(\App\Services\ValueSetValidator::class)->validate('Project', 'status', $validated['status']);
            }

            // Basic project data
            $projectData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'website' => $validated['website'] ?? null,
                'social_media_link' => $validated['social_media_link'] ?? null,
                'preferred_keywords' => $validated['preferred_keywords'] ?? null,
                'reporting_sites' => $validated['reporting_sites'] ?? null,
                'google_chat_id' => $validated['google_chat_id'] ?? null,
                'status' => $validated['status'],
                'project_type' => $validated['project_type'] ?? null,
                'source' => $validated['source'] ?? null,
                'google_drive_link' => $validated['google_drive_link'] ?? null,
                //                'payment_type' => $validated['payment_type'],
            ];

            // Create a Google Drive folder for the project inside the Projects folder
            try {
                $projectsFolderId = '11RnSKeKqpAebG-DRKwCykVDl3uIsHMSg'; // Projects folder ID
                $folderId = $this->googleDriveService->createFolder($validated['name'], $projectsFolderId);
                $projectData['google_drive_folder_id'] = $folderId;
                $projectData['google_drive_link'] = "https://drive.google.com/drive/folders/{$folderId}";
            } catch (\Exception $e) {
                Log::error('Failed to create Google Drive folder for project', [
                    'project_name' => $validated['name'],
                    'error' => $e->getMessage(),
                ]);
                // Continue with project creation even if folder creation fails
            }

            // Create a Google Chat space for the project
            try {

                $spaceName = $validated['name'];
                $isDirectMessage = false; // Regular space, not a direct message
                $memberEmails = []; // No initial members except the authorizing user

                $externalMembers = $request->input('allowed_external_members', true);
                $spaceData = $this->googleChatService->createSpace($spaceName, $isDirectMessage, $externalMembers);

                // Store the space name/ID in the project data
                $projectData['google_chat_id'] = $spaceData['name']; // This is the resource name like "spaces/AAAAAAAAAAA"

            } catch (\Exception $e) {
                Log::error('Failed to create Google Chat space for project', [
                    'project_name' => $validated['name'],
                    'error' => $e->getMessage(),
                ]);
                // Continue with project creation even if Chat space creation fails
            }

            // Handle logo upload if present
            if ($request->hasFile('logo')) {
                // Store logo locally first
                $localPath = $request->file('logo')->store('logos', 'public');
                $projectData['logo'] = $localPath;

                // If we have a Google Drive folder ID, also upload logo to Google Drive
                if (isset($projectData['google_drive_folder_id'])) {
                    try {
                        $fullLocalPath = Storage::disk('public')->path($localPath);
                        $originalFilename = $request->file('logo')->getClientOriginalName();

                        // Upload logo to Google Drive
                        $fileId = $this->googleDriveService->uploadFile(
                            $fullLocalPath,
                            'logo_'.$originalFilename,
                            $projectData['google_drive_folder_id']
                        );

                        // Store Google Drive file ID
                        $projectData['logo_google_drive_file_id'] = $fileId;
                    } catch (\Exception $e) {
                        Log::error('Failed to upload logo to Google Drive: '.$e->getMessage());
                    }
                }
            }

            // Create the project with basic information
            $project = Project::create($projectData);

            // Attach users if provided
            if ($request->has('user_ids') && is_array($request->user_ids)) {
                $userIds = [];
                foreach ($request->user_ids as $userData) {
                    if (isset($userData['id'])) {
                        $roleId = isset($userData['role_id']) ? $userData['role_id'] : 2; // Default to role_id 2 if not provided
                        $userIds[$userData['id']] = ['role_id' => $roleId];
                    }
                }
                if (! empty($userIds)) {
                    $project->users()->attach($userIds);
                }
            }

            $project->load(['clients', 'users' => function ($query) {
                $query->withPivot('role_id');
            }, 'transactions', 'notes']);
            $project->notes->each(function ($note) {
                try {
                    $note->content = $note->content;
                } catch (\Exception $e) {
                    // If decryption fails, set a placeholder or leave as is
                    Log::error('Failed to decrypt note content in store method', ['note_id' => $note->id, 'error' => $e->getMessage()]);
                    $note->content = '[Encrypted content could not be decrypted]';
                }
            });

            return response()->json($project, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating project: '.$e->getMessage(), ['request' => $request->all(), 'error' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Failed to create project', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Project $project)
    {
        $user = Auth::user();

        // Super Admin and Manager can view all projects
        if ($user->isSuperAdmin() || $user->isManager()) {
            // Allow access to all projects
        }
        // Employees and Contractors can only view projects they're assigned to
        elseif ($user->isEmployee() || $user->isContractor()) {
            // Check if user is assigned to this project
            if (! $project->users->contains($user->id)) {
                return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
            }
        }
        // Other roles are not allowed
        else {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view projects.'], 403);
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

        // Check if user has permission to view client contacts
        if ($this->canViewClientContacts($user, $project)) {
            $project->load('clients');
            $filteredProject['clients'] = $project->clients;
        }

        // Check if user has permission to view users
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

            $filteredProject['users'] = $project->users;
        }

        // Check if user has permission to view project services and payments
        if ($this->canViewProjectServicesAndPayments($user, $project)) {
            $filteredProject['services'] = $project->services;
            $filteredProject['service_details'] = $project->service_details;
            $filteredProject['total_amount'] = $project->total_amount;
            $filteredProject['payment_type'] = $project->payment_type;
        }

        // Check if user has permission to view project transactions
        if ($this->canViewProjectTransactions($user, $project)) {
            $project->load('transactions');

            // If user can only view expenses or only income, filter accordingly
            if ($this->canManageProjectExpenses($user, $project) && ! $this->canManageProjectIncome($user, $project)) {
                $filteredTransactions = $project->transactions->filter(function ($transaction) {
                    return $transaction->type === 'expense';
                });
                $filteredProject['transactions'] = $filteredTransactions;
            } elseif (! $this->canManageProjectExpenses($user, $project) && $this->canManageProjectIncome($user, $project)) {
                $filteredTransactions = $project->transactions->filter(function ($transaction) {
                    return $transaction->type === 'income';
                });
                $filteredProject['transactions'] = $filteredTransactions;
            } else {
                $filteredProject['transactions'] = $project->transactions;
            }
        }

        // Check if user has permission to view project documents
        if ($this->canViewProjectDocuments($user, $project)) {
            $filteredProject['documents'] = $project->documents;
        }

        // Check if user has permission to view project notes
        if ($this->canViewProjectNotes($user, $project)) {
            // Load only parent notes (where parent_id is null)
            $project->load(['notes' => function ($query) {
                $query->whereNull('parent_id');
            }]);

            // Decrypt note content and add reply count
            $project->notes->each(function ($note) {
                $note->reply_count = $note->replyCount();
            });

            $filteredProject['notes'] = $project->notes;
        }

        // Include contract details if user has permission to view client financial
        if ($this->canViewClientFinancial($user, $project)) {
            $filteredProject['contract_details'] = $project->contract_details;
        }

        return response()->json($filteredProject);
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
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_services_and_payments')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('view_project_services_and_payments');
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

    public function update(Request $request, Project $project)
    {
        try {
            $user = Auth::user();

            // Check if user has permission to manage the project
            if (! $this->canManageProjects($user, $project)) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to update this project.'], 403);
            }

            // Determine if the request is JSON or FormData
            $isJsonRequest = $request->isJson() || $request->header('Content-Type') === 'application/json';

            // Check for file uploads to better detect FormData requests
            $hasFileUploads = $request->hasFile('logo') || $request->hasFile('documents');
            if ($hasFileUploads) {
                $isJsonRequest = false;
            }

            // Handle FormData with JSON strings for arrays and objects
            if (! $isJsonRequest && ($request->header('Content-Type') === 'multipart/form-data' || $hasFileUploads)) {
                // Parse JSON strings in FormData for array/object fields
                $fields = ['services', 'service_details', 'transactions', 'notes'];
                foreach ($fields as $field) {
                    if ($request->has($field) && is_string($request->input($field))) {
                        try {
                            $request->merge([$field => json_decode($request->input($field), true)]);
                        } catch (\Exception $e) {
                            Log::warning("Failed to decode JSON for field {$field}: ".$e->getMessage());
                        }
                    }
                }
            }

            // Adjust validation rules based on request type and user permissions
            $validationRules = [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'website' => 'nullable|url',
                'social_media_link' => 'nullable|url',
                'preferred_keywords' => 'nullable|string',
                'google_chat_id' => 'nullable|string|max:255',
                'client_id' => 'sometimes|required|exists:clients,id',
                'status' => 'sometimes|required|string',
                'project_type' => 'nullable|string|max:255',
                'source' => 'nullable|string|max:255',
                'google_drive_link' => 'nullable|url',
                'reporting_sites' => 'nullable|string',
            ];

            // Only include services and payment validation rules if user has permission
            if ($this->canManageProjectServicesAndPayments($user, $project)) {
                $validationRules = array_merge($validationRules, [
                    'services' => 'nullable|array',
                    'services.*' => 'string|max:255',
                    'service_details' => 'nullable|array',
                    'service_details.*.service_id' => 'required|string|max:255',
                    'service_details.*.amount' => 'required|numeric|min:0',
                    'service_details.*.frequency' => 'required|in:monthly,one_off',
                    'service_details.*.start_date' => 'nullable|date',
                    'service_details.*.payment_breakdown' => 'nullable|array',
                    'service_details.*.payment_breakdown.first' => 'required|numeric|min:0|max:100',
                    'service_details.*.payment_breakdown.second' => 'required|numeric|min:0|max:100',
                    'service_details.*.payment_breakdown.third' => 'required|numeric|min:0|max:100',
                    'total_amount' => 'nullable|numeric|min:0',
                    'payment_type' => 'sometimes|required|in:one_off,monthly',
                ]);
            }

            // Only include contract details validation rule if user has permission
            if ($this->canViewClientFinancial($user, $project)) {
                $validationRules['contract_details'] = 'nullable|string';
            }

            // Only include notes validation rules if user has permission
            if ($this->canAddProjectNotes($user, $project)) {
                $validationRules = array_merge($validationRules, [
                    'notes' => 'nullable|array',
                    'notes.*.content' => 'required|string',
                ]);
            }

            // Only include transactions validation rules if user has permission
            if ($this->canManageProjectExpenses($user, $project) || $this->canManageProjectIncome($user, $project)) {
                $validationRules = array_merge($validationRules, [
                    'transactions' => 'nullable|array',
                    'transactions.*.description' => 'required|string|max:255',
                    'transactions.*.amount' => 'required|numeric|min:0',
                    'transactions.*.user_id' => 'nullable|exists:users,id',
                    'transactions.*.hours_spent' => 'nullable|numeric|min:0',
                    'transactions.*.type' => 'required|in:income,expense',
                ]);
            }

            // Add file validation rules only for non-JSON requests
            if (! $isJsonRequest) {
                $validationRules['logo'] = 'nullable|image|max:2048';
                $validationRules['documents.*'] = 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:10240';
            }

            $validated = $request->validate($validationRules);
            if (array_key_exists('status', $validated)) {
                app(\App\Services\ValueSetValidator::class)->validate('Project', 'status', $validated['status']);
            }

            // Initialize project data with basic information
            $projectData = [
                'name' => $validated['name'] ?? $project->name,
                'description' => $validated['description'] ?? $project->description,
                'website' => $validated['website'] ?? $project->website,
                'social_media_link' => $validated['social_media_link'] ?? $project->social_media_link,
                'preferred_keywords' => $validated['preferred_keywords'] ?? $project->preferred_keywords,
                'google_chat_id' => $validated['google_chat_id'] ?? $project->google_chat_id,
                'client_id' => $validated['client_id'] ?? $project->client_id,
                'status' => $validated['status'] ?? $project->status,
                'project_type' => $validated['project_type'] ?? $project->project_type,
                'source' => $validated['source'] ?? $project->source,
                'google_drive_link' => $validated['google_drive_link'] ?? $project->google_drive_link,
                'reporting_sites' => $validated['reporting_sites'] ?? $project->reporting_sites,
            ];

            // Only include services and payment data if user has permission
            if ($this->canManageProjectServicesAndPayments($user, $project)) {
                $projectData['services'] = $validated['services'] ?? $project->services;
                $projectData['service_details'] = $validated['service_details'] ?? $project->service_details;
                $projectData['total_amount'] = $validated['total_amount'] ?? $project->total_amount;
                $projectData['payment_type'] = $validated['payment_type'] ?? $project->payment_type;
            }

            // Only include contract details if user has permission
            if ($this->canViewClientFinancial($user, $project)) {
                $projectData['contract_details'] = $validated['contract_details'] ?? $project->contract_details;
            }

            // Handle file uploads based on request type
            if ($isJsonRequest) {
                // For JSON requests, check if logo and documents are present but empty
                if ($request->has('logo')) {
                    if (is_array($request->input('logo')) && empty($request->input('logo'))) {
                        // Empty logo object in JSON, do nothing
                    } elseif ($request->input('logo') === null && $project->logo) {
                        // If logo is explicitly set to null, remove the existing logo
                        Storage::disk('public')->delete($project->logo);
                        $projectData['logo'] = null;
                    }
                }

                if ($request->has('documents') && is_array($request->input('documents'))) {
                    $existingDocuments = $project->documents ?? [];
                    $newDocuments = [];

                    foreach ($request->input('documents') as $document) {
                        if (is_array($document) && ! empty($document)) {
                            // If document has data, add it to the documents array
                            $newDocuments[] = $document;
                        } else {
                            // Empty document object in JSON, ignore it
                        }
                    }

                    if (! empty($newDocuments)) {
                        $projectData['documents'] = array_merge($existingDocuments, $newDocuments);
                    }
                }
            } else {
                // For FormData requests, handle actual file uploads
                if ($request->hasFile('logo')) {
                    if ($project->logo) {
                        Storage::disk('public')->delete($project->logo);
                    }

                    // Store logo locally first
                    $localPath = $request->file('logo')->store('logos', 'public');
                    $projectData['logo'] = $localPath;

                    // If project has a Google Drive folder ID, also upload logo to Google Drive
                    if ($project->google_drive_folder_id) {
                        try {
                            $googleDriveService = app(GoogleDriveService::class);
                            $fullLocalPath = Storage::disk('public')->path($localPath);
                            $originalFilename = $request->file('logo')->getClientOriginalName();

                            // Upload logo to Google Drive
                            $fileId = $googleDriveService->uploadFile(
                                $fullLocalPath,
                                'logo_'.$originalFilename,
                                $project->google_drive_folder_id
                            );

                            // Store Google Drive file ID
                            $projectData['logo_google_drive_file_id'] = $fileId;

                        } catch (\Exception $e) {
                            Log::error('Failed to upload logo to Google Drive: '.$e->getMessage(), [
                                'project_id' => $project->id,
                            ]);
                        }
                    }
                }

                // Document uploads are now handled by the dedicated uploadDocuments endpoint
            }

            $project->update($projectData);

            // Only update notes if user has permission
            if (isset($validated['notes']) && $this->canAddProjectNotes($user, $project)) {
                $project->notes()->delete();
                foreach ($validated['notes'] as $note) {
                    $project->notes()->create([
                        'content' => $note['content'],
                        'user_id' => Auth::id(),
                    ]);
                }
            }

            // Only update transactions if user has permission
            if (isset($validated['transactions']) &&
                ($this->canManageProjectExpenses($user, $project) || $this->canManageProjectIncome($user, $project))) {

                $project->transactions()->delete();

                foreach ($validated['transactions'] as $transaction) {
                    // Only add expense transactions if user has permission
                    if ($transaction['type'] === 'expense' && ! $this->canManageProjectExpenses($user, $project)) {
                        continue;
                    }

                    // Only add income transactions if user has permission
                    if ($transaction['type'] === 'income' && ! $this->canManageProjectIncome($user, $project)) {
                        continue;
                    }

                    $project->transactions()->create([
                        'description' => $transaction['description'],
                        'amount' => $transaction['amount'],
                        'user_id' => $transaction['user_id'] ?? null,
                        'hours_spent' => $transaction['hours_spent'] ?? null,
                        'type' => $transaction['type'],
                    ]);
                }
            }

            // Create a filtered response based on user permissions
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

            // Check if user has permission to view client contacts
            if ($this->canViewClientContacts($user, $project)) {
                $project->load('clients');
                $filteredProject['clients'] = $project->clients;
            }

            // Check if user has permission to view users
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

                $filteredProject['users'] = $project->users;
            }

            // Check if user has permission to view project services and payments
            if ($this->canViewProjectServicesAndPayments($user, $project)) {
                $filteredProject['services'] = $project->services;
                $filteredProject['service_details'] = $project->service_details;
                $filteredProject['total_amount'] = $project->total_amount;
                $filteredProject['payment_type'] = $project->payment_type;
            }

            // Check if user has permission to view project transactions
            if ($this->canViewProjectTransactions($user, $project)) {
                $project->load('transactions');

                // If user can only view expenses or only income, filter accordingly
                if ($this->canManageProjectExpenses($user, $project) && ! $this->canManageProjectIncome($user, $project)) {
                    $filteredTransactions = $project->transactions->filter(function ($transaction) {
                        return $transaction->type === 'expense';
                    });
                    $filteredProject['transactions'] = $filteredTransactions;
                } elseif (! $this->canManageProjectExpenses($user, $project) && $this->canManageProjectIncome($user, $project)) {
                    $filteredTransactions = $project->transactions->filter(function ($transaction) {
                        return $transaction->type === 'income';
                    });
                    $filteredProject['transactions'] = $filteredTransactions;
                } else {
                    $filteredProject['transactions'] = $project->transactions;
                }
            }

            // Check if user has permission to view project documents
            if ($this->canViewProjectDocuments($user, $project)) {
                $filteredProject['documents'] = $project->documents;
            }

            // Check if user has permission to view project notes
            if ($this->canViewProjectNotes($user, $project)) {
                $project->load('notes');

                // Decrypt note content
                //                $project->notes->each(function ($note) {
                //                    $note->content = Crypt::decryptString($note->content);
                //                });

                $filteredProject['notes'] = $project->notes;
            }

            // Include contract details if user has permission to view client financial
            if ($this->canViewClientFinancial($user, $project)) {
                $filteredProject['contract_details'] = $project->contract_details;
            }

            return response()->json($filteredProject);
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
     * Check if user has permission to manage projects
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    public function canManageProjects($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'manage_projects')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('manage_projects');
    }

    /**
     * Check if user has permission to add project notes
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return bool
     */
    private function canAddProjectNotes($user, $project)
    {
        // Check for super admin role
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Get the user's project-specific role
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'add_project_notes')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('add_project_notes');
    }

    public function destroy(Project $project)
    {
        try {
            if ($project->logo) {
                Storage::disk('public')->delete($project->logo);
            }
            if ($project->documents) {
                foreach ($project->documents as $document) {
                    Storage::disk('public')->delete($document['path']);
                }
            }
            $project->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting project: '.$e->getMessage(), ['project_id' => $project->id, 'error' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Failed to delete project', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Attach users to a project and update Google Chat/Drive permissions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function attachUsers(Request $request, Project $project)
    {
        $this->authorize('attachAnyUser', $project);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*.id' => 'exists:users,id',
            'user_ids.*.role_id' => 'required|exists:roles,id',
        ]);

        // Get the current list of users and their emails before the sync operation
        $currentUsers = $project->users()->get();
        $currentUserEmails = $currentUsers->pluck('email')->toArray();

        // Prepare the user data for sync
        $userData = collect($validated['user_ids'])->mapWithKeys(function ($user) {
            return [$user['id'] => ['role_id' => $user['role_id']]];
        });

        // Perform the sync operation
        $project->users()->sync($userData);

        // Get the new list of users after the sync operation
        $project->load(['users' => function ($query) {
            $query->withPivot('role_id');
        }]);
        $newUsers = $project->users;
        $newUserEmails = $newUsers->pluck('email')->toArray();

        // Find added and removed users
        $addedUserEmails = array_diff($newUserEmails, $currentUserEmails);
        $removedUserEmails = array_diff($currentUserEmails, $newUserEmails);

        // Update Google Chat space members if the project has a Google Chat space
        if ($project->google_chat_id) {
            try {
                // Add new members to Google Chat space
                if (! empty($addedUserEmails)) {
                    $responseArray = $this->googleChatService->addMembersToSpace($project->google_chat_id, $addedUserEmails);

                    // Update users with chat_name
                    foreach ($responseArray as $userInfo) {
                        if (isset($userInfo['email']) && isset($userInfo['chat_name'])) {
                            User::where('email', $userInfo['email'])->update(['chat_name' => $userInfo['chat_name']]);
                        }
                    }
                }

                // Remove members from Google Chat space
                if (! empty($removedUserEmails)) {
                    $usersToDetach = User::whereIn('email', $removedUserEmails)->get();
                    $this->googleChatService->removeMembersFromSpace($project->google_chat_id, $usersToDetach->toArray());
                }
            } catch (\Exception $e) {
                // Log the error but don't fail the user assignment
                Log::error('Failed to update Google Chat space members', [
                    'project_id' => $project->id,
                    'space_name' => $project->google_chat_id,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        // Update Google Drive folder permissions if the project has a Google Drive folder
        if ($project->google_drive_folder_id) {
            try {
                // Add new members to Google Drive
                foreach ($addedUserEmails as $email) {
                    try {
                        // Assuming 'writer' role for added members
                        $this->googleDriveService->addPermission($project->google_drive_folder_id, $email, 'writer');
                    } catch (\Exception $e) {
                        Log::error('Failed to add Google Drive permission for '.$email, [
                            'project_id' => $project->id,
                            'folder_id' => $project->google_drive_folder_id,
                            'email' => $email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Remove members from Google Drive
                foreach ($removedUserEmails as $email) {
                    try {
                        $this->googleDriveService->removePermission($project->google_drive_folder_id, $email);
                    } catch (\Exception $e) {
                        Log::error('Failed to remove Google Drive permission for '.$email, [
                            'project_id' => $project->id,
                            'folder_id' => $project->google_drive_folder_id,
                            'email' => $email,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

            } catch (\Exception $e) {
                Log::error('Failed to update Google Drive permissions for project', [
                    'project_id' => $project->id,
                    'folder_id' => $project->google_drive_folder_id,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        return response()->json($project->users, 200);
    }

    public function detachUsers(Request $request, Project $project)
    {
        $this->authorize('detachAnyUser', $project);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Get the users being detached
        $usersToDetach = User::whereIn('id', $validated['user_ids'])->get();

        // Detach the users from the project
        $project->users()->detach($validated['user_ids']);

        // Update Google Chat space members if the project has a Google Chat space
        if ($project->google_chat_id && $usersToDetach->count() > 0) {
            try {
                // Remove members from Google Chat space - pass the user objects directly
                $this->googleChatService->removeMembersFromSpace($project->google_chat_id, $usersToDetach->all());
            } catch (\Exception $e) {
                // Log the error but don't fail the user detachment
                Log::error('Failed to remove members from Google Chat space', [
                    'project_id' => $project->id,
                    'space_name' => $project->google_chat_id,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        $project->load(['users' => function ($query) {
            $query->withPivot('role_id');
        }]);

        return response()->json($project->users);
    }

    public function attachClients(Request $request, Project $project)
    {
        $this->authorize('attachAnyClient', $project);

        $validated = $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*.id' => 'exists:clients,id',
            'client_ids.*.role_id' => 'required|exists:roles,id',
        ]);

        $clientData = collect($validated['client_ids'])->mapWithKeys(function ($client) {
            return [$client['id'] => ['role_id' => $client['role_id']]];
        });
        $project->clients()->sync($clientData);

        $project->load('clients');

        return response()->json($project->clients, 200);
    }

    public function detachClients(Request $request, Project $project)
    {
        $this->authorize('detachAnyClient', $project);

        $validated = $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*' => 'exists:clients,id',
        ]);

        $project->clients()->detach($validated['client_ids']);
        $project->load('clients');

        return response()->json($project->clients);
    }

    public function addNotes(Request $request, Project $project)
    {
        $this->authorize('addNotes', $project);

        $validated = $request->validate([
            'notes' => 'required|array',
            'notes.*.content' => 'required|string',
        ]);

        $notes = [];
        $user = Auth::user();

        foreach ($validated['notes'] as $note) {
            $notes[] = $project->notes()->create([
                'content' => $note['content'],
                'user_id' => Auth::id(),
            ]);

            // Send notification to Google Chat space if the project has one
            if ($project->google_chat_id) {
                try {
                    $messageText = "📝 *{$user->name}*: ".$note['content'];
                    $response = $this->googleChatService->sendMessage($project->google_chat_id, $messageText);

                    // Save the message ID to the note
                    $notes[count($notes) - 1]->chat_message_id = $response['name'] ?? null;
                    $notes[count($notes) - 1]->save();

                } catch (\Exception $e) {
                    // Log the error but don't fail the note creation
                    Log::error('Failed to send note notification to Google Chat space', [
                        'project_id' => $project->id,
                        'space_name' => $project->google_chat_id,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
            }
        }

        return response()->json($notes, 201);
    }

    public function convertPaymentType(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'payment_type' => 'required|in:one_off,monthly',
        ]);

        $project->update([
            'payment_type' => $validated['payment_type'],
        ]);

        return response()->json($project);
    }

    /**
     * Get notes for a project
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotes(Project $project)
    {
        $this->authorize('view', $project);

        $notes = $project->notes()->with('user')->orderBy('created_at', 'desc')->get();

        return response()->json($notes);
    }

    /**
     * Get tasks for a project
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTasks(Project $project)
    {
        $this->authorize('view', $project);

        // Get all milestones for this project
        $milestoneIds = $project->milestones()->pluck('id')->toArray();

        // Get all tasks that belong to these milestones
        $tasks = \App\Models\Task::whereIn('milestone_id', $milestoneIds)
            ->with(['assignedTo', 'taskType', 'milestone', 'tags', 'subtasks'])
            ->orderBy('due_date', 'asc')
            ->get();

        // Format the tasks for the frontend
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
     * Get replies for a specific note
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNoteReplies(Project $project, ProjectNote $note)
    {
        $this->authorize('viewNotes', $project);

        // Check if the note belongs to the project
        if ($note->project_id !== $project->id) {
            return response()->json([
                'message' => 'The note does not belong to this project.',
                'success' => false,
            ], 400);
        }

        // Get all replies for this note
        $replies = $note->replies()->with('user')->orderBy('created_at', 'asc')->get();

        // Decrypt the content of each reply
        $replies->each(function ($reply) {
            try {
                $reply->content = $reply->content;
            } catch (\Exception $e) {
                Log::error('Failed to decrypt reply content', ['reply_id' => $reply->id, 'error' => $e->getMessage()]);
                $reply->content = '[Encrypted content could not be decrypted]';
            }
        });

        return response()->json([
            'replies' => $replies,
            'success' => true,
        ]);
    }

    public function replyToNote(Request $request, Project $project, ProjectNote $note)
    {
        $this->authorize('addNotes', $project);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        // Check if the project has a Google Chat space
        if (! $project->google_chat_id) {
            return response()->json([
                'message' => 'This project does not have a Google Chat space.',
                'success' => false,
            ], 400);
        }

        // Check if the note has a chat_message_id
        if (! $note->chat_message_id) {
            return response()->json([
                'message' => 'This note does not have an associated Google Chat message to reply to.',
                'success' => false,
            ], 400);
        }

        $user = Auth::user();
        $messageText = "💬 *{$user->name}*: ".$validated['content'];

        try {
            // Extract space ID and thread key from chat_message_id
            $messageResourceName = $note->chat_message_id;
            $parts = explode('/', $messageResourceName);

            if (count($parts) < 4 || $parts[0] !== 'spaces' || $parts[2] !== 'messages') {
                throw new \Exception('Invalid chat_message_id format: Expected spaces/{space_id}/messages/{message_id_segment}.');
            }

            $spaceId = $parts[1]; // e.g., "AAAAcyAGtPk"
            $messageIdSegment = $parts[3]; // e.g., "NLG_HCMqEJc.NLG_HCMqEJc"

            // Validate space ID matches project->google_chat_id
            if ('spaces/'.$spaceId !== $project->google_chat_id) {
                Log::warning('Space ID mismatch', [
                    'project_chat_id' => $project->google_chat_id,
                    'chat_message_space_id' => 'spaces/'.$spaceId,
                    'chat_message_id' => $note->chat_message_id,
                ]);
                throw new \Exception('Space ID in chat_message_id does not match project Google Chat space.');
            }

            // Extract thread key
            $threadKey = $messageIdSegment;
            if (str_contains($messageIdSegment, '.')) {
                $threadKeyParts = explode('.', $messageIdSegment);
                $threadKey = end($threadKeyParts); // Get the last part after the dot
            }

            // Construct thread name: spaces/{space_id}/threads/{thread_key}
            $threadNameForReply = 'spaces/'.$spaceId.'/threads/'.$threadKey;

            // Send the reply to the specified space and thread
            $response = $this->googleChatService->sendThreadedMessage(
                $project->google_chat_id,
                $threadNameForReply,
                $messageText
            );

            // Verify the message was posted to the correct thread
            if (! isset($response['thread']['name']) || $response['thread']['name'] !== $threadNameForReply) {
                Log::warning('Message may not have been posted to the correct thread', [
                    'expected_thread' => $threadNameForReply,
                    'response_thread' => $response['thread']['name'] ?? 'N/A',
                    'message_id' => $response['name'] ?? 'N/A',
                ]);
            }

            // Create a new note for the reply
            $replyNote = $project->notes()->create([
                'content' => $validated['content'],
                'user_id' => Auth::id(),
                'chat_message_id' => $response['name'] ?? null,
                'parent_id' => $note->id, // Set the parent_id to create the parent-child relationship
            ]);

            // Set the decrypted content for the response
            $replyNote->content = $validated['content'];

            return response()->json([
                'message' => 'Reply sent successfully',
                'note' => $replyNote,
                'success' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send reply to note in Google Chat thread', [
                'project_id' => $project->id,
                'space_name' => $project->google_chat_id,
                'note_id' => $note->id,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Failed to send reply: '.$e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    /**
     * Upload documents to a project
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadDocuments(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        try {
            $validationRules = [
                'documents' => 'required|array',
                'documents.*' => 'required|file|mimes:pdf,doc,docx,jpg,png|max:10240',
            ];

            $validated = $request->validate($validationRules);

            $existingDocuments = $project->documents ?? [];

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $document) {
                    // Store file locally first
                    $localPath = $document->store('documents', 'public');
                    $fullLocalPath = Storage::disk('public')->path($localPath);
                    $originalFilename = $document->getClientOriginalName();

                    try {
                        // If project has a Google Drive folder ID, upload to Google Drive
                        if ($project->google_drive_folder_id) {
                            // Upload file to Google Drive
                            $fileId = $this->googleDriveService->uploadFile(
                                $fullLocalPath,
                                $originalFilename,
                                $project->google_drive_folder_id
                            );

                            // Add file info to documents array with Google Drive ID
                            $existingDocuments[] = [
                                'path' => $localPath,
                                'filename' => $originalFilename,
                                'google_drive_file_id' => $fileId,
                            ];
                        } else {
                            // If no Google Drive folder ID, just store locally
                            $existingDocuments[] = [
                                'path' => $localPath,
                                'filename' => $originalFilename,
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to upload file to Google Drive: '.$e->getMessage(), [
                            'project_id' => $project->id,
                            'file_name' => $originalFilename,
                        ]);

                        // Still add the local file to documents array if Google Drive upload fails
                        $existingDocuments[] = [
                            'path' => $localPath,
                            'filename' => $originalFilename,
                            'upload_error' => 'Failed to upload to Google Drive',
                        ];
                    }
                }

                // Update the project with the new documents
                $project->update(['documents' => $existingDocuments]);

                return response()->json([
                    'message' => 'Documents uploaded successfully',
                    'documents' => $existingDocuments,
                ]);
            }

            return response()->json([
                'message' => 'No documents were uploaded',
                'documents' => $existingDocuments,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading documents: '.$e->getMessage(), [
                'project_id' => $project->id,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to upload documents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get projects for email composer screen
     * Returns only project name, client name, and relevant project fields
     * No contact information is included in the response
     * Filters projects based on user's permission to send emails
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectsForEmailComposer()
    {
        $user = Auth::user();
        $projects = collect();

        $user->load(['role.permissions']);
        $hasGlobalComposeEmailPermission = false;
        if ($user->role && $user->role->permissions) {
            $hasGlobalComposeEmailPermission = $user->role->permissions->contains('slug', 'compose_emails');
        }

        if ($user->isSuperAdmin()) {
            $projects = Project::with('clients:id,name')->get();
        } else {
            $userProjects = $user->projects()->with(['clients:id,name'])->get();
            foreach ($userProjects as $project) {
                $projectRole = null;
                if (isset($project->pivot->role_id)) {
                    $projectRole = \App\Models\Role::with('permissions')->find($project->pivot->role_id);
                }
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
     * Get simplified projects data for dashboard
     * Returns only id, name, and status fields
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
     * Create a new meeting for a project.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createProjectMeeting(Request $request, Project $project)
    {
        // Delegate to the ProjectCalendarController
        $response = $this->projectCalendarController->createProjectMeeting($request, $project);

        // If the meeting was created successfully, save it to the database
        if ($response->getStatusCode() === 201) {
            $data = json_decode($response->getContent(), true);

            // Get the timezone from the request or use the default app timezone
            $timezone = $request->input('timezone') ?? config('app.timezone');

            // Convert start and end times to UTC for storage
            $startTime = $request->input('start_datetime');
            $endTime = $request->input('end_datetime');

            // Use Carbon to parse the datetime strings with the specified timezone and convert to UTC
            $startTimeUtc = \Carbon\Carbon::parse($startTime, $timezone)->setTimezone('UTC');
            $endTimeUtc = \Carbon\Carbon::parse($endTime, $timezone)->setTimezone('UTC');

            // Create a new meeting record in the database
            $meeting = new Meeting([
                'project_id' => $project->id,
                'created_by_user_id' => Auth::id(),
                'google_event_id' => $data['event']['id'],
                'google_event_link' => $data['event']['htmlLink'],
                'google_meet_link' => $data['event']['hangoutLink'] ?? null,
                'summary' => $request->input('summary'),
                'description' => $request->input('description'),
                'start_time' => $startTimeUtc->format('Y-m-d H:i:s'),
                'end_time' => $endTimeUtc->format('Y-m-d H:i:s'),
                'location' => $request->input('location'),
                'timezone' => $timezone,
                'enable_recording' => $request->input('enable_recording', false),
                // Flag to indicate that times are stored in UTC
                'is_utc' => true,
            ]);

            $meeting->save();

            // Add the meeting ID to the response data
            $data['meeting_id'] = $meeting->id;

            // Return a new response with the updated data
            return response()->json($data, 201);
        }

        // If there was an error, just return the original response
        return $response;
    }

    /**
     * Delete a meeting for a project.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProjectMeeting(Request $request, Project $project, string $googleEventId)
    {
        // Find the meeting in the database
        $meeting = $project->meetings()->where('google_event_id', $googleEventId)->first();

        // Delegate to the ProjectCalendarController
        $response = $this->projectCalendarController->deleteProjectMeeting($request, $project, $googleEventId);

        // If the meeting was deleted successfully, delete it from the database
        if ($response->getStatusCode() === 200 && $meeting) {
            $meeting->delete();
        }

        return $response;
    }

    /**
     * Get meetings for a project.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectMeetings(Project $project)
    {
        // Get all meetings for the project
        $meetings = $project->meetings()
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json($meetings);
    }
}
