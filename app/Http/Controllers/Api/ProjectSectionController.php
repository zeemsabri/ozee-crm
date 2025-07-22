<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class ProjectSectionController extends Controller
{
    /**
     * Get basic information for a project
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBasicInfo(Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to view the project
        if (!$this->canViewProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Return only basic information
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
     * Get clients and users for a project
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientsAndUsers(Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to view the project
        if (!$this->canViewProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        $data = [];

        // Only include clients if user has permission to view client contacts
        if ($this->canViewClientContacts($user, $project)) {
            $data['clients'] = $project->clients;
        }

        // Only include users if user has permission to view users
        if ($this->canViewUsers($user, $project)) {
            $data['users'] = $project->users;

            // Load role information for each user's project-specific role
            $data['users']->each(function ($user) {
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
                                'category' => $permission->category
                            ];
                        }

                        // Add the project role information to the pivot data with permissions included
                        $user->pivot->role_data = [
                            'id' => $projectRole->id,
                            'name' => $projectRole->name,
                            'slug' => $projectRole->slug,
                            'permissions' => $permissions
                        ];

                        // Make sure role_data is included in the JSON response
                        $user->setRelation('pivot', $user->pivot->makeVisible(['role_data']));

                        // Also set the role property directly for display in the UI
                        $user->pivot->role = $projectRole->name;
                    }
                }
            });
        }

        // Include contract details if user has permission to view client financial
        if ($this->canViewClientFinancial($user, $project)) {
            $data['contract_details'] = $project->contract_details;
        }

        return response()->json($data);
    }

    /**
     * Get services and payment information for a project
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServicesAndPayment(Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to view the project
        if (!$this->canViewProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Only include services and payment info if user has permission
        if (!$this->canViewProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view services and payment information.'], 403);
        }

        return response()->json([
            'services' => $project->services,
            'service_details' => $project->service_details,
            'total_amount' => $project->total_amount,
            'payment_type' => $project->payment_type,
        ]);
    }

    /**
     * Get transactions for a project
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to view the project
        if (!$this->canViewProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Only include transactions if user has permission
        if (!$this->canViewProjectTransactions($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view transactions.'], 403);
        }

        $transactions = $project->transactions;

        // If user can only view expenses or only income, filter accordingly
        if ($this->canManageProjectExpenses($user, $project) && !$this->canManageProjectIncome($user, $project)) {
            $transactions = $transactions->filter(function ($transaction) {
                return $transaction->type === 'expense';
            });
        } elseif (!$this->canManageProjectExpenses($user, $project) && $this->canManageProjectIncome($user, $project)) {
            $transactions = $transactions->filter(function ($transaction) {
                return $transaction->type === 'income';
            });
        }

        return response()->json($transactions);
    }

    /**
     * Get documents for a project
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDocuments(Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to view the project
        if (!$this->canViewProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Only include documents if user has permission
        if (!$this->canViewProjectDocuments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view documents.'], 403);
        }

        return response()->json([
            'documents' => $project->documents,
        ]);
    }

    /**
     * Get notes for a project
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotes(Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to view the project
        if (!$this->canViewProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Only include notes if user has permission
        if (!$this->canViewProjectNotes($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view notes.'], 403);
        }

        $notes = $project->notes()->with('user')->orderBy('created_at', 'desc')->get();

        // Decrypt the content of each note
        foreach ($notes as $note) {
            try {
                $note->content = Crypt::decryptString($note->content);
            } catch (\Exception $e) {
                // If decryption fails, leave as is
                \Illuminate\Support\Facades\Log::error('Failed to decrypt note content', ['note_id' => $note->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json($notes);
    }

    /**
     * Update basic information for a project
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBasicInfo(Request $request, Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to manage the project
        if (!$this->canManageProjects($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update this project.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'website' => 'nullable|url',
            'social_media_link' => 'nullable|url',
            'preferred_keywords' => 'nullable|string',
            'google_chat_id' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:active,completed,on_hold,archived',
            'project_type' => 'nullable|string|max:255',
            'source' => 'nullable|string|max:255',
            'google_drive_link' => 'nullable|url',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    /**
     * Update services and payment information for a project
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateServicesAndPayment(Request $request, Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to manage services and payments
        if (!$this->canManageProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update services and payment information.'], 403);
        }

        $validated = $request->validate([
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
            'payment_type' => 'required|in:one_off,monthly',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    /**
     * Update transactions for a project
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTransactions(Request $request, Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to manage expenses or income
        if (!$this->canManageProjectExpenses($user, $project) && !$this->canManageProjectIncome($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update transactions.'], 403);
        }

        $validated = $request->validate([
            'transactions' => 'required|array',
            'transactions.*.description' => 'required|string|max:255',
            'transactions.*.amount' => 'required|numeric|min:0',
            'transactions.*.user_id' => 'nullable|exists:users,id',
            'transactions.*.hours_spent' => 'nullable|numeric|min:0',
            'transactions.*.type' => 'required|in:income,expense',
        ]);

        // Filter transactions based on permissions
        $transactions = collect($validated['transactions']);

        if (!$this->canManageProjectExpenses($user, $project)) {
            $transactions = $transactions->filter(function ($transaction) {
                return $transaction['type'] !== 'expense';
            });
        }

        if (!$this->canManageProjectIncome($user, $project)) {
            $transactions = $transactions->filter(function ($transaction) {
                return $transaction['type'] !== 'income';
            });
        }

        // Delete existing transactions and add new ones
        $project->transactions()->delete();

        foreach ($transactions as $transaction) {
            $project->transactions()->create([
                'description' => $transaction['description'],
                'amount' => $transaction['amount'],
                'user_id' => $transaction['user_id'] ?? null,
                'hours_spent' => $transaction['hours_spent'] ?? null,
                'type' => $transaction['type'],
            ]);
        }

        return response()->json($project->transactions);
    }

    /**
     * Update notes for a project
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateNotes(Request $request, Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to add notes
        if (!$this->canAddProjectNotes($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update notes.'], 403);
        }

        $validated = $request->validate([
            'notes' => 'required|array',
            'notes.*.content' => 'required|string',
        ]);

        // Delete existing notes and add new ones
        $project->notes()->delete();

        $notes = [];
        foreach ($validated['notes'] as $note) {
            $notes[] = $project->notes()->create([
                'content' => Crypt::encryptString($note['content']),
                'user_id' => Auth::id(),
            ]);
            $notes[count($notes) - 1]->content = $note['content'];
        }

        return response()->json($notes);
    }

    /**
     * Check if user has permission to view the project
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
     * @return bool
     */
    private function canViewProject($user, $project)
    {
        // Super Admin and Manager can view all projects
        if ($user->isSuperAdmin() || $user->isManager()) {
            return true;
        }

        // Employees and Contractors can only view projects they're assigned to
        if ($user->isEmployee() || $user->isContractor()) {
            return $project->users->contains($user->id);
        }

        return false;
    }

    /**
     * Check if user has permission to manage projects
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
     * @return bool
     */
    private function canManageProjects($user, $project)
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
     * Check if user has permission to view project documents
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
     * Check if user has permission to view project transactions
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
     * Check if user has permission to view project services and payments
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
     * Check if user has permission to manage project services and payments
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
            if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_services_and_payments')) {
                return true;
            }
        }

        // Check global permissions
        return $user->hasPermission('manage_project_services_and_payments');
    }

    /**
     * Check if user has permission to view project notes
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
     * Check if user has permission to add project notes
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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

    /**
     * Check if user has permission to view client contacts
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
            if ($projectRole) {
                // Check for view_client_contacts, view_project_clients, or manage_project_clients permissions
                if ($projectRole->permissions->contains('slug', 'view_client_contacts') ||
                    $projectRole->permissions->contains('slug', 'view_project_clients') ||
                    $projectRole->permissions->contains('slug', 'manage_project_clients')) {
                    return true;
                }
            }
        }

        // Check global permissions
        return $user->hasPermission('view_client_contacts') ||
               $user->hasPermission('view_project_clients') ||
               $user->hasPermission('manage_project_clients');
    }

    /**
     * Check if user has permission to view client financial
     *
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
     * @param \App\Models\User $user
     * @param \App\Models\Project $project
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
            if ($projectRole) {
                // Check for view_users, view_project_users, or manage_project_users permissions
                if ($projectRole->permissions->contains('slug', 'view_users') ||
                    $projectRole->permissions->contains('slug', 'view_project_users') ||
                    $projectRole->permissions->contains('slug', 'manage_project_users')) {
                    return true;
                }
            }
        }

        // Check global permissions
        return $user->hasPermission('view_users') ||
               $user->hasPermission('view_project_users') ||
               $user->hasPermission('manage_project_users');
    }
    /**
     * Get users for a project based on permissions
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectUsers(Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to view the project
        if (!$this->canViewProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has manage_project_users permission (either globally or for this project)
        $hasManageProjectUsers = false;

        // Check project-specific permission first
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_users')) {
                $hasManageProjectUsers = true;
            }
        }

        // If not found in project role, check global permission
        if (!$hasManageProjectUsers) {
            $hasManageProjectUsers = $user->hasPermission('manage_project_users');
        }

        // Check if user has view_project_users permission (either globally or for this project)
        $hasViewProjectUsers = false;

        // Check project-specific permission first
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_users')) {
                $hasViewProjectUsers = true;
            }
        }

        // If not found in project role, check global permission
        if (!$hasViewProjectUsers) {
            $hasViewProjectUsers = $user->hasPermission('view_project_users');
        }

        // Return users based on permissions
        if ($user->isSuperAdmin() || $hasManageProjectUsers) {
            // Super admins and users with manage_project_users permission can see all users
            $users = \App\Models\User::with(['projects'])->orderBy('name')->get();
            return response()->json($users);
        } elseif ($hasViewProjectUsers) {
            // Users with view_project_users permission can see all users in the current project
            $users = $project->users;
            return response()->json($users);
        } else {
            // Other users can only see themselves
            return response()->json(collect([$user]));
        }
    }

    /**
     * Get clients for a project based on permissions
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProjectClients(Project $project)
    {
        $user = Auth::user();

        // Check if user has permission to view the project
        if (!$this->canViewProject($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have access to this project.'], 403);
        }

        // Check if user has manage_project_clients permission (either globally or for this project)
        $hasManageProjectClients = false;

        // Check project-specific permission first
        $projectUser = $project->users()->where('users.id', $user->id)->first();
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'manage_project_clients')) {
                $hasManageProjectClients = true;
            }
        }

        // If not found in project role, check global permission
        if (!$hasManageProjectClients) {
            $hasManageProjectClients = $user->hasPermission('manage_project_clients');
        }

        // Check if user has view_project_clients permission (either globally or for this project)
        $hasViewProjectClients = false;

        // Check project-specific permission first
        if ($projectUser && isset($projectUser->pivot->role_id)) {
            $projectRole = \App\Models\Role::with('permissions')->find($projectUser->pivot->role_id);
            if ($projectRole && $projectRole->permissions->contains('slug', 'view_project_clients')) {
                $hasViewProjectClients = true;
            }
        }

        // If not found in project role, check global permission
        if (!$hasViewProjectClients) {
            $hasViewProjectClients = $user->hasPermission('view_project_clients');
        }

        // Return clients based on permissions
        if ($user->isSuperAdmin() || $hasManageProjectClients) {
            // Super admins and users with manage_project_clients permission can see all clients
            $clients = \App\Models\Client::with(['projects'])->orderBy('name')->get();
            return response()->json($clients);
        } elseif ($hasViewProjectClients) {
            // Users with view_project_clients permission can see all clients in the current project
            $clients = $project->clients;
            return response()->json($clients);
        } else {
            // Other users can't see any clients
            return response()->json([]);
        }
    }
}
