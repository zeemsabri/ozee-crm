<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\Task;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FileAttachmentController extends Controller
{
    use HasProjectPermissions;

    public function index(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $modelType = $request->input('model_type');
        $modelId = (int) $request->input('model_id');

        [$instance, $project] = $this->resolveModelAndProject($modelType, $modelId);
        if (!$instance) {
            return response()->json(['message' => 'Model not found.'], 404);
        }

        $user = Auth::user();
        if ($project && !$this->canManageProjects($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $files = FileAttachment::where('fileable_type', get_class($instance))
            ->where('fileable_id', $instance->id)
            ->latest()->get();

        return response()->json($files);
    }

    public function store(Request $request, GoogleDriveService $drive)
    {
        try {
            $request->validate([
                'model_type' => 'required|string',
                'model_id' => 'required|integer',
                'files' => 'required|array',
                'files.*' => 'required|file|max:20480',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        }

        $modelType = $request->input('model_type');
        $modelId = (int) $request->input('model_id');

        [$instance, $project] = $this->resolveModelAndProject($modelType, $modelId);
        if (!$instance) {
            return response()->json(['message' => 'Model not found.'], 404);
        }

        $user = Auth::user();
        if ($project && !$this->canManageProjects($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (!$project || !$project->google_drive_folder_id) {
            return response()->json(['message' => 'Project Google Drive folder is not configured.'], 400);
        }

        // Create or get subfolder inside project folder with the model base name (e.g., "Task")
        $modelBaseName = class_basename(get_class($instance));
//        $subfolderId = $drive->findOrCreateSubfolder($project->google_drive_folder_id, $modelBaseName);

//        $records = [];

        foreach ($request->file('files') as $file) {

            $localPath = $file->store('files', 'public');
            $fullLocalPath = Storage::disk('public')->path($localPath);

            try {
                $path = Storage::disk('gcs')->putFile('screenshots', $fullLocalPath);
            }
            catch (\Exception $e) {
                dd($e->getMessage());
            }


            dd($path);
           $publicUrl = Storage::disk('gcs')->url($path);

        }

                // Use the Project model's uploadDocuments method
//            $uploadedData = $project->uploadDocuments(
//                files: $request->file('files'),
//                googleDriveService: $drive,
//                field: 'webViewLink',
//                subFolder: $subfolderId,
//                createRecord: false);


//            foreach ($uploadedData as $uploadedFile) {
//                $records[] = $record = $instance->files()->create($uploadedFile);
//
//            activity()
//                    ->performedOn($record)
//                    ->causedBy($user)
//                    ->withProperties([
//                        'model_type' => get_class($instance),
//                        'model_id' => $instance->id,
//                        'project_id' => $project?->id
//                    ])
//                    ->log('file_uploaded');
//            }
//
//        return response()->json([
//            'message' => 'Files uploaded successfully',
//            'files' => $records,
//        ]);
    }

    public function destroy(FileAttachment $file, GoogleDriveService $drive)
    {
        $user = Auth::user();

        // Resolve project via file->project or via related model
        $project = $file->project;
        if (!$project && $file->fileable_type === Task::class) {
            $task = Task::find($file->fileable_id);
            $project = $task?->milestone?->project;
        }

        if ($project && !$this->canManageProjects($user, $project)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        try {
            if ($file->google_drive_file_id) {
                $drive->deleteFile($file->google_drive_file_id);
            }
        } catch (\Exception $e) {
            Log::warning('Error deleting Google Drive file; proceeding to delete DB record', [
                'file_id' => $file->id,
                'drive_file_id' => $file->google_drive_file_id,
                'error' => $e->getMessage(),
            ]);
        }

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

        $file->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

    /**
     * Resolve the model instance and its parent project depending on model type.
     * @return array{0: mixed|null, 1: Project|null}
     */
    protected function resolveModelAndProject(string $modelType, int $modelId): array
    {
        // Allow short model names like "Task" or fully qualified classes
        $fqcn = $modelType;
        if (!class_exists($fqcn)) {
            $maybe = 'App\\Models\\' . ltrim($modelType, '\\');
            if (class_exists($maybe)) {
                $fqcn = $maybe;
            }
        }

        $instance = $fqcn::find($modelId);
        $project = null;

        if ($instance instanceof Task) {
            $instance->loadMissing('milestone.project');
            $project = $instance->milestone?->project;
        }

        if ($instance instanceof Project) {
            $project = $instance;
        }

        return [$instance, $project];
    }
}
