<?php

namespace App\Actions\Files;

use App\Models\FileAttachment;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteFileAttachmentAction
{
    /**
     * Delete a file attachment, cleaning up storage and logging.
     * Returns a result array with status info.
     */
    public function execute(FileAttachment $file, ?\App\Models\User $user = null): array
    {
        $result = [
            'success' => false,
            'file_id' => $file->id,
            'filename' => $file->filename,
            'error' => null,
        ];

        try {
            // Resolve project for activity logging
            $project = $file->project;
            if (!$project && $file->fileable_type === Task::class) {
                $task = Task::find($file->fileable_id);
                $project = $task?->milestone?->project;
            }

            // Attempt to delete files from GCS (original and thumbnail)
            try {
                if (!empty($file->path)) {
                    Storage::disk('gcs')->delete($file->path);
                }
                if (!empty($file->thumbnail)) {
                    Storage::disk('gcs')->delete($file->thumbnail);
                }
            } catch (\Throwable $e) {
                Log::warning('Error deleting file(s) from GCS; proceeding to delete DB record', [
                    'file_id' => $file->id,
                    'path' => $file->path,
                    'thumbnail' => $file->thumbnail,
                    'error' => $e->getMessage(),
                ]);
            }

            // Log activity if user is provided
            if ($user) {
                activity()
                    ->performedOn($file)
                    ->causedBy($user)
                    ->withProperties([
                        'model_type' => $file->fileable_type,
                        'model_id' => $file->fileable_id,
                        'project_id' => $project?->id,
                        'filename' => $file->filename,
                    ])
                    ->log('file_deleted');
            }

            // Delete DB record
            $file->delete();

            $result['success'] = true;
        } catch (\Exception $e) {
            Log::error('Error deleting file attachment', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }
}
