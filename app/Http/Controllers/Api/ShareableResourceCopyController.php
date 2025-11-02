<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ShareableResource;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShareableResourceCopyController extends Controller
{
    public function copyToProject(Request $request, ShareableResource $resource, GoogleDriveService $drive)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'source_url' => ['nullable', 'string'],
            'new_name' => ['nullable', 'string', 'max:255'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        $sourceUrl = $data['source_url'] ?? $resource->url;

        if (! $sourceUrl) {
            return response()->json(['message' => 'No source URL found for this resource.'], 422);
        }

        $docId = $this->extractGoogleDocId($sourceUrl);
        if (! $docId) {
            return response()->json(['message' => 'Could not extract Google Document ID from the provided URL.'], 422);
        }

        // Handle correct field and potential legacy typo
        $destinationFolderId = $project->google_drive_folder_id ?? $project->google_drive_foler_id ?? null;
        if (! $destinationFolderId) {
            return response()->json(['message' => 'Project does not have a Google Drive folder configured.'], 422);
        }

        $newName = $data['new_name'] ?? ($resource->title ? ($resource->title.' (Copy)') : 'Copied Document');

        try {
            $result = $drive->copyFile($docId, $destinationFolderId, $newName);

            return response()->json([
                'id' => $result['id'] ?? null,
                'link' => $result['link'] ?? null,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Failed to copy Google Doc', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Failed to copy file.'], 500);
        }
    }

    /**
     * [DEBUG] A new method to test service account file visibility.
     */
    public function debugListFiles(GoogleDriveService $drive)
    {
        try {
            $files = $drive->listRootFilesAndFolders();

            return response()->json($files);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to list files: '.$e->getMessage()], 500);
        }
    }

    private function extractGoogleDocId(string $url): ?string
    {
        $matches = [];
        // This regex looks for the string of characters between /d/ and the next /
        preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $url, $matches);

        return $matches[1] ?? null;
    }
}
