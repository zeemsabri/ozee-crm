<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Inbox\EmailAttachmentStore;
use App\Services\Inbox\InboxAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Uploads file and image attachments for emails.
 *
 * Uploaded files get an initial parent and a 180-day TTL, and are re-pointed to
 * the Email model once the email is created.
 */
class InboxAttachmentController extends Controller
{
    public function __construct(
        private readonly EmailAttachmentStore $store,
        private readonly InboxAccess $access,
    ) {}

    /** POST /api/inbox/attachments */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        // 25MB limit per file matching MAX_ATTACHMENT_BYTES in ComposeModal.jsx
        $maxKb = 25 * 1024;

        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['required', 'file', 'max:'.$maxKb],
        ], [
            'files.*.max' => 'Each attached file must be under 25MB.',
        ]);

        $project = null;
        if (! empty($data['project_id'])) {
            $projectId = (int) $data['project_id'];
            if (! in_array($projectId, $this->access->composableProjectIds($user), true)) {
                abort(403, 'You cannot compose on that project.');
            }
            $project = Project::findOrFail($projectId);
        }

        $uploaded = $this->store->store($request->file('files'), $project, $user);

        return response()->json([
            'data' => array_map(fn ($file) => [
                'file_id' => $file->id,
                'id' => $file->id,
                'filename' => $file->filename,
                'name' => $file->filename,
                'mime_type' => $file->mime_type,
                'size' => $file->file_size,
                'url' => $file->path_url,
                'thumbnail_url' => $file->thumbnail_url,
                'expires_at' => $file->expires_at?->toIso8601String(),
            ], $uploaded),
        ], 201);
    }
}
