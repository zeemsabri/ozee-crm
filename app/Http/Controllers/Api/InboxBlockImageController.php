<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Inbox\BlockComposition;
use App\Services\Inbox\BlockRenderer;
use App\Services\Inbox\EmailImageStore;
use App\Services\Inbox\InboxAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Uploads for the block builder's image blocks.
 *
 * A separate endpoint from `POST /api/files` because that one hard-refuses any model whose
 * project has no `google_drive_folder_id` — which is every Email — and because these
 * images need `expires_at` set and their own storage prefix. See EmailImageStore.
 *
 * Images are uploaded while composing, before an Email row exists, so they are parented to
 * the project and re-pointed at the email once it is created.
 */
class InboxBlockImageController extends Controller
{
    public function __construct(
        private readonly EmailImageStore $images,
        private readonly InboxAccess $access,
        private readonly BlockRenderer $renderer,
        private readonly BlockComposition $blocks,
    ) {}

    /** POST /api/inbox/block-images */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $maxMb = (int) config('inbox.blocks.max_image_mb', 5);
        $mimes = (array) config('inbox.blocks.image_mimes', ['image/jpeg', 'image/png', 'image/gif']);

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'images' => ['required', 'array', 'max:10'],
            // mimetypes (not `mimes`) so the check is on the sniffed type, not the
            // extension. GD cannot thumbnail webp or svg — HandlesImageUploads throws
            // outright — and SVG in an email is both unsupported and a script vector.
            'images.*' => ['required', 'file', 'mimetypes:'.implode(',', $mimes), 'max:'.($maxMb * 1024)],
        ], [
            'images.*.mimetypes' => 'Images must be JPEG, PNG or GIF.',
            'images.*.max' => "Each image must be under {$maxMb}MB.",
        ]);

        // Same gate as composing itself: if you cannot write the email, you cannot upload
        // into it. Also confirms the project is one this user may compose on, so the
        // upload cannot be used to drop files into an arbitrary project.
        if (! $this->access->canComposeTemplate($user) && ! $this->access->canComposeCustom($user)) {
            abort(403, 'You do not have permission to compose emails.');
        }

        $projectId = (int) $data['project_id'];

        if (! in_array($projectId, $this->access->composableProjectIds($user), true)) {
            abort(403, 'You cannot compose on that project.');
        }

        $files = $this->images->store($request->file('images'), Project::findOrFail($projectId));

        return response()->json([
            'data' => array_map(fn ($file) => [
                'file_id' => $file->id,
                'filename' => $file->filename,
                'mime_type' => $file->mime_type,
                'size' => $file->file_size,
                // For the composer's preview only. This is a 24-hour signed GCS URL and
                // must never reach an email body — the sent message embeds the bytes.
                'url' => $this->images->previewUrl($file),
                'expires_at' => $file->expires_at?->toIso8601String(),
            ], $files),
        ], 201);
    }

    /**
     * POST /api/inbox/blocks/preview — the client's-eye view of a set of blocks.
     *
     * Rendered by the same BlockRenderer the send path uses, so what the composer shows is
     * the markup the client receives, not a second implementation of it in JavaScript that
     * would drift the first time either side changed. The only difference is MODE_PREVIEW:
     * `cid:` references become signed GCS URLs, because a browser cannot resolve a
     * Content-ID.
     *
     * Nothing is stored. This renders whatever was posted, after the same sanitise() the
     * submit path applies — so a block that would be dropped on send is not shown here
     * either, and the preview cannot promise something the email will not deliver.
     */
    public function preview(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $this->access->canComposeTemplate($user) && ! $this->access->canComposeCustom($user)) {
            abort(403, 'You do not have permission to compose emails.');
        }

        $data = $request->validate([
            'blocks' => ['present', 'array', 'max:60'],
            'blocks.*.type' => ['required', 'string'],
            'blocks.*.text' => ['nullable', 'string', 'max:20000'],
            'blocks.*.label' => ['nullable', 'string', 'max:200'],
            'blocks.*.url' => ['nullable', 'string', 'max:2000'],
            'blocks.*.alt' => ['nullable', 'string', 'max:200'],
            'blocks.*.file_id' => ['nullable', 'integer'],
        ]);

        $blocks = $this->blocks->sanitise($data['blocks'], $this->access->composableProjectIds($user));

        return response()->json([
            'html' => $this->renderer->render($blocks, BlockRenderer::MODE_PREVIEW),
            // Which blocks survived sanitising, so the builder can flag the ones that did
            // not rather than silently showing fewer than the author added.
            'kept' => count($blocks),
            'posted' => count($data['blocks']),
        ]);
    }
}
