<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BugReportController extends Controller
{
    use HandlesImageUploads;

    /**
     * GET /api/bugs
     * List recent bug reports, optionally filtered by pageUrl.
     */
    public function index(Request $request)
    {

        $pageUrl = $request->query('pageUrl') ?? $request->query('url');

        $query = Task::query()
            ->with(['files' => fn ($q) => $q->select('id', 'fileable_id', 'fileable_type', 'path', 'thumbnail', 'filename')])
            ->where('details->source', 'bug_report');

        if (! empty($pageUrl)) {
            $base = $this->normalizeUrlBase($pageUrl);
            $baseAlt = rtrim($base, '/');
            $query->where(function ($q) use ($base, $baseAlt) {
                $q->where('details->page_url', 'like', $base.'%');
                if ($baseAlt !== $base) {
                    $q->orWhere('details->page_url', 'like', $baseAlt.'%');
                }
            });
        }

        $tasks = $query->latest('id')->limit(200)->get(['id', 'description', 'details', 'status', 'created_at', 'updated_at']);

        $result = $tasks->map(function (Task $task) {
            $details = $task->details ?? [];

            return [
                'id' => $details['bug_id'] ?? $task->id,
                'description' => $task->description,
                'status' => $task->status,
                'screenshot' => $task->files,
                'pageUrl' => $details['page_url'] ?? null,
                'rect' => $details['rect'] ?? null,
                'created' => $task->created_at,
                'updated' => $task->updated_at,
            ];
        })->values();

        return response()->json($result);
    }

    /**
     * GET /api/bugs/status
     * Check if a URL belongs to a project's reporting sites.
     */
    public function status(Request $request)
    {
        return $this->checkExists($this->getPattern($request))->exists()
            ? response()->json(['exists' => true, 'status' => 'ok'], 200)
            : response()->json(['exists' => false, 'message' => 'No matching reporting site found'], 404);
    }

    private function getPattern($request)
    {
        $pageUrl = $request->query('pageUrl') ?? $request->query('url') ?? $request->input('pageUrl') ?? $request->input('url');
        if (empty($pageUrl)) {
            return response()->json(['message' => 'The pageUrl query parameter is required.'], 422);
        }

        $parts = @parse_url($pageUrl) ?: [];
        $host = strtolower($parts['host'] ?? '');
        $host = preg_replace('/^www\./', '', $host);
        $path = $parts['path'] ?? '';
        $scheme = strtolower($parts['scheme'] ?? '');
        $baseNoQuery = $host ? ($scheme ? ($scheme.'://'.$host.$path) : ($host.$path)) : $pageUrl;

        $patterns = [];
        if (! empty($host)) {
            $patterns[] = $host;
        }
        if (! empty($baseNoQuery)) {
            $patterns[] = rtrim($baseNoQuery, '/');
        }

        return $patterns;
    }

    public function checkExists($patterns = [])
    {
        return $exists = Project::query()
            ->whereNotNull('reporting_sites')
            ->where('reporting_sites', '!=', '')
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $p) {
                    $q->orWhere('reporting_sites', 'like', '%'.$p.'%');
                }
            });
    }

    /**
     * POST /api/bugs/report
     * Accepts and persists a new bug report.
     */
    public function report(Request $request)
    {
        $data = $request->validate([
            'description' => 'nullable|string',
            'screenshot' => 'nullable|string',
            'pageUrl' => 'nullable|url',
            'rect' => 'nullable|array',
            'rect.x' => 'nullable|integer',
            'rect.y' => 'nullable|integer',
            'rect.width' => 'nullable|integer',
            'rect.height' => 'nullable|integer',
        ]);

        $bugId = Str::random(3);
        $project = $this->checkExists($this->getPattern($request))->with('clients')->first();
        $client = $project->clients?->first() ?? User::first();
        $screenshotUrl = null;
        $uploadMeta = null;
        $paths = [];

        // 1. Handle Screenshot Upload
        if (! empty($data['screenshot'])) {
            try {
                $uploadedFile = $this->prepareUploadedFileFromBase64($data['screenshot'], $bugId);
                $paths = $this->uploadFilesToGcsWithThumbnails([$uploadedFile], 'bug-reports', 'gcs');

                if (! empty($paths)) {
                    $uploadMeta = $paths[0];
                    $screenshotUrl = Storage::disk('gcs')->url($uploadMeta['path']);
                }
            } catch (Throwable $e) {
                Log::error('BugReport screenshot upload failed', ['id' => $bugId, 'error' => $e->getMessage()]);
            }
        }

        if ($project) {
            $task = $this->createBugReportTask($project, $bugId, $data, $uploadMeta, $screenshotUrl, $client, $request);
            // 3. Attach Files and Log Activity
            $this->attachFileToTaskAndLogActivity($task, $paths, User::first(), $project);
        } else {
            Log::warning('No project found to attach bug report task', ['id' => $bugId]);
        }

        return response()->json([
            'id' => $bugId,
            'description' => $data['description'] ?? null,
            'screenshot' => $screenshotUrl,
            'pageUrl' => $data['pageUrl'] ?? null,
            'rect' => $data['rect'] ?? null,
            'message' => 'Bug report received and logged.',
        ], 201);
    }

    /**
     * Decodes a base64 data URL and prepares it as an UploadedFile instance.
     */
    private function prepareUploadedFileFromBase64(string $base64String, string $id): UploadedFile
    {
        @[$typePart, $base64Part] = explode(';', $base64String);
        @[, $base64Data] = explode(',', $base64Part);
        $decoded = base64_decode($base64Data);

        $mimeType = str_starts_with($typePart ?? '', 'data:') ? substr($typePart, 5) : 'image/png';
        $extension = explode('/', $mimeType)[1] ?? 'png';

        $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bugreport-'.$id.'.'.$extension;
        file_put_contents($tmpPath, $decoded);

        return new UploadedFile($tmpPath, 'bug-'.$id.'.'.$extension, $mimeType, null, true);
    }

    /**
     * Creates the Task record for the bug report.
     */
    private function createBugReportTask(
        Project $project,
        string $bugId,
        array $data,
        ?array $uploadMeta,
        ?string $screenshotUrl,
        User|Client|null $user = null,
        Request $request): Task
    {
        $defaultTaskType = TaskType::firstOrCreate(['name' => 'Bug']);
        $milestone = $project->supportMilestone();

        $details = [
            'source' => 'bug_report',
            'bug_id' => $bugId,
            'page_url' => $data['pageUrl'] ?? null,
            'rect' => $data['rect'] ?? null,
            'ip' => $request->ip(),
            'screenshot' => [
                'path' => $uploadMeta['path'] ?? null,
                'thumbnail' => $uploadMeta['thumbnail'] ?? null,
                'url' => $screenshotUrl,
            ],
        ];

        return $milestone->tasks()->create([
            'name' => 'Bug# '.$bugId,
            'creator_id' => $user?->id ?? null,
            'creator_type' => get_class($user) ?? null,
            'task_type_id' => $defaultTaskType->id,
            'description' => $data['description'] ?? null,
            'details' => $details,
            'status' => Task::STATUS_TO_DO,
        ]);
    }

    /**
     * Attaches file records to a task and logs the upload activity.
     */
    private function attachFileToTaskAndLogActivity(Task $task, array $paths, ?User $user, Project $project): void
    {
        foreach ($paths as $uploadedFile) {
            $record = $task->files()->create($uploadedFile);
            activity()
                ->performedOn($record)
                ->causedBy($user)
                ->withproperty('model_type', get_class($task))
                ->withproperty('model_id', $task->id)
                ->withproperty('project_id', $project->id)
                ->log('file_uploaded');
        }
    }

    /**
     * Normalize a URL to its base form (scheme://host/path).
     */
    protected function normalizeUrlBase(?string $url): string
    {
        if (empty($url)) {
            return '';
        }
        $parts = parse_url($url);
        if (! $parts || empty($parts['host'])) {
            return trim((string) $url);
        }

        $scheme = $parts['scheme'] ?? 'http';
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return sprintf('%s://%s%s%s', $scheme, $host, $port, $path);
    }
}
