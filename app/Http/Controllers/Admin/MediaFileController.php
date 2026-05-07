<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Files\DeleteFileAttachmentAction;
use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class MediaFileController extends Controller
{
    /**
     * Display the media files admin page.
     */
    public function index()
    {
        return Inertia::render('Admin/MediaFiles/Index');
    }

    /**
     * List media files with filtering and pagination.
     */
    public function list(Request $request)
    {
        $validated = $request->validate([
            'fileable_types' => 'nullable|array',
            'fileable_types.*' => 'string',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer',
            'linked_status' => 'nullable|in:linked,unlinked',
            'parent_status' => 'nullable|in:active,soft_deleted,missing',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $query = FileAttachment::query();

        // Filter by fileable types
        if (!empty($validated['fileable_types'])) {
            $query->whereIn('fileable_type', $validated['fileable_types']);
        }

        // Filter by project IDs (includes soft-deleted projects)
        if (!empty($validated['project_ids'])) {
            $query->where(function ($q) use ($validated) {
                $q->whereIn('project_id', $validated['project_ids'])
                    ->orWhereIn('fileable_type', [Task::class, Email::class])
                    ->whereIn('fileable_id', function ($subq) use ($validated) {
                        if (in_array(Task::class, $validated['fileable_types'] ?? [])) {
                            $subq->selectRaw('id')
                                ->from('tasks')
                                ->whereHas('milestone', function ($q) use ($validated) {
                                    $q->whereIn('project_id', $validated['project_ids']);
                                });
                        }
                    });
            });
        }

        // Filter by search (filename contains)
        if (!empty($validated['search'])) {
            $query->where('filename', 'like', '%' . $validated['search'] . '%');
        }

        // Filter by date range
        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        // Get paginated results
        $files = $query->latest('created_at')->paginate($perPage);

        // Enrich each file with project context and parent status
        $files->getCollection()->transform(function (FileAttachment $file) {
            return $this->enrichFile($file);
        });

        // Build filter option payloads
        $fileableTypes = FileAttachment::query()
            ->select('fileable_type')
            ->distinct()
            ->pluck('fileable_type')
            ->map(function ($type) {
                return ['value' => $type, 'label' => class_basename($type)];
            })
            ->values();

        $projects = Project::withTrashed()
            ->orderBy('name')
            ->get()
            ->map(function ($project) {
                $label = $project->name;
                if ($project->trashed()) {
                    $label .= ' (deleted)';
                }
                return ['value' => $project->id, 'label' => $label];
            });

        return response()->json([
            'files' => $files,
            'filters' => [
                'fileable_types' => $fileableTypes,
                'projects' => $projects,
                'linked_statuses' => [
                    ['value' => 'linked', 'label' => 'Linked to Project'],
                    ['value' => 'unlinked', 'label' => 'Not Linked'],
                ],
                'parent_statuses' => [
                    ['value' => 'active', 'label' => 'Parent Active'],
                    ['value' => 'soft_deleted', 'label' => 'Parent Soft-Deleted'],
                    ['value' => 'missing', 'label' => 'Parent Missing/Broken'],
                ],
            ],
        ]);
    }

    /**
     * Get signed URL for viewing a file.
     */
    public function viewUrl(FileAttachment $file)
    {
        try {
            // Generate signed URL for the file
            $pathUrl = $file->path_url;
            if (!$pathUrl) {
                return response()->json(['error' => 'File path not available'], 404);
            }

            return response()->json([
                'url' => $pathUrl,
                'filename' => $file->filename,
                'mime_type' => $file->mime_type,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error generating signed URL for file: ' . $e->getMessage(), [
                'file_id' => $file->id,
            ]);
            return response()->json(['error' => 'Unable to generate file URL'], 500);
        }
    }

    /**
     * Bulk delete files.
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:files,id',
        ]);

        $user = Auth::user();
        $action = new DeleteFileAttachmentAction();

        $deleted = 0;
        $failed = 0;
        $failedIds = [];
        $errors = [];

        foreach ($validated['ids'] as $fileId) {
            try {
                $file = FileAttachment::find($fileId);
                if (!$file) {
                    $failed++;
                    $failedIds[] = $fileId;
                    $errors[$fileId] = 'File not found';
                    continue;
                }

                // Check authorization by attempting to resolve project
                $project = $file->project;
                if (!$project && $file->fileable_type === Task::class) {
                    $task = Task::find($file->fileable_id);
                    $project = $task?->milestone?->project;
                }

                if ($project && !$this->canManageProjects($user, $project)) {
                    $failed++;
                    $failedIds[] = $fileId;
                    $errors[$fileId] = 'Unauthorized';
                    continue;
                }

                // Execute delete action
                $result = $action->execute($file, $user);
                if ($result['success']) {
                    $deleted++;
                } else {
                    $failed++;
                    $failedIds[] = $fileId;
                    $errors[$fileId] = $result['error'] ?? 'Unknown error';
                }
            } catch (\Exception $e) {
                $failed++;
                $failedIds[] = $fileId;
                $errors[$fileId] = $e->getMessage();
            }
        }

        return response()->json([
            'message' => "Deleted $deleted file(s), $failed failed",
            'deleted_count' => $deleted,
            'failed_count' => $failed,
            'failed_ids' => $failedIds,
            'errors' => $errors,
        ]);
    }

    /**
     * Enrich a file with project context and parent status.
     */
    protected function enrichFile(FileAttachment $file): array
    {
        $file->load(['fileable' => function ($q) {
            $q->withTrashed();
        }]);

        $project = null;
        $parentStatus = 'missing';

        if ($file->project_id) {
            $project = Project::withTrashed()->find($file->project_id);
            $parentStatus = $project ? ($project->trashed() ? 'soft_deleted' : 'active') : 'missing';
        }

        // Resolve from parent chain
        if (!$project && $file->fileable) {
            $parent = $file->fileable;
            $parentDeleted = false;

            if ($parent instanceof Task) {
                $parentDeleted = $parent->trashed();
                $milestone = $parent->milestone;
                $project = $milestone?->project;
            } elseif ($parent instanceof Email) {
                $parentDeleted = $parent->trashed();
                $conversation = $parent->conversation;
                $project = $conversation?->project;
            } elseif ($parent instanceof Project) {
                $parentDeleted = $parent->trashed();
                $project = $parent;
            }

            $parentStatus = $parent ? ($parentDeleted ? 'soft_deleted' : 'active') : 'missing';
        }

        return [
            'id' => $file->id,
            'filename' => $file->filename,
            'mime_type' => $file->mime_type,
            'file_size' => $file->file_size,
            'fileable_type' => class_basename($file->fileable_type),
            'fileable_id' => $file->fileable_id,
            'project_id' => $project?->id,
            'project_name' => $project?->name,
            'project_trashed' => $project?->trashed() ?? false,
            'parent_status' => $parentStatus,
            'has_path' => !empty($file->path),
            'has_thumbnail' => !empty($file->thumbnail),
            'created_at' => $file->created_at,
            'linked' => !is_null($project),
        ];
    }

    /**
     * Check if user can manage projects.
     */
    protected function canManageProjects($user, $project): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasProjectPermission($project, 'manage_projects'))
            || $user->hasPermission('manage_projects');
    }
}
