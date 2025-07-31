<?php

namespace App\Http\Controllers\Api\ProjectDashboard;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\Project;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth; // Import Auth facade

class ProjectDeliverableAction extends Controller
{
    /**
     * GoogleDriveService instance.
     *
     * @var GoogleDriveService
     */
    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Display a listing of deliverables for a specific project.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Project $project)
    {
        // Ensure the authenticated user has access to this project
        // This check might be handled by a middleware like 'project.access'
        // If not, you'd add: if (!$project->users->contains(Auth::id())) { abort(403); }

        try {
            $deliverables = $project->deliverables()
                ->with('teamMember') // Eager load the team member (user)
                ->orderBy('submitted_at', 'desc')
                ->get();

            return response()->json($deliverables);
        } catch (\Exception $e) {
            Log::error("Error fetching project deliverables for CRM: {$e->getMessage()}", [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'error_trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to fetch deliverables.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created deliverable in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Project $project)
    {

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:2000',
                'type' => 'required|string|in:blog_post,design_mockup,social_media_post,report,contract_draft,proposal,other',
                'content_url' => 'nullable|url|max:2000',
                'attachment_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB
                'is_visible_to_client' => 'boolean',
                'initial_status' => 'required|string|in:pending_review,for_information', // NEW: Validate initial status
                'content_url_type' => 'nullable|string|in:google_doc,pdf,image,video,other|required_with:content_url',
            ]);

            // Ensure only one of content_url or attachment_file is provided
            if (!empty($validated['content_url']) && $request->hasFile('attachment_file')) {
                throw ValidationException::withMessages([
                    'content_url' => 'Cannot provide both a content URL and an attachment file.',
                    'attachment_file' => 'Cannot provide both a content URL and an attachment file.',
                ]);
            }

            if (empty($validated['content_url']) && !$request->hasFile('attachment_file')) {
                throw ValidationException::withMessages([
                    'content_url' => 'Either a content URL or an attachment file is required.',
                    'attachment_file' => 'Either a content URL or an attachment file is required.',
                ]);
            }

            $attachmentPath = null;
            $contentUrl = $validated['content_url'] ?? null;
            $contentUrlType = $validated['content_url_type'] ?? null;

            // Handle file upload to Google Drive if an attachment is provided
            if ($request->hasFile('attachment_file')) {

                $file = $request->file('attachment_file');

                // Get the file's mime type
                $file = $request->file('attachment_file');
                $contentUrlType = $file->getMimeType();

                $explodedType = explode('/', $contentUrlType);

                if(ISSET($explodedType[1])) {
                    $contentUrlType = $explodedType[1];
                }

                // Get the project's Google Drive folder ID (assuming it's stored on the Project model)
                $projectFolderId = $project->google_drive_folder_id;

                if (!$projectFolderId) {
                    Log::error("Google Drive folder ID not found for project: {$project->id}");
                    return response()->json(['message' => 'Google Drive folder not configured for this project.'], 500);
                }

                // Upload the file
                $uploadedFile = $project->uploadDocuments([$file], $this->googleDriveService, 'webViewLink');

                if (ISSET($uploadedFile[0]) && isset($uploadedFile[0]['path'])) {
                    $attachmentPath = $uploadedFile[0]['path']; // Store the web view link
                    $contentUrl = $uploadedFile[0]['path'];
                } else {
                    throw new \Exception('Failed to upload file to Google Drive.');
                }
            }

            // Create the deliverable record
            $deliverable = $project->deliverables()->create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'type' => $validated['type'],
                'content_url' => $contentUrl, // Use the uploaded file link or the provided URL
                'attachment_path' => $attachmentPath, // Only for uploaded files
                'mime_type' => $contentUrlType, // Save the mime type for uploaded files
                'team_member_id' => Auth::id(), // AUTOMATICALLY ASSIGN AUTHENTICATED USER
                'submitted_at' => now(),
                'is_visible_to_client' => $validated['is_visible_to_client'],
                'status' => $validated['initial_status'], // Use the selected initial status
                'version' => 1, // Always start with version 1 for new deliverables
            ]);

            return response()->json([
                'message' => 'Deliverable created successfully.',
                'deliverable' => $deliverable->load('teamMember'), // Load team member for frontend display
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Error creating deliverable: {$e->getMessage()}", [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Failed to create deliverable.', 'error' => $e->getMessage()], 500);
        }
    }
}
