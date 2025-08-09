<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShareableResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ShareableResourceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = ShareableResource::with('tags');

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by visibility if provided
        if ($request->has('visible_to_client')) {
            $query->where('visible_to_client', '=', 1);
        }

        // Filter by tag if provided
        if ($request->has('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        $resources = $query->latest()->paginate(10);

        return response()->json($resources);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'url' => 'required|url|max:2048',
            'type' => 'required|string|in:youtube,website,other',
            'thumbnail_url' => 'nullable|url|max:2048',
            'visible_to_client' => 'boolean',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $resource = ShareableResource::create([
            'title' => $request->title,
            'description' => $request->description,
            'url' => $request->url,
            'type' => $request->type,
            'thumbnail_url' => $request->thumbnail_url,
            'created_by' => Auth::id(),
            'visible_to_client' => $request->visible_to_client ?? true,
        ]);

        // Sync tags if provided
        if ($request->has('tag_ids')) {
            $resource->syncTags($request->tag_ids);
        }

        return response()->json($resource->load('tags'), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $resource = ShareableResource::with('tags', 'creator')->findOrFail($id);

        return response()->json($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $resource = ShareableResource::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'url' => 'sometimes|required|url|max:2048',
            'type' => 'sometimes|required|string|in:youtube,website,other',
            'thumbnail_url' => 'nullable|url|max:2048',
            'visible_to_client' => 'boolean',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $resource->update($request->only([
            'title',
            'description',
            'url',
            'type',
            'thumbnail_url',
            'visible_to_client',
        ]));

        // Sync tags if provided
        if ($request->has('tag_ids')) {
            $resource->syncTags($request->tag_ids);
        }

        return response()->json($resource->load('tags'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $resource = ShareableResource::findOrFail($id);
        $resource->delete();

        return response()->json(null, 204);
    }
}
