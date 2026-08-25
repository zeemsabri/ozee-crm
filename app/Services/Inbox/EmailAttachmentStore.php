<?php

namespace App\Services\Inbox;

use App\Http\Controllers\Api\Concerns\HandlesImageUploads;
use App\Models\Email;
use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stores and retrieves file and image attachments for emails.
 *
 * Uploaded files get an initial parent (Project if available, otherwise User) with a 180-day
 * expiration TTL. Once the Email is created, attachTo() re-points them to the Email model.
 * At send time, attachmentPartsFor() fetches raw bytes from GCS for building MIME attachments.
 */
class EmailAttachmentStore
{
    use HandlesImageUploads;

    public const PREFIX = 'email-attachments';

    /**
     * Store uploaded attachment files on GCS with a TTL.
     *
     * @param  UploadedFile[]  $files
     * @return FileAttachment[]
     */
    public function store(array $files, ?Project $project = null, ?User $user = null): array
    {
        $uploaded = $this->uploadFilesToGcsWithThumbnails($files, self::PREFIX);
        $ttl = (int) config('inbox.blocks.image_ttl_days', 180);

        return array_map(function (array $entry) use ($project, $user, $ttl) {
            $parentType = $project ? Project::class : ($user ? User::class : null);
            $parentId = $project ? $project->id : ($user ? $user->id : null);

            return FileAttachment::create([
                ...$entry,
                'fileable_type' => $parentType,
                'fileable_id' => $parentId,
                'project_id' => $project?->id,
                'expires_at' => now()->addDays($ttl),
            ]);
        }, $uploaded);
    }

    /**
     * Re-point uploaded attachments to the email that now owns them.
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
            ->where(function ($q) {
                $q->where('fileable_type', Project::class)
                    ->orWhere('fileable_type', User::class)
                    ->orWhereNull('fileable_type');
            })
            ->update([
                'fileable_type' => Email::class,
                'fileable_id' => $email->id,
            ]);
    }

    /**
     * Resolve email attachments as raw parts for MIME encoding at send time.
     *
     * @param  array<int>  $excludeFileIds  ids to exclude (e.g. block inline images already in CID map)
     * @return array<int,array{filename:string,mime_type:string,bytes:string}>
     */
    public function attachmentPartsFor(Email $email, array $excludeFileIds = []): array
    {
        $exclude = array_filter(array_map('intval', $excludeFileIds));

        $files = $email->files()
            ->when(! empty($exclude), fn ($q) => $q->whereNotIn('id', $exclude))
            ->get();

        $parts = [];

        foreach ($files as $file) {
            $bytes = $this->bytes($file);
            if ($bytes === null) {
                continue;
            }

            $parts[] = [
                'filename' => $file->filename ?: 'attachment',
                'mime_type' => $file->mime_type ?: 'application/octet-stream',
                'bytes' => $bytes,
            ];
        }

        return $parts;
    }

    /**
     * Read raw bytes for a file from GCS storage.
     */
    public function bytes(FileAttachment $file): ?string
    {
        try {
            return Storage::disk('gcs')->get($file->path);
        } catch (Throwable $e) {
            Log::warning('inbox: could not read an email attachment for sending.', [
                'file_id' => $file->id,
                'path' => $file->path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
