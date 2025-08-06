<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wireframe;
use App\Models\WireframeVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;
use function PHPUnit\Framework\isNumeric;
use function PHPUnit\Framework\isString;

class WireframeController extends Controller
{
    /**
     * Display a listing of wireframes for a project.
     *
     * @param  int  $projectId
     * @return \Illuminate\Http\Response
     */
    public function index($projectId)
    {


        $wireframes = Wireframe::where('project_id', $projectId)
            ->with(['versions' => function ($query) {
                $query->orderBy('version_number', 'desc');
            }])
            ->get()
            ->map(function ($wireframe) {
                $latestVersion = $wireframe->latestVersion();
                return [
                    'id' => $wireframe->id,
                    'name' => $wireframe->name,
                    'project_id' => $wireframe->project_id,
                    'latest_version' => $latestVersion ? $latestVersion->version_number : null,
                    'latest_status' => $latestVersion ? $latestVersion->status : null,
                    'created_at' => $wireframe->created_at,
                    'updated_at' => $wireframe->updated_at,
                ];
            });

        return response()->json($wireframes);
    }

    /**
     * Store a newly created wireframe with version 1 (draft).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $projectId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $projectId)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('wireframes')->where(function ($query) use ($projectId) {
                    return $query->where('project_id', $projectId);
                }),
            ],
            'data' => 'sometimes|json',
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = '{"canvasSize": {"width": 1280, "height": 720}, "components": []}';

        $wireframe = Wireframe::create([
            'project_id' => $projectId,
            'name' => $request->name,
        ]);

        $version = WireframeVersion::create([
            'wireframe_id' => $wireframe->id,
            'version_number' => 1,
            'data' => json_decode($request->data ?? $data, true),
            'status' => 'draft',
        ]);

        activity()
            ->performedOn($wireframe)
            ->withProperties(['version_number' => 1])
            ->log("Wireframe {$wireframe->name} created with version 1 (draft)");

        return response()->json([
            'wireframe' => $wireframe,
            'version' => $version,
        ], 201);
    }

    /**
     * Display the specified wireframe with a specific version.
     *
     * @param  int  $projectId
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $projectId, $id)
    {
        $wireframe = Wireframe::where('project_id', $projectId)
            ->where('id', $id)
            ->firstOrFail();

        $versionNumber = $request->query('version');

        if (ISSET($versionNumber) && $versionNumber !== 'latest') {
            $version = WireframeVersion::where('wireframe_id', $wireframe->id)
                ->where('id', $versionNumber)
                ->firstOrFail();
        } else {
            $version = $wireframe->latestVersion();
            if (!$version) {
                return response()->json(['error' => 'No versions found for this wireframe'], 404);
            }
        }

        return response()->json([
            'wireframe' => $wireframe,
            'version' => $version,
        ]);
    }

    /**
     * Update the latest draft or create a new draft.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $projectId
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $projectId, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('wireframes')->where(function ($query) use ($projectId, $id) {
                    return $query->where('project_id', $projectId)->where('id', '!=', $id);
                }),
            ],
            'data' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $wireframe = Wireframe::where('project_id', $projectId)
            ->where('id', $id)
            ->firstOrFail();

        // Check if a specific version is requested
        $versionParam = $request->query('version');

        if ($versionParam && is_numeric((int)$versionParam)) {
            // Update a specific version
            $version = $wireframe->versions()
                ->where('id', (int)$versionParam)
                ->firstOrFail();

            if ($request->has('name')) {
                $wireframe->name = $request->name;
                $wireframe->save();
            }

            $version->data = json_decode($request->data, true);
            $version->save();

            activity()
                ->performedOn($wireframe)
                ->withProperties(['version_number' => $version->version_number])
                ->log("Wireframe {$wireframe->name} version {$version->version_number} updated");

            return response()->json([
                'wireframe' => $wireframe,
                'version' => $version,
                'action' => 'updated_version',
            ]);
        }

        if ($request->has('name')) {
            $wireframe->name = $request->name;
            $wireframe->save();
        }

        $latestDraft = $wireframe->latestDraftVersion();
        $latestPublished = $wireframe->latestPublishedVersion();

        if ($latestDraft) {
            // Update the existing draft
            $latestDraft->data = json_decode($request->data, true);
            $latestDraft->save();

            activity()
                ->performedOn($wireframe)
                ->withProperties(['version_number' => $latestDraft->version_number])
                ->log("Wireframe {$wireframe->name} draft version {$latestDraft->version_number} updated");

            return response()->json([
                'wireframe' => $wireframe,
                'version' => $latestDraft,
                'action' => 'updated_draft',
            ]);
        } else {
            // Create a new draft version
            $newVersionNumber = $latestPublished ? $latestPublished->version_number + 1 : 1;

            $newVersion = WireframeVersion::create([
                'wireframe_id' => $wireframe->id,
                'version_number' => $newVersionNumber,
                'data' => json_decode($request->data, true),
                'status' => 'draft',
            ]);

            activity()
                ->performedOn($wireframe)
                ->withProperties(['version_number' => $newVersionNumber])
                ->log("Wireframe {$wireframe->name} new draft version {$newVersionNumber} created");

            return response()->json([
                'wireframe' => $wireframe,
                'version' => $newVersion,
                'action' => 'created_draft',
            ]);
        }
    }

    /**
     * Publish the latest draft version.
     *
     * @param  int  $projectId
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function publish($projectId, $id)
    {
        $wireframe = Wireframe::where('project_id', $projectId)
            ->where('id', $id)
            ->firstOrFail();

        $latestDraft = $wireframe->latestDraftVersion();

        if (!$latestDraft) {
            return response()->json(['error' => 'No draft version available to publish'], 422);
        }

        $latestDraft->status = 'published';
        $latestDraft->save();

        activity()
            ->performedOn($wireframe)
            ->withProperties(['version_number' => $latestDraft->version_number])
            ->log("Wireframe {$wireframe->name} version {$latestDraft->version_number} published");

        return response()->json([
            'wireframe' => $wireframe,
            'version' => $latestDraft,
        ]);
    }

    /**
     * Create a new draft version.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $projectId
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function newVersion(Request $request, $projectId, $id)
    {
        $validator = Validator::make($request->all(), [
            'data' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $wireframe = Wireframe::where('project_id', $projectId)
            ->where('id', $id)
            ->firstOrFail();

        $latestVersion = $wireframe->latestVersion();
        $newVersionNumber = $latestVersion ? $latestVersion->version_number + 1 : 1;

        $newVersion = WireframeVersion::create([
            'wireframe_id' => $wireframe->id,
            'version_number' => $newVersionNumber,
            'data' => json_decode($request->data, true),
            'status' => 'draft',
        ]);

        activity()
            ->performedOn($wireframe)
            ->withProperties(['version_number' => $newVersionNumber])
            ->log("Wireframe {$wireframe->name} new version {$newVersionNumber} created");

        return response()->json([
            'wireframe' => $wireframe,
            'version' => $newVersion,
        ], 201);
    }

    /**
     * Remove the specified wireframe and all its versions.
     *
     * @param  int  $projectId
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($projectId, $id)
    {
        $wireframe = Wireframe::where('project_id', $projectId)
            ->where('id', $id)
            ->firstOrFail();

        $wireframeName = $wireframe->name;

        // The versions will be deleted automatically due to the cascade constraint
        $wireframe->delete();

        activity()
            ->withProperties(['wireframe_name' => $wireframeName])
            ->log("Wireframe {$wireframeName} deleted with all its versions");

        return response()->json(null, 204);
    }

    /**
     * Get all versions of a wireframe.
     *
     * @param  int  $projectId
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function versions($projectId, $id)
    {
        $wireframe = Wireframe::where('project_id', $projectId)
            ->where('id', $id)
            ->firstOrFail();

        $versions = $wireframe->versions()
            ->orderBy('version_number', 'desc')
            ->get();

        return response()->json([
            'wireframe' => $wireframe,
            'versions' => $versions
        ]);
    }

    /**
     * Get activity logs for a wireframe.
     *
     * @param  int  $projectId
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function logs($projectId, $id)
    {
        $wireframe = Wireframe::where('project_id', $projectId)
            ->where('id', $id)
            ->firstOrFail();

        $logs = Activity::where(function ($query) use ($wireframe) {
                $query->where('subject_type', Wireframe::class)
                      ->where('subject_id', $wireframe->id);
            })
            ->orWhere(function ($query) use ($wireframe) {
                $query->where('subject_type', WireframeVersion::class)
                      ->whereIn('subject_id', $wireframe->versions()->pluck('id'));
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }

    /**
     * Get the latest wireframe for a project.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $projectId
     * @return \Illuminate\Http\Response
     */
    public function latest(Request $request, $projectId)
    {
        // Find the latest wireframe for the project
        $wireframe = Wireframe::where('project_id', $projectId)
            ->with(['versions' => function ($query) {
                $query->orderBy('version_number', 'desc');
            }])
            ->latest()
            ->first();

        if (!$wireframe) {
            return response()->json(['error' => 'No wireframes found for this project'], 404);
        }

        // Check if a specific version is requested
        $versionParam = $request->query('version');

        if ($versionParam === 'latest') {
            // Get the latest version
            $version = $wireframe->latestVersion();
        } elseif ($versionParam === 'published') {
            // Get the latest published version
            $version = $wireframe->latestPublishedVersion();
        } elseif ($versionParam === 'draft') {
            // Get the latest draft version
            $version = $wireframe->latestDraftVersion();
        } else {
            // Default to latest version
            $version = $wireframe->latestVersion();
        }

        if (!$version) {
            return response()->json(['error' => 'No versions found for this wireframe'], 404);
        }

        return response()->json([
            'wireframe' => $wireframe,
            'version' => $version,
        ]);
    }

    /**
     * Update a specific version of a wireframe.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $projectId
     * @param  int  $id
     * @param  int  $versionNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateVersion(Request $request, $projectId, $id, $versionNumber)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $wireframe = Wireframe::where('project_id', $projectId)
            ->where('id', $id)
            ->firstOrFail();

        $version = $wireframe->versions()
            ->where('id', (int)$versionNumber)
            ->firstOrFail();

        // Update the wireframe name
        $version->name = $request->name;
        $version->save();

        activity()
            ->performedOn($version)
            ->withProperties(['version_number' => $version->version_number])
            ->log("Wireframe {$version->name} version {$version->version_number} updated");

        return response()->json([
            'wireframe' => $wireframe,
            'version' => $version,
        ]);
    }
}
