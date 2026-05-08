<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\FileAttachment;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ChatAttachmentService
{
    public function __construct(
        protected GoogleDriveService $googleDriveService
    ) {
    }

    /**
     * Attach uploaded files to a chat message.
     * Images are stored on GCS, documents are uploaded to Google Drive.
     *
     * @param  UploadedFile[]  $files
     * @return FileAttachment[]
     */
    public function attachUploadedFiles(Project $project, ChatMessage $message, array $files): array
    {
        $records = [];
        $driveFolderId = $this->resolveProjectDriveFolderId($project);

        foreach ($files as $file) {
            if ($this->isImage($file->getMimeType())) {
                $records[] = $this->storeImageOnGcs($project, $message, $file);
                continue;
            }

            if (! $driveFolderId) {
                throw new \RuntimeException('Project Google Drive folder is not configured for document upload.');
            }

            $records[] = $this->storeDocumentOnDrive($project, $message, $file, $driveFolderId);
        }

        return $records;
    }

    public function attachNewDriveDocument(Project $project, ChatMessage $message, string $title): FileAttachment
    {
        $driveFolderId = $this->resolveProjectDriveFolderId($project);
        if (! $driveFolderId) {
            throw new \RuntimeException('Project Google Drive folder is not configured.');
        }

        $created = $this->googleDriveService->createDocument($title, $driveFolderId);

        return $message->files()->create([
            'project_id' => $project->id,
            'filename' => $created['name'] ?? $title,
            'mime_type' => $created['mime_type'] ?? 'application/vnd.google-apps.document',
            'file_size' => null,
            'path' => $created['link'] ?? null,
            'google_drive_file_id' => $created['id'] ?? null,
        ]);
    }

    public function attachDriveReference(Project $project, ChatMessage $message, array $payload): FileAttachment
    {
        $rawDriveValue = $payload['drive_file_id'] ?? $payload['drive_url'] ?? null;
        $fileId = null;
        $url = $payload['drive_url'] ?? null;
        $name = $payload['name'] ?? null;
        $mimeType = $payload['mime_type'] ?? null;
        $thumbnail = null;
        $size = null;

        if ($rawDriveValue) {
            $fileId = $this->googleDriveService->resolveFileId((string) $rawDriveValue);

            try {
                $meta = $this->googleDriveService->getFileMetadata($fileId);
                $url = $meta['web_view_link'] ?? $url;
                $name = $name ?: ($meta['name'] ?? null);
                $mimeType = $mimeType ?: ($meta['mime_type'] ?? null);
                $thumbnail = $meta['thumbnail'] ?? null;
                $size = $meta['size'] ?? null;
            } catch (\Throwable) {
                // Fallback to the incoming URL/name/mime if metadata lookup fails.
            }

            if (! $url) {
                try {
                    $url = $this->googleDriveService->getWebContentLink($fileId, 'webViewLink');
                } catch (\Throwable) {
                    $url = null;
                }
            }
        }

        return $message->files()->create([
            'project_id' => $project->id,
            'filename' => $name ?? 'Google Drive File',
            'mime_type' => $mimeType,
            'file_size' => $size,
            'path' => $url,
            'thumbnail' => $thumbnail,
            'google_drive_file_id' => $fileId,
        ]);
    }

    public function resolveProjectDriveFolderId(Project $project): ?string
    {
        if (! empty($project->google_drive_folder_id)) {
            return $project->google_drive_folder_id;
        }

        $link = (string) ($project->google_drive_link ?? '');
        if ($link === '') {
            return null;
        }

        if (preg_match('/\/folders\/([a-zA-Z0-9_-]+)/', $link, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $link, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    protected function storeImageOnGcs(Project $project, ChatMessage $message, UploadedFile $file): FileAttachment
    {
        $objectPath = Storage::disk('gcs')->putFile("chat/{$project->id}", $file);

        return $message->files()->create([
            'project_id' => $project->id,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'path' => $objectPath,
        ]);
    }

    protected function storeDocumentOnDrive(
        Project $project,
        ChatMessage $message,
        UploadedFile $file,
        string $driveFolderId
    ): FileAttachment {
        $response = $this->googleDriveService->uploadFile(
            $file->getRealPath(),
            $file->getClientOriginalName(),
            $driveFolderId,
            'webViewLink'
        );

        return $message->files()->create([
            'project_id' => $project->id,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'path' => $response['path'] ?? null,
            'thumbnail' => $response['thumbnail'] ?? null,
            'google_drive_file_id' => $response['id'] ?? null,
        ]);
    }

    protected function isImage(?string $mime): bool
    {
        return is_string($mime) && str_starts_with($mime, 'image/');
    }
}
