<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Models\ProjectTier;
use App\Models\User;
use App\Models\Meeting;
use App\Models\ProjectNote;
use App\Services\GmailService;
use App\Services\GoogleDriveService;
use App\Services\GoogleChatService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Api\Concerns\HasProjectPermissions; // Import the trait

class ProjectActionController extends Controller
{
    use HasProjectPermissions; // Use the trait

    protected GmailService $gmailService;
    protected GoogleDriveService $googleDriveService;
    protected GoogleChatService $googleChatService;
    protected ProjectCalendarController $projectCalendarController;

    public function __construct(
        GmailService $gmailService,
        GoogleDriveService $googleDriveService,
        GoogleChatService $googleChatService,
        ProjectCalendarController $projectCalendarController
    ) {
        $this->gmailService = $gmailService;
        $this->googleDriveService = $googleDriveService;
        $this->googleChatService = $googleChatService;
        $this->projectCalendarController = $projectCalendarController;
    }

    /**
     * Store a newly created project in storage.
     *
     * @param StoreProjectRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProjectRequest $request)
    {
        try {
            $validated = $request->validated();

            $projectData = [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'website' => $validated['website'] ?? null,
                'social_media_link' => $validated['social_media_link'] ?? null,
                'preferred_keywords' => $validated['preferred_keywords'] ?? null,
                'google_chat_id' => $validated['google_chat_id'] ?? null,
                'status' => $validated['status'],
                'project_type' => $validated['project_type'] ?? null,
                'source' => $validated['source'] ?? null,
                'google_drive_link' => $validated['google_drive_link'] ?? null,
            ];

            try {
                $projectsFolderId = '11RnSKeKqpAebG-DRKwCykVDl3uIsHMSg'; // Projects folder ID
                $folderId = $this->googleDriveService->createFolder($validated['name'], $projectsFolderId);
                $projectData['google_drive_folder_id'] = $folderId;
                $projectData['google_drive_link'] = "https://drive.google.com/drive/folders/{$folderId}";
            } catch (\Exception $e) {
                Log::error('Failed to create Google Drive folder for project', ['project_name' => $validated['name'], 'error' => $e->getMessage()]);
            }

            try {
                $spaceName = $validated['name'];
                $externalMembers = $request->input('allowed_external_members', true);
                $spaceData = $this->googleChatService->createSpace($spaceName, false, $externalMembers);
                $projectData['google_chat_id'] = $spaceData['name'];
            } catch (\Exception $e) {
                Log::error('Failed to create Google Chat space for project', ['project_name' => $validated['name'], 'error' => $e->getMessage()]);
            }

            if ($request->hasFile('logo')) {
                $localPath = $request->file('logo')->store('logos', 'public');
                $projectData['logo'] = $localPath;

                if (isset($projectData['google_drive_folder_id'])) {
                    try {
                        $fullLocalPath = Storage::disk('public')->path($localPath);
                        $originalFilename = $request->file('logo')->getClientOriginalName();
                        $fileId = $this->googleDriveService->uploadFile($fullLocalPath, 'logo_' . $originalFilename, $projectData['google_drive_folder_id']);
                        $projectData['logo_google_drive_file_id'] = $fileId;
                    } catch (\Exception $e) {
                        Log::error('Failed to upload logo to Google Drive: ' . $e->getMessage());
                    }
                }
            }

            $project = Project::create($projectData);

            if ($request->has('user_ids') && is_array($request->user_ids)) {
                $userIds = [];
                foreach ($request->user_ids as $userData) {
                    if (isset($userData['id'])) {
                        $roleId = isset($userData['role_id']) ? $userData['role_id'] : 2;
                        $userIds[$userData['id']] = ['role_id' => $roleId];
                    }
                }
                if (!empty($userIds)) {
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
                    Log::error('Failed to decrypt note content in store method', ['note_id' => $note->id, 'error' => $e->getMessage()]);
                    $note->content = '[Encrypted content could not be decrypted]';
                }
            });

            return response()->json($project, 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error creating project: ' . $e->getMessage(), ['request' => $request->all(), 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to create project', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified project in storage.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Project $project)
    {
        try {
            $user = Auth::user();
            if (!$this->canManageProjects($user, $project)) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to update this project.'], 403);
            }

            $isJsonRequest = $request->isJson() || $request->header('Content-Type') === 'application/json';
            $hasFileUploads = $request->hasFile('logo') || $request->hasFile('documents');
            if ($hasFileUploads) {
                $isJsonRequest = false;
            }

            if (!$isJsonRequest && ($request->header('Content-Type') === 'multipart/form-data' || $hasFileUploads)) {
                $fields = ['services', 'service_details', 'transactions', 'notes'];
                foreach ($fields as $field) {
                    if ($request->has($field) && is_string($request->input($field))) {
                        try {
                            $request->merge([$field => json_decode($request->input($field), true)]);
                        } catch (\Exception $e) {
                            Log::warning("Failed to decode JSON for field {$field}: " . $e->getMessage());
                        }
                    }
                }
            }

            $validationRules = [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'website' => 'nullable|url',
                'social_media_link' => 'nullable|url',
                'preferred_keywords' => 'nullable|string',
                'google_chat_id' => 'nullable|string|max:255',
                'client_id' => 'sometimes|required|exists:clients,id',
                'status' => 'sometimes|required|in:active,completed,on_hold,archived',
                'project_type' => 'nullable|string|max:255',
                'source' => 'nullable|string|max:255',
                'google_drive_link' => 'nullable|url',
            ];

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

            if ($this->canViewClientFinancial($user, $project)) {
                $validationRules['contract_details'] = 'nullable|string';
            }

            if ($this->canAddProjectNotes($user, $project)) {
                $validationRules = array_merge($validationRules, [
                    'notes' => 'nullable|array',
                    'notes.*.content' => 'required|string',
                ]);
            }

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

            if (!$isJsonRequest) {
                $validationRules['logo'] = 'nullable|image|max:2048';
                $validationRules['documents.*'] = 'nullable|file|mimes:pdf,doc,docx,jpg,png|max:10240';
            }

            $validated = $request->validate($validationRules);

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
            ];

            if ($this->canManageProjectServicesAndPayments($user, $project)) {
                $projectData['services'] = $validated['services'] ?? $project->services;
                $projectData['service_details'] = $validated['service_details'] ?? $project->service_details;
                $projectData['total_amount'] = $validated['total_amount'] ?? $project->total_amount;
                $projectData['payment_type'] = $validated['payment_type'] ?? $project->payment_type;
            }

            if ($this->canViewClientFinancial($user, $project)) {
                $projectData['contract_details'] = $validated['contract_details'] ?? $project->contract_details;
            }

            if ($isJsonRequest) {
                if ($request->has('logo')) {
                    if (is_array($request->input('logo')) && empty($request->input('logo'))) {
                        // Do nothing
                    } else if ($request->input('logo') === null && $project->logo) {
                        Storage::disk('public')->delete($project->logo);
                        $projectData['logo'] = null;
                    }
                }

                if ($request->has('documents') && is_array($request->input('documents'))) {
                    $existingDocuments = $project->documents ?? [];
                    $newDocuments = [];
                    foreach ($request->input('documents') as $document) {
                        if (is_array($document) && !empty($document)) {
                            $newDocuments[] = $document;
                        }
                    }
                    if (!empty($newDocuments)) {
                        $projectData['documents'] = array_merge($existingDocuments, $newDocuments);
                    }
                }
            } else {
                if ($request->hasFile('logo')) {
                    if ($project->logo) {
                        Storage::disk('public')->delete($project->logo);
                    }
                    $localPath = $request->file('logo')->store('logos', 'public');
                    $projectData['logo'] = $localPath;

                    if ($project->google_drive_folder_id) {
                        try {
                            $fullLocalPath = Storage::disk('public')->path($localPath);
                            $originalFilename = $request->file('logo')->getClientOriginalName();
                            $fileId = $this->googleDriveService->uploadFile($fullLocalPath, 'logo_' . $originalFilename, $project->google_drive_folder_id);
                            $projectData['logo_google_drive_file_id'] = $fileId;
                        } catch (\Exception $e) {
                            Log::error('Failed to upload logo to Google Drive: ' . $e->getMessage(), ['project_id' => $project->id]);
                        }
                    }
                }
            }

            $project->update($projectData);

            if (isset($validated['notes']) && $this->canAddProjectNotes($user, $project)) {
                $project->notes()->delete();
                foreach ($validated['notes'] as $note) {
                    $project->notes()->create([
                        'content' => $note['content'],
                        'user_id' => Auth::id(),
                    ]);
                }
            }

            if (isset($validated['transactions']) &&
                ($this->canManageProjectExpenses($user, $project) || $this->canManageProjectIncome($user, $project))) {

                $project->transactions()->delete();

                foreach ($validated['transactions'] as $transaction) {
                    if ($transaction['type'] === 'expense' && !$this->canManageProjectExpenses($user, $project)) {
                        continue;
                    }
                    if ($transaction['type'] === 'income' && !$this->canManageProjectIncome($user, $project)) {
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

            // Return filtered project data
            return response()->json($project);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error updating project: ' . $e->getMessage(), ['project_id' => $project->id, 'request' => $request->all(), 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to update project', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified project from storage.
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Project $project)
    {
        try {
            // Check if user has permission to manage the project (delete falls under manage)
            $user = Auth::user();
            if (!$this->canManageProjects($user, $project)) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to delete this project.'], 403);
            }

            if ($project->logo) {
                Storage::disk('public')->delete($project->logo);
            }
            if ($project->documents) {
                foreach ($project->documents as $document) {
                    Storage::disk('public')->delete($document['path']);
                }
            }
            $project->delete();
            Log::info('Project deleted', ['project_id' => $project->id, 'project_name' => $project->name, 'user_id' => Auth::id()]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting project: ' . $e->getMessage(), ['project_id' => $project->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to delete project', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Attach users to a project and update Google Chat/Drive permissions.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function attachUsers(Request $request, Project $project)
    {
        // Authorization should ideally be handled via policies or a dedicated authorization layer.
        // For now, let's assume `Auth::user()->can('attachAnyUser', $project)` if such a policy exists.
        // As per the original file, it was using $this->authorize('attachAnyUser', $project);
        // Ensure your ProjectPolicy reflects this or remove if authorization is handled by traits/roles.
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*.id' => 'exists:users,id',
            'user_ids.*.role_id' => 'required|exists:roles,id',
        ]);

        $currentUsers = $project->users()->get();
        $currentUserEmails = $currentUsers->pluck('email')->toArray();

        $userData = collect($validated['user_ids'])->mapWithKeys(function ($user) {
            return [$user['id'] => ['role_id' => $user['role_id']]];
        });

        $project->users()->sync($userData);

        $project->load(['users' => function ($query) {
            $query->withPivot('role_id');
        }]);
        $newUsers = $project->users;
        $newUserEmails = $newUsers->pluck('email')->toArray();

        $addedUserEmails = array_diff($newUserEmails, $currentUserEmails);
        $removedUserEmails = array_diff($currentUserEmails, $newUserEmails);

        if ($project->google_chat_id) {
            try {
                if (!empty($addedUserEmails)) {
                    $responseArray = $this->googleChatService->addMembersToSpace($project->google_chat_id, $addedUserEmails);
                    foreach ($responseArray as $userInfo) {
                        if (isset($userInfo['email']) && isset($userInfo['chat_name'])) {
                            User::where('email', $userInfo['email'])->update(['chat_name' => $userInfo['chat_name']]);
                        }
                    }
                    Log::info('Added members to Google Chat space', ['project_id' => $project->id, 'space_name' => $project->google_chat_id, 'added_emails' => $addedUserEmails, 'response' => $responseArray]);
                }
                if (!empty($removedUserEmails)) {
                    $usersToDetach = User::whereIn('email', $removedUserEmails)->get();
                    $this->googleChatService->removeMembersFromSpace($project->google_chat_id, $usersToDetach->toArray());
                    Log::info('Removed members from Google Chat space', ['project_id' => $project->id, 'space_name' => $project->google_chat_id, 'removed_emails' => $removedUserEmails]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to update Google Chat space members', ['project_id' => $project->id, 'space_name' => $project->google_chat_id, 'error' => $e->getMessage(), 'exception' => $e]);
            }
        }

        if ($project->google_drive_folder_id) {
            try {
                foreach ($addedUserEmails as $email) {
                    try {
                        $this->googleDriveService->addPermission($project->google_drive_folder_id, $email, 'writer');
                    } catch (\Exception $e) {
                        Log::error('Failed to add Google Drive permission for ' . $email, ['project_id' => $project->id, 'folder_id' => $project->google_drive_folder_id, 'email' => $email, 'error' => $e->getMessage()]);
                    }
                }
                foreach ($removedUserEmails as $email) {
                    try {
                        $this->googleDriveService->removePermission($project->google_drive_folder_id, $email);
                    } catch (\Exception $e) {
                        Log::error('Failed to remove Google Drive permission for ' . $email, ['project_id' => $project->id, 'folder_id' => $project->google_drive_folder_id, 'email' => $email, 'error' => $e->getMessage()]);
                    }
                }
                Log::info('Google Drive permissions updated for project', ['project_id' => $project->id, 'folder_id' => $project->google_drive_folder_id, 'added_emails' => $addedUserEmails, 'removed_emails' => $removedUserEmails]);
            } catch (\Exception $e) {
                Log::error('Failed to update Google Drive permissions for project', ['project_id' => $project->id, 'folder_id' => $project->google_drive_folder_id, 'error' => $e->getMessage(), 'exception' => $e]);
            }
        }

        return response()->json($project->users, 200);
    }

    /**
     * Detach users from a project.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function detachUsers(Request $request, Project $project)
    {
        // Authorization should ideally be handled via policies or a dedicated authorization layer.
        // As per the original file, it was using $this->authorize('detachAnyUser', $project);
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $usersToDetach = User::whereIn('id', $validated['user_ids'])->get();
        $project->users()->detach($validated['user_ids']);

        if ($project->google_chat_id && $usersToDetach->count() > 0) {
            try {
                $this->googleChatService->removeMembersFromSpace($project->google_chat_id, $usersToDetach->all());
                Log::info('Removed members from Google Chat space', ['project_id' => $project->id, 'space_name' => $project->google_chat_id, 'removed_emails' => $usersToDetach->pluck('email')->toArray()]);
            } catch (\Exception $e) {
                Log::error('Failed to remove members from Google Chat space', ['project_id' => $project->id, 'space_name' => $project->google_chat_id, 'error' => $e->getMessage(), 'exception' => $e]);
            }
        }

        $project->load(['users' => function ($query) {
            $query->withPivot('role_id');
        }]);
        return response()->json($project->users);
    }

    /**
     * Attach clients to a project.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function attachClients(Request $request, Project $project)
    {
        // Authorization should ideally be handled via policies or a dedicated authorization layer.
        // As per the original file, it was using $this->authorize('attachAnyClient', $project);
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

    /**
     * Detach clients from a project.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function detachClients(Request $request, Project $project)
    {
        // Authorization should ideally be handled via policies or a dedicated authorization layer.
        // As per the original file, it was using $this->authorize('detachAnyClient', $project);
        $validated = $request->validate([
            'client_ids' => 'required|array',
            'client_ids.*' => 'exists:clients,id',
        ]);

        $project->clients()->detach($validated['client_ids']);
        $project->load('clients');
        return response()->json($project->clients);
    }

    /**
     * Add notes to a project.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNotes(Request $request, Project $project)
    {

        $user = Auth::user();

        if (!$this->canAddProjectNotes($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to add notes.'], 403);
        }

        $validated = $request->validate([
            'notes' => 'required|array',
            'notes.*.content' => 'required|string',
        ]);

        $notes = [];
        foreach ($validated['notes'] as $note) {
            $createdNote = ProjectNote::createAndNotify($project, $note['content'], ['type' => 'note']);
            $notes[] = $createdNote;
        }

        return response()->json($notes, 201);
    }

    /**
     * Convert project payment type.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function convertPaymentType(Request $request, Project $project)
    {
        $user = Auth::user();
        if (!$this->canManageProjectServicesAndPayments($user, $project)) { // Assuming 'update' permission implies managing financial
            return response()->json(['message' => 'Unauthorized. You do not have permission to convert payment type.'], 403);
        }

        $validated = $request->validate([
            'payment_type' => 'required|in:one_off,monthly',
        ]);

        $project->update(['payment_type' => $validated['payment_type']]);

        return response()->json($project);
    }

    /**
     * Reply to a specific note and send to Google Chat thread.
     *
     * @param Request $request
     * @param Project $project
     * @param ProjectNote $note
     * @return \Illuminate\Http\JsonResponse
     */
    public function replyToNote(Request $request, Project $project, ProjectNote $note)
    {
        $user = Auth::user();
        if (!$this->canAddProjectNotes($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to add replies.'], 403);
        }

        $validated = $request->validate(['content' => 'required|string']);

        if (!$project->google_chat_id) {
            return response()->json(['message' => 'This project does not have a Google Chat space.', 'success' => false], 400);
        }

        if (!$note->chat_message_id) {
            return response()->json(['message' => 'This note does not have an associated Google Chat message to reply to.', 'success' => false], 400);
        }

        $messageText = "💬 *{$user->name}*: " . $validated['content'];

        try {
            $messageResourceName = $note->chat_message_id;
            $parts = explode('/', $messageResourceName);

            if (count($parts) < 4 || $parts[0] !== 'spaces' || $parts[2] !== 'messages') {
                throw new \Exception('Invalid chat_message_id format: Expected spaces/{space_id}/messages/{message_id_segment}.');
            }

            $spaceId = $parts[1];
            $messageIdSegment = $parts[3];
            $messageExploded = explode('.', $messageIdSegment);
            $threadKey = str_contains($messageIdSegment, '.') ? end($messageExploded) : $messageIdSegment;
            $threadNameForReply = 'spaces/' . $spaceId . '/threads/' . $threadKey;

            $response = $this->googleChatService->sendThreadedMessage($project->google_chat_id, $threadNameForReply, $messageText);

            if (!isset($response['thread']['name']) || $response['thread']['name'] !== $threadNameForReply) {
                Log::warning('Message may not have been posted to the correct thread', ['expected_thread' => $threadNameForReply, 'response_thread' => $response['thread']['name'] ?? 'N/A', 'message_id' => $response['name'] ?? 'N/A']);
            }

            $replyNote = $project->notes()->create([
                'content' => $validated['content'],
                'user_id' => Auth::id(),
                'chat_message_id' => $response['name'] ?? null,
                'parent_id' => $note->id,
            ]);
            $replyNote->content = $validated['content']; // Set decrypted content for immediate response

            Log::info('Sent reply to note in Google Chat thread', ['project_id' => $project->id, 'space_name' => $project->google_chat_id, 'thread_name_used' => $threadNameForReply, 'original_chat_message_id' => $note->chat_message_id, 'user_id' => $user->id, 'original_note_id' => $note->id, 'reply_note_id' => $replyNote->id, 'new_chat_message_id' => $response['name'] ?? null]);

            return response()->json(['message' => 'Reply sent successfully', 'note' => $replyNote, 'success' => true]);

        } catch (\Exception $e) {
            Log::error('Failed to send reply to note in Google Chat thread', ['project_id' => $project->id, 'space_name' => $project->google_chat_id, 'note_id' => $note->id, 'error' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['message' => 'Failed to send reply: ' . $e->getMessage(), 'success' => false], 500);
        }
    }

    /**
     * Upload documents to a project.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadDocument(Request $request, Project $project)
    {
        $user = Auth::user();
        if (!$this->canManageProjects($user, $project)) { // Assuming 'update' permission implies document upload
            return response()->json(['message' => 'Unauthorized. You do not have permission to upload documents.'], 403);
        }

        try {
            $validationRules = [
                'documents' => 'required|array',
                'documents.*' => 'required|file|mimes:pdf,doc,docx,jpg,png|max:10240',
            ];
            $validated = $request->validate($validationRules);

            if ($request->hasFile('documents')) {
                // Use the Project model's uploadDocuments method
                $project->uploadDocuments($request->file('documents'), $this->googleDriveService);

                // Load the URL attribute for each document
                $documents = $project->documents()->latest()->get();

                return response()->json([
                    'message' => 'Documents uploaded successfully',
                    'documents' => $documents
                ]);
            }

            return response()->json([
                'message' => 'No documents were uploaded',
                'documents' => $project->documents()->latest()->get()
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading documents: ' . $e->getMessage(), ['project_id' => $project->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to upload documents', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload documents to a project.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadDocuments(Request $request, Project $project)
    {

        // Assuming canManageProjects is a method on your controller or a trait
       $this->authorize('uploadDocuments', $project);

        try {
            $validationRules = [
                'documents' => 'required|array',
                'documents.*' => 'required|file|mimes:pdf,doc,docx,jpg,png|max:10240',
            ];
            $validated = $request->validate($validationRules);

            if ($request->hasFile('documents')) {
                // Loop through each uploaded file and call the Project model's method
                $project->uploadDocuments($request->file('documents'), $this->googleDriveService);

                // Load the URL attribute for each document after all uploads are complete
                $documents = $project->documents()->latest()->get();

                return response()->json([
                    'message' => 'Documents uploaded successfully',
                    'documents' => $documents
                ]);
            }

            return response()->json([
                'message' => 'No documents were uploaded',
                'documents' => $project->documents()->latest()->get()
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading documents: ' . $e->getMessage(), ['project_id' => $project->id, 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to upload documents', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Add a daily standup note to the project and send it to Google Space.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function addStandup(Request $request, Project $project)
    {
        $user = Auth::user();

        $this->authorize('view', $project);

        $validated = $request->validate([
            'yesterday' => 'required|string',
            'today' => 'required|string',
            'blockers' => 'nullable|string',
        ]);

        $formattedContent = "**Daily Standup - " . date('F j, Y') . "**\n\n";
        $formattedContent .= "**Yesterday:** " . $validated['yesterday'] . "\n\n";
        $formattedContent .= "**Today:** " . $validated['today'] . "\n\n";
        $formattedContent .= "**Blockers:** " . ($validated['blockers'] ?? 'None');

        $note = $project->notes()->create([
            'content' => $formattedContent,
            'user_id' => $user->id,
            'type' => 'standup',
        ]);

        if ($project->google_chat_id) {
            try {
                $messageText = "🏃‍♂️ *Daily Standup from {$user->name} - " . date('F j, Y') . "*\n\n";
                $messageText .= "💼 *Yesterday:* " . $validated['yesterday'] . "\n\n";
                $messageText .= "📝 *Today:* " . $validated['today'] . "\n\n";
                $messageText .= "🚧 *Blockers:* " . ($validated['blockers'] ?? 'None');

                $response = $this->googleChatService->sendMessage($project->google_chat_id, $messageText);

                $note->chat_message_id = $response['name'] ?? null;
                $note->save();

                Log::info('Sent standup notification to Google Chat space', ['project_id' => $project->id, 'space_name' => $project->google_chat_id, 'user_id' => $user->id, 'chat_message_id' => $response['name'] ?? null]);
            } catch (\Exception $e) {
                Log::error('Failed to send standup notification to Google Chat space', ['project_id' => $project->id, 'space_name' => $project->google_chat_id, 'error' => $e->getMessage(), 'exception' => $e]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Standup submitted successfully', 'note' => $note], 201);
    }

    /**
     * Update basic project information.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateBasicInfo(Request $request, Project $project)
    {

        try {

            $this->authorize('update', $project);

            $validationRules = [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'website' => 'nullable|url',
                'social_media_link' => 'nullable|url',
                'preferred_keywords' => 'nullable|string',
                'reporting_sites' => 'nullable|string',
                'google_chat_id' => 'nullable|string|max:255',
                'status' => 'sometimes|required|in:active,completed,on_hold,archived',
                'source' => 'nullable|string|max:255',
                'google_drive_link' => 'nullable|url', // Keep this validation
                'project_type'  =>  'required|string|max:30',
                'timezone'  =>  'nullable|string|max:30',
                'project_tier_id'   =>  'required|exists:project_tiers,id',
            ];

            $project->syncTags($request->tags ?? []);

            $validated = $request->validate($validationRules);


            $projectData = [
                'name' => $validated['name'] ?? $project->name,
                'description' => $validated['description'] ?? $project->description,
                'website' => $validated['website'] ?? $project->website,
                'social_media_link' => $validated['social_media_link'] ?? $project->social_media_link,
                'preferred_keywords' => $validated['preferred_keywords'] ?? $project->preferred_keywords,
                'reporting_sites' => $validated['reporting_sites'] ?? $project->reporting_sites,
                'google_chat_id' => $validated['google_chat_id'] ?? $project->google_chat_id,
                'status' => $validated['status'] ?? $project->status,
                'source' => $validated['source'] ?? $project->source,
                'google_drive_link' => $validated['google_drive_link'] ?? $project->google_drive_link,
                'project_type'  =>  $validated['project_type'] ?? 'Unknown',
                'timezone'  =>  $validated['timezone'] ?? null,
                'project_tier_id'   =>  $validated['project_tier_id'] ?? ProjectTier::first()?->id
            ];

            // --- NEW LOGIC FOR GOOGLE DRIVE FOLDER ID ---
            if (isset($validated['google_drive_link'])) {
                $link = $validated['google_drive_link'];
                // Regular expression to extract the folder ID from a Google Drive URL
                // It looks for /folders/ or /file/ followed by the ID.
                if (preg_match('/(?:id=|folders\/|file\/)([a-zA-Z0-9_-]+)/', $link, $matches)) {
                    $extractedFolderId = $matches[1];
                    // Update the google_drive_folder_id in projectData
                    $projectData['google_drive_folder_id'] = $extractedFolderId;
                    Log::info('Extracted Google Drive folder ID from link', [
                        'link' => $link,
                        'extracted_id' => $extractedFolderId
                    ]);
                } else {
                    // If no valid ID could be extracted, set it to null or log a warning
                    $projectData['google_drive_folder_id'] = null;
                    Log::warning('Could not extract Google Drive folder ID from provided link', [
                        'link' => $link,
                        'project_id' => $project->id
                    ]);
                }
            } else if (array_key_exists('google_drive_link', $validated) && $validated['google_drive_link'] === null) {
                // If google_drive_link is explicitly sent as null, clear the folder ID as well
                $projectData['google_drive_folder_id'] = null;
                Log::info('Google Drive link set to null, clearing folder ID.', ['project_id' => $project->id]);
            }
            // --- END NEW LOGIC ---

            $project->update($projectData);

            if($request->hasFile('logo')) {
                $this->uploadLogo($request, $project);
            }

            return response()->json($project->refresh());
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error updating project basic info: ' . $e->getMessage(), ['project_id' => $project->id, 'request' => $request->all(), 'error' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Failed to update project basic info', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update project services and payment information.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateServicesAndPayment(Request $request, Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || !$this->canManageProjectServicesAndPayments($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage financial information.'], 403);
        }

        $validatedData = $request->validate([
            'services' => 'nullable|array',
            'service_details' => 'nullable|array',
            'total_amount' => 'nullable|numeric',
            'payment_type' => 'nullable|string|in:one_off,monthly',
        ]);

        $project->services = $validatedData['services'] ?? $project->services;
        $project->service_details = $validatedData['service_details'] ?? $project->service_details;
        $project->total_amount = $validatedData['total_amount'] ?? $project->total_amount;
        $project->payment_type = $validatedData['payment_type'] ?? $project->payment_type;
        $project->save();

        return response()->json([
            'message' => 'Services and payment information updated successfully',
            'services' => $project->services,
            'service_details' => $project->service_details,
            'total_amount' => $project->total_amount,
            'payment_type' => $project->payment_type,
        ]);
    }

    /**
     * Update project transactions.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignLeads(Request $request, Project $project)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$this->canManageProjects($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to assign project leads.'], 403);
        }

        $validated = $request->validate([
            'project_manager_id' => 'nullable|exists:users,id',
            'project_admin_id' => 'nullable|exists:users,id',
        ]);

        $project->project_manager_id = $validated['project_manager_id'] ?? null;
        $project->project_admin_id = $validated['project_admin_id'] ?? null;
        $project->save();

        $project->load(['manager:id,name,email', 'admin:id,name,email']);

        return response()->json([
            'message' => 'Project leads updated successfully',
            'project_manager_id' => $project->project_manager_id,
            'project_admin_id' => $project->project_admin_id,
            'manager' => $project->manager,
            'admin' => $project->admin,
        ]);
    }

    public function updateTransactions(Request $request, Project $project)
    {
        $user = Auth::user();
        if (!$this->canAccessProject($user, $project) || (!$this->canManageProjectExpenses($user, $project) && !$this->canManageProjectIncome($user, $project))) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage transactions.'], 403);
        }

        $validatedData = $request->validate([
            'transactions' => 'required|array',
            'transactions.*.description' => 'required|string',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.currency' => 'nullable|string',
            'transactions.*.user_id' => 'nullable|exists:users,id',
            'transactions.*.hours_spent' => 'nullable|numeric',
            'transactions.*.type' => 'required|in:expense,income',
        ]);

        $transactions = $validatedData['transactions'];

        if (!$this->canManageProjectExpenses($user, $project)) {
            $transactions = array_filter($transactions, fn ($transaction) => $transaction['type'] !== 'expense');
        }
        if (!$this->canManageProjectIncome($user, $project)) {
            $transactions = array_filter($transactions, fn ($transaction) => $transaction['type'] !== 'income');
        }

        $project->transactions()->delete();

        foreach ($transactions as $transaction) {
            $project->transactions()->create([
                'description' => $transaction['description'],
                'amount' => $transaction['amount'],
                'user_id' => $transaction['user_id'] ?? null,
                'hours_spent' => $transaction['hours_spent'] ?? null,
                'type' => $transaction['type'],
                'currency' => $transaction['currency'] ?? null,
            ]);
        }

        $project->load('transactions');
        return response()->json(['message' => 'Transactions updated successfully', 'transactions' => $project->transactions]);
    }

    /**
     * Update project notes.
     * This method in ProjectSectionController was named `updateNotes` but had no logic,
     * so it's being implemented to allow updating/managing existing notes.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateNotes(Request $request, Project $project)
    {
        $user = Auth::user();
        // Assuming 'manage_project_notes' permission, or 'add_project_notes' for adding/updating.
        if (!$this->canAddProjectNotes($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage notes.'], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|array',
            'notes.*.id' => 'nullable|exists:project_notes,id', // For existing notes
            'notes.*.content' => 'required|string',
            'notes.*.type' => 'nullable|string', // Allow updating type if needed
            'notes.*.chat_message_id' => 'nullable|string',
            'notes.*.parent_id' => 'nullable|exists:project_notes,id',
            'notes.*.delete' => 'nullable|boolean', // Flag to indicate deletion'
        ]);

        $incomingNotes = collect($validated['notes'] ?? []);
        $noteType = $request->type;
        // Process existing notes (update or delete)
        foreach ($incomingNotes as $noteData) {

            if (isset($noteData['id'])) {
                $note = $project->notes()->find($noteData['id']);
                if ($note) {
                    if (isset($noteData['delete']) && $noteData['delete']) {
                        $note->delete();
                    } else {
                        $note->update([
                            'content' => $noteData['content'],
                            'type' => $noteType ?? $noteData['type'] ?? $note->type,
                            'chat_message_id' => $noteData['chat_message_id'] ?? $note->chat_message_id,
                            'parent_id' => $noteData['parent_id'] ?? $note->parent_id,
                            // user_id is usually not changed on update
                        ]);
                    }
                }
            } else {
                // This is a new note to be added. Re-use addNotes logic or create here.
                $project->notes()->create([
                    'content' => $noteData['content'],
                    'user_id' => Auth::id(),
                    'type' => $noteType ?? $noteData['type'] ?? 'note',
                    'chat_message_id' => $noteData['chat_message_id'] ?? null,
                    'parent_id' => $noteData['parent_id'] ?? null,
                ]);
            }
        }

        // Reload and return all notes for the project
        $project->load(['notes' => function($query) use($noteType) {
            $query->whereNull('parent_id')->with('user');
            if($noteType) {
                $query->where('type', $noteType);
            }
        }]);

        $project->notes->each(function ($note) {
            $note->reply_count = $note->replyCount();
        });

        return response()->json([
            'message' => 'Project notes updated successfully',
            'notes' => $project->notes,
        ]);
    }

    /**
     * Create a new meeting for a project.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function createProjectMeeting(Request $request, Project $project)
    {
        $user = Auth::user();
        // Assuming `canManageProjects` or a more specific `canScheduleMeetings` permission
        if (!$this->canManageProjects($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to schedule meetings.'], 403);
        }

        $response = $this->projectCalendarController->createProjectMeeting($request, $project);

        if ($response->getStatusCode() === 201) {
            $data = json_decode($response->getContent(), true);

            $timezone = $request->input('timezone') ?? config('app.timezone');
            $startTime = $request->input('start_datetime');
            $endTime = $request->input('end_datetime');

            $startTimeUtc = \Carbon\Carbon::parse($startTime, $timezone)->setTimezone('UTC');
            $endTimeUtc = \Carbon\Carbon::parse($endTime, $timezone)->setTimezone('UTC');

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
                'is_utc' => true,
            ]);

            $meeting->save();
            $data['meeting_id'] = $meeting->id;

            // Save attendees if provided and send notifications
            if ($request->has('attendee_user_ids') && is_array($request->input('attendee_user_ids'))) {
                $attendeeIds = $request->input('attendee_user_ids');
                foreach ($attendeeIds as $attendeeId) {
                    $attendee = $meeting->attendees()->create([
                        'user_id' => $attendeeId,
                        'notification_sent' => false,
                    ]);

                    // Send notification to the attendee
                    $user = \App\Models\User::find($attendeeId);
                    if ($user) {
                        $user->notify(new \App\Notifications\MeetingInvitation($meeting, $attendee));

                        // Update notification status
                        $attendee->notification_sent = true;
                        $attendee->notification_sent_at = now();
                        $attendee->save();
                    }
                }
            }

            return response()->json($data, 201);
        }

        return $response;
    }

    /**
     * Delete a meeting for a project.
     *
     * @param Request $request
     * @param Project $project
     * @param string $googleEventId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProjectMeeting(Request $request, Project $project, string $googleEventId)
    {
        $user = Auth::user();
        // Assuming `canManageProjects` or a more specific `canDeleteMeetings` permission
        if (!$this->canManageProjects($user, $project)) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to delete meetings.'], 403);
        }

        $meeting = $project->meetings()->where('google_event_id', $googleEventId)->first();
        $response = $this->projectCalendarController->deleteProjectMeeting($request, $project, $googleEventId);

        if ($response->getStatusCode() === 200 && $meeting) {
            $meeting->delete();
        }

        return $response;
    }

    /**
     * Upload a logo for a project.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadLogo(Request $request, Project $project)
    {

//        try {
            $validationRules = [
                'logo' => 'required|image|max:2048', // Max 2MB
            ];

            $validated = $request->validate($validationRules);

            if ($request->hasFile('logo')) {
                // Delete existing logo if it exists
                if ($project->logo) {
                    Storage::disk('public')->delete($project->logo);
                }

                // Store the new logo
                $localPath = $request->file('logo')->store('logos', 'public');
                $project->logo = $localPath;
                $project->save();

                Log::info('Generated Logo URL: ' . asset($project->logo));

                return response()->json([
                    'message' => 'Logo uploaded successfully',
                    'logo' => $project->logo
                ]);
            }

            return response()->json([
                'message' => 'No logo was uploaded',
            ], 400);
//        } catch (ValidationException $e) {
//            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
//        } catch (\Exception $e) {
//            Log::error('Error uploading logo: ' . $e->getMessage(), ['project_id' => $project->id, 'error' => $e->getTraceAsString()]);
//            return response()->json(['message' => 'Failed to upload logo', 'error' => $e->getMessage()], 500);
//        }
    }

    /**
     * Upload documents from the client dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadClientDocuments(Request $request)
    {
        $authenticatedProjectId = $request->attributes->get('magic_link_project_id');
        $authenticatedClientEmail = $request->attributes->get('magic_link_email');

        // Verify the project exists and is accessible
        $project = Project::find($authenticatedProjectId);
        if (!$project) {
            return response()->json(['message' => 'Project not found or unauthorized.'], 403);
        }

        try {
            $validationRules = [
                'documents' => 'required|array',
                'documents.*' => 'required|file|mimes:pdf,doc,docx,jpg,png,jpeg|max:10240', // Max 10MB
            ];
            $validated = $request->validate($validationRules);
            $uploadedDocuments = [];

            if ($request->hasFile('documents')) {
                // Get the client who is uploading
                $client = Client::where('email', $authenticatedClientEmail)->first();
                if (!$client) {
                    return response()->json(['message' => 'Client not found.'], 404);
                }

                foreach ($request->file('documents') as $file) {
                    // Store locally first
                    $localPath = $file->store('client_documents', 'public'); // Store in 'client_documents' folder
                    $fullLocalPath = Storage::disk('public')->path($localPath);
                    $originalFilename = $file->getClientOriginalName();
                    $mimeType = $file->getMimeType();
                    $fileSize = $file->getSize();

                    $documentData = [
                        'project_id' => $project->id,
                        'client_id' => $client->id, // Associate with the client who uploaded
                        'path' => $localPath,
                        'filename' => $originalFilename,
                        'mime_type' => $mimeType,
                        'file_size' => $fileSize,
                        'is_client_uploaded' => true, // Flag to differentiate client uploads
                    ];

                    try {
                        // Upload to Google Drive if project has a folder ID
                        if ($project->google_drive_folder_id) {
                            $response = $this->googleDriveService->uploadFile($fullLocalPath, $originalFilename, $project->google_drive_folder_id);
                            $documentData['google_drive_file_id'] = $response['id'] ?? null;
                            $documentData['path'] = $response['path'] ?? null;
                            $documentData['thumbnail'] = $response['thumbnail'] ?? null;

                            // Optionally, add permission for this client to the Google Drive file
                            // This might require your service account to have appropriate delegation
                            /*
                            $this->googleDriveService->addPermission(
                                $fileId,
                                $authenticatedClientEmail,
                                'reader', // or 'writer' if they need to edit after upload
                                'user'
                            );
                            */
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to upload file to Google Drive: ' . $e->getMessage(), [
                            'project_id' => $project->id,
                            'file_name' => $originalFilename,
                            'error' => $e->getTraceAsString()
                        ]);
                        $documentData['upload_error'] = 'Failed to upload to Google Drive';
                        // Optionally, if Google Drive upload fails, delete the locally stored file
                        Storage::disk('public')->delete($localPath);
                        throw new \Exception("Failed to upload '{$originalFilename}' to Google Drive: " . $e->getMessage());
                    }

                    $document = \App\Models\Document::create($documentData);
                    $uploadedDocuments[] = $document;
                }

                // Append URL attribute for the newly uploaded documents
                collect($uploadedDocuments)->each(function ($doc) {
                    $doc->append('url');
                });

                return response()->json([
                    'message' => 'Documents uploaded successfully',
                    'documents' => $uploadedDocuments // Return the newly uploaded documents
                ]);
            }

            return response()->json([
                'message' => 'No documents were uploaded',
                'documents' => []
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error uploading documents: ' . $e->getMessage(), [
                'project_id' => $authenticatedProjectId,
                'email' => $authenticatedClientEmail,
                'error' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to upload documents: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update expendable budget for a project (amount and currency)
     */
    public function updateExpendableBudget(Request $request, Project $project)
    {
        // Authorization aligned with financial operations
        $this->authorize('manageTransactions', $project);

        $validated = $request->validate([
            'total_expendable_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
        ]);

        $project->update([
            'total_expendable_amount' => $validated['total_expendable_amount'],
            'currency' => strtoupper($validated['currency']),
        ]);

        return response()->json([
            'message' => 'Expendable budget updated successfully',
            'total_expendable_amount' => $project->total_expendable_amount,
            'currency' => $project->currency,
        ]);
    }

    /**
     * Archive a project (soft delete).
     *
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function archive(Project $project)
    {
        try {
            // Check if user has permission to archive projects
            $user = Auth::user();
            if (!$user->can('delete', $project)) {
                return response()->json([
                    'message' => 'You do not have permission to archive this project.',
                    'success' => false
                ], 403);
            }

            $project->delete(); // This will soft delete the project

            return response()->json([
                'message' => 'Project archived successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Error archiving project: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'error' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to archive project: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Restore an archived project.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        try {
            // Find the project with trashed (soft deleted) records
            $project = Project::withTrashed()->findOrFail($id);

            // Check if user has permission to restore projects
            $user = Auth::user();
            if (!$user->can('restore', $project)) {
                return response()->json([
                    'message' => 'You do not have permission to restore this project.',
                    'success' => false
                ], 403);
            }

            $project->restore(); // Restore the soft deleted project

            return response()->json([
                'message' => 'Project restored successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Error restoring project: ' . $e->getMessage(), [
                'project_id' => $id,
                'error' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to restore project: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Add a wireframe comment for internal users (auth:sanctum). Accepts optional parent_id.
     * POST /api/projects/{projectId}/wireframes/{id}/comments
     */
    public function addWireframeComment(Request $request, $projectId, $id)
    {
        try {
            $user = Auth::user();
            $project = Project::findOrFail($projectId);

            if (!$this->canAccessProject($user, $project) || !$this->canViewProjectNotes($user, $project)) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to add comments.'], 403);
            }

            // Validate
            $validated = $request->validate([
                'text' => 'required|string|max:2000',
                'context' => 'nullable',
                'parent_id' => 'nullable|integer'
            ]);

            // Ensure wireframe belongs to project
            $wireframe = $project->wireframes()->where('id', $id)->first();
            if (!$wireframe) {
                return response()->json(['message' => 'Wireframe not found.'], 404);
            }

            // If parent_id provided, ensure it belongs to same wireframe
            $parent = null;
            if (!empty($validated['parent_id'])) {
                $parent = ProjectNote::where('id', $validated['parent_id'])
                    ->where('project_id', $project->id)
                    ->where('noteable_type', get_class($wireframe))
                    ->where('noteable_id', $wireframe->id)
                    ->first();
                if (!$parent) {
                    return response()->json(['message' => 'Invalid parent comment for this wireframe.'], 422);
                }
            }

            $note = new ProjectNote([
                'project_id' => $project->id,
                'content' => $validated['text'],
                'type' => ProjectNote::COMMENT,
                'noteable_id' => $wireframe->id,
                'noteable_type' => get_class($wireframe),
                'parent_id' => $validated['parent_id'] ?? null,
                'context' => $validated['context'] ?? null,
            ]);

            $note->save();

            return response()->json([
                'message' => 'Comment added successfully.',
                'comment' => $note,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error adding wireframe comment (internal): ' . $e->getMessage(), [
                'project_id' => $projectId,
                'wireframe_id' => $id,
                'error' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to add comment.'], 500);
        }
    }

    /**
     * Mark a wireframe comment as resolved by updating note type to 'resolved_comment'.
     * POST /api/projects/{projectId}/wireframes/{id}/comments/{commentId}/resolved_comment
     */
    public function resolveWireframeComment(Request $request, $projectId, $id, $commentId)
    {
        try {
            $user = Auth::user();
            $project = Project::findOrFail($projectId);

            if (!$this->canAccessProject($user, $project) || !$this->canViewProjectNotes($user, $project)) {
                return response()->json(['message' => 'Unauthorized. You do not have permission to update comments.'], 403);
            }

            // Ensure wireframe belongs to project
            $wireframe = $project->wireframes()->where('id', $id)->first();
            if (!$wireframe) {
                return response()->json(['message' => 'Wireframe not found.'], 404);
            }

            // Find the comment and ensure it belongs to this project and wireframe
            $note = ProjectNote::where('id', $commentId)
                ->where('project_id', $project->id)
                ->where('noteable_type', get_class($wireframe))
                ->where('noteable_id', $wireframe->id)
                ->first();

            if (!$note) {
                return response()->json(['message' => 'Comment not found.'], 404);
            }

            // Allow toggling between 'comment' and 'resolved_comment' only
            if (!in_array($note->type, [ProjectNote::COMMENT, 'resolved_comment'])) {
                return response()->json(['message' => 'Only wireframe comments can be marked as resolved.'], 422);
            }

            // Update type to resolved_comment
            $note->type = 'resolved_comment';
            $note->save();

            return response()->json([
                'message' => 'Comment marked as resolved.',
                'comment' => $note,
            ]);
        } catch (\Exception $e) {
            Log::error('Error resolving wireframe comment (internal): ' . $e->getMessage(), [
                'project_id' => $projectId,
                'wireframe_id' => $id,
                'comment_id' => $commentId,
                'error' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to update comment.'], 500);
        }
    }
}
