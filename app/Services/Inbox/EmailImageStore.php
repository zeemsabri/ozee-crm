<?php

namespace App\Services\Inbox;

use App\Http\Controllers\Api\Concerns\HandlesImageUploads;
use App\Models\Email;
use App\Models\FileAttachment;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stores and retrieves the images used by the inbox's block builder.
 *
 * Three things this owns, all of which have a sharp edge:
 *
 * 1. **Where they go.** GCS under `email-blocks/`, deliberately a different prefix from
 *    the `task/` one everything else uses. The prune command keys off that prefix, so a
 *    task attachment can never be swept up by it even if someone sets `expires_at` on one.
 *
 * 2. **Expiry.** Every upload gets `expires_at = now + config('inbox.blocks.image_ttl_days')`
 *    (default 180). Safe because the copy that matters is embedded in the sent message as
 *    a CID part — ours is only a working copy for the composer and for rendering the
 *    thread in our own UI. Existing rows have a null `expires_at` and are never pruned.
 *
 * 3. **Ownership before the email exists.** `files.fileable_id`/`fileable_type` are NOT
 *    NULL, but images are uploaded while composing, before there is an Email to attach to.
 *    They are parented to the Project first and re-pointed by `attachTo()` once the email
 *    is created. An abandoned composer therefore leaves project-owned images, which is
 *    what the TTL is for.
 */
class EmailImageStore
{
    use HandlesImageUploads;

    /** Keep block images out of the `task/` prefix so pruning can never touch task files. */
    private const PREFIX = 'email-blocks';

    /** In-request cache — a render asks for the same file for src, cid and alt. */
    private array $cache = [];

    /**
     * @param  UploadedFile[]  $files
     * @return FileAttachment[]
     */
    public function store(array $files, Project $project): array
    {
        $uploaded = $this->uploadFilesToGcsWithThumbnails($files, self::PREFIX);
        $ttl = (int) config('inbox.blocks.image_ttl_days', 180);

        return array_map(function (array $entry) use ($project, $ttl) {
            return FileAttachment::create([
                ...$entry,
                'fileable_type' => Project::class,
                'fileable_id' => $project->id,
                'project_id' => $project->id,
                'expires_at' => now()->addDays($ttl),
            ]);
        }, $uploaded);
    }

    /**
     * Re-point images at the email that now owns them.
     *
     * Only ever moves rows that are still parented to the project and live under our own
     * prefix, so a malicious or stale id in a block cannot re-parent someone else's file.
     *
     * @param  array<int>  $fileIds
     */
    public function attachTo(Email $email, array $fileIds): void
    {
        $ids = array_filter(array_map('intval', $fileIds));

        if (! $ids) {
            return;
        }

        FileAttachment::query()
            ->whereIn('id', $ids)
            ->where('fileable_type', Project::class)
            ->where('path', 'like', self::PREFIX.'/%')
            ->update([
                'fileable_type' => Email::class,
                'fileable_id' => $email->id,
            ]);
    }

    /**
     * Resolve a block image by id.
     *
     * @param  array<int>|null  $projectIds  when given, the file must belong to one of
     *                                       these projects. Pass the caller's composable
     *                                       projects on anything driven by user input —
     *                                       ids are sequential, so without this someone
     *                                       could enumerate another project's images and
     *                                       get a signed URL back from the preview
     *                                       endpoint. Null (the default) is for the send
     *                                       path, where the ids were validated when the
     *                                       email was submitted.
     * @return FileAttachment|null
     */
    public function find($id, ?array $projectIds = null)
    {
        if (! $id) {
            return null;
        }

        $id = (int) $id;
        $key = $id.'|'.($projectIds === null ? '*' : implode(',', $projectIds));

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $query = FileAttachment::query()
            // Only ever resolves our own block images. A block carrying the id of an
            // unrelated attachment resolves to null rather than embedding someone else's
            // file into an outgoing client email.
            ->where('path', 'like', self::PREFIX.'/%');

        if ($projectIds !== null) {
            $query->whereIn('project_id', $projectIds ?: [0]);
        }

        return $this->cache[$key] = $query->find($id);
    }

    /**
     * A URL our own UI can render.
     *
     * This is the 24-hour GCS signed URL from `FileAttachment::$path_url`, which is fine
     * for a page the user has open and completely unfit for an email — an emailed image
     * uses a CID part instead. Do not be tempted to put this in a message body.
     */
    public function previewUrl(FileAttachment $file): ?string
    {
        return $file->path_url;
    }

    /**
     * The raw bytes, for building the MIME part at send time.
     *
     * Null on any failure — a missing object must degrade to "no image in the email",
     * never to a failed send.
     */
    public function bytes(FileAttachment $file): ?string
    {
        try {
            return Storage::disk('gcs')->get($file->path);
        } catch (Throwable $e) {
            Log::warning('inbox: could not read a block image for sending.', [
                'file_id' => $file->id,
                'path' => $file->path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
