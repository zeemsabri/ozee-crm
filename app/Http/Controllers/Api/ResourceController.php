<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Resource;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ResourceController extends Controller
{
    protected GoogleDriveService $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Get all resources for a project.
     *
     * @param  int  $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $projectId)
    {
        try {
            $project = Project::findOrFail($projectId);

            // Check if user has permission to view the project
            // This assumes you have a policy or other authorization mechanism
            // $this->authorize('view', $project);

            $resources = $project->resources;

            return response()->json([
                'success' => true,
                'resources' => $resources,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching resources: '.$e->getMessage(), [
                'project_id' => $projectId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch resources: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new resource.
     *
     * @param  int  $projectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $projectId)
    {
        try {
            $project = Project::findOrFail($projectId);

            // Check if user has permission to update the project
            // $this->authorize('update', $project);

            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|in:link,file',
                'url' => 'required_if:type,link|nullable|url|max:2048',
                'file' => 'required_if:type,file|nullable|file|max:10240', // 10MB max
                'description' => 'nullable|string|max:1000',
                'requires_approval' => 'nullable|boolean',
                'visible_to_client' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            // Create the resource based on type
            $resource = new Resource([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
                'requires_approval' => $validated['requires_approval'] ?? false,
                'visible_to_client' => $validated['visible_to_client'] ?? false,
            ]);

            if ($validated['type'] === 'link') {
                $resource->url = $validated['url'];
            } else {
                // Handle file upload to Google Drive
                $file = $request->file('file');
                $fileName = $file->getClientOriginalName();
                $filePath = $file->getPathname();

                // Upload to Google Drive
                $googleResponse = $this->googleDriveService->uploadFile(
                    $filePath,
                    $fileName,
                    $project->google_drive_folder_id
                );

                if (! isset($googleResponse['id'])) {
                    throw new \Exception('Failed to upload file to Google Drive');
                }
                $resource->url = "https://drive.google.com/file/d/{$googleResponse['id']}/view";
            }

            // Save the resource
            $project->resources()->save($resource);

            return response()->json([
                'success' => true,
                'message' => 'Resource created successfully',
                'resource' => $resource,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating resource: '.$e->getMessage(), [
                'project_id' => $projectId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create resource: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific resource.
     *
     * @param  int  $projectId
     * @param  int  $resourceId
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $projectId, $resourceId)
    {
        try {
            $project = Project::findOrFail($projectId);

            // Check if user has permission to view the project
            // $this->authorize('view', $project);

            $resource = $project->resources()->findOrFail($resourceId);

            return response()->json([
                'success' => true,
                'resource' => $resource,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching resource: '.$e->getMessage(), [
                'project_id' => $projectId,
                'resource_id' => $resourceId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch resource: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a resource.
     *
     * @param  int  $projectId
     * @param  int  $resourceId
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $projectId, $resourceId)
    {
        try {
            $project = Project::findOrFail($projectId);

            // Check if user has permission to update the project
            // $this->authorize('update', $project);

            $resource = $project->resources()->findOrFail($resourceId);

            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'url' => 'sometimes|required_if:type,link|nullable|url|max:2048',
                'requires_approval' => 'nullable|boolean',
                'visible_to_client' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            // Update the resource
            if (isset($validated['name'])) {
                $resource->name = $validated['name'];
            }

            if (isset($validated['description'])) {
                $resource->description = $validated['description'];
            }

            if ($resource->type === 'link' && isset($validated['url'])) {
                $resource->url = $validated['url'];
            }

            // Update approval and visibility settings if provided
            if (isset($validated['requires_approval'])) {
                $resource->requires_approval = $validated['requires_approval'];
            }

            if (isset($validated['visible_to_client'])) {
                $resource->visible_to_client = $validated['visible_to_client'];
            }

            $resource->save();

            return response()->json([
                'success' => true,
                'message' => 'Resource updated successfully',
                'resource' => $resource,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating resource: '.$e->getMessage(), [
                'project_id' => $projectId,
                'resource_id' => $resourceId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update resource: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a resource.
     *
     * @param  int  $projectId
     * @param  int  $resourceId
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $projectId, $resourceId)
    {
        try {
            $project = Project::findOrFail($projectId);

            // Check if user has permission to update the project
            // $this->authorize('update', $project);

            $resource = $project->resources()->findOrFail($resourceId);

            // If it's a file, delete it from Google Drive
            if ($resource->type === 'file' && $resource->file_id) {
                $this->googleDriveService->deleteFile($resource->file_id);
            }

            // Delete the resource
            $resource->delete();

            return response()->json([
                'success' => true,
                'message' => 'Resource deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting resource: '.$e->getMessage(), [
                'project_id' => $projectId,
                'resource_id' => $resourceId,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete resource: '.$e->getMessage(),
            ], 500);
        }
    }
}
