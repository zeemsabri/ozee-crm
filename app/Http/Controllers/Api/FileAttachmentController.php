<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function store(Request $request)
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

        $records = [];
        $paths = [];

        foreach ($request->file('files') as $file) {
            try {
                // Upload original file to GCS
                $objectPath = Storage::disk('gcs')->putFile('task', $file); // returns object path in bucket

                $entry = [
                    'filename'   => $file->getClientOriginalName(),
                    'mime_type'  => $file->getMimeType(),
                    'file_size'  => $file->getSize(),
                    'path'       => $objectPath,
                ];

                // If it's an image, generate and upload a thumbnail
                if ($this->isImageMime($entry['mime_type'])) {
                    try {
                        $thumbBinary = $this->makeThumbnailFromFile($file->getRealPath());
                        // Build thumbnail object path alongside original, under a thumbnails/ subfolder
                        $dir = trim(dirname($objectPath), '.\/');
                        $base = pathinfo($objectPath, PATHINFO_FILENAME);
                        $thumbPath = ($dir ? $dir.'/' : '') . 'thumbnails/' . $base . '-thumb.jpg';
                        Storage::disk('gcs')->put($thumbPath, $thumbBinary);
                        $entry['thumbnail'] = $thumbPath;
                    } catch (\Throwable $thumbEx) {
                        Log::warning('Thumbnail generation failed', [
                            'file' => $entry['filename'],
                            'error' => $thumbEx->getMessage(),
                        ]);
                    }
                }

                $paths[] = $entry;
            } catch (\Exception $e) {
                return response()->json(['message' => 'Error uploading file: ' . $e->getMessage()], 500);
            }
        }

        foreach ($paths as $uploadedFile) {

            $record = $instance->files()->create($uploadedFile);;

            activity()
                    ->performedOn($record)
                    ->causedBy($user)
                    ->withProperties([
                        'model_type' => get_class($instance),
                        'model_id' => $instance->id,
                        'project_id' => $project?->id
                    ])
                    ->log('file_uploaded');
        }

        return response()->json([
            'message' => 'Files uploaded successfully',
            'files' => $records,
        ]);

    }

    public function destroy(FileAttachment $file)
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

    /**
     * Determine if mime type is an image we can thumbnail.
     */
    protected function isImageMime(?string $mime): bool
    {
        if (!$mime) return false;
        return str_starts_with($mime, 'image/');
    }

    /**
     * Create a JPEG thumbnail (max 400x400) from a local file path using GD and return binary string.
     */
    protected function makeThumbnailFromFile(string $path, int $maxDim = 400): string
    {
        [$width, $height, $type] = getimagesize($path);
        if (!$width || !$height) {
            throw new \RuntimeException('Invalid image for thumbnail.');
        }

        $scale = min($maxDim / $width, $maxDim / $height, 1);
        $newW = max(1, (int) floor($width * $scale));
        $newH = max(1, (int) floor($height * $scale));

        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($path);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($path);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($path);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $src = imagecreatefromwebp($path);
                    break;
                }
                // fallthrough to unsupported
            default:
                throw new \RuntimeException('Unsupported image type for thumbnail.');
        }

        if (!$src) {
            throw new \RuntimeException('Failed to load source image.');
        }

        $dst = imagecreatetruecolor($newW, $newH);
        // Preserve transparency for PNG/GIF while resampling
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

        ob_start();
        // Encode as JPEG to keep size small (quality 80). Transparency will be flattened.
        imagejpeg($dst, null, 80);
        $binary = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        if ($binary === false) {
            throw new \RuntimeException('Failed to render thumbnail.');
        }

        return $binary;
    }
}
