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
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        // Visibility filters
        if ($request->has('visible_to_client')) {
            $query->where('visible_to_client', filter_var($request->input('visible_to_client'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->has('visible_to_team')) {
            $query->where('visible_to_team', filter_var($request->input('visible_to_team'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->has('is_private')) {
            $query->where('is_private', filter_var($request->input('is_private'), FILTER_VALIDATE_BOOLEAN));
        }

        // Search by title or description
        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function($sub) use ($q) {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%');
            });
        }

        // Filter by tag if provided
        if ($request->has('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        $perPage = (int) ($request->input('per_page', 10));
        if ($perPage <= 0) { $perPage = 10; }
        $resources = $query->latest()->paginate($perPage);

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
            'type' => 'required|string|in:youtube,website,document,image,other',
            'thumbnail_url' => 'nullable|url|max:2048',
            'visible_to_client' => 'boolean',
            'visible_to_team' => 'boolean',
            'is_private' => 'boolean',
            'visibility' => 'nullable|string|in:client,team,private',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Determine visibility
        $visible_to_client = $request->boolean('visible_to_client');
        $visible_to_team = $request->boolean('visible_to_team');
        $is_private = $request->boolean('is_private');
        if ($request->filled('visibility')) {
            $visible_to_client = $request->visibility === 'client';
            $visible_to_team = $request->visibility === 'team';
            $is_private = $request->visibility === 'private';
        }
        // Enforce exclusivity
        if ($is_private) { $visible_to_client = false; $visible_to_team = false; }
        if ($visible_to_team) { $visible_to_client = false; $is_private = false; }
        if ($visible_to_client) { $visible_to_team = false; $is_private = false; }

        $resource = ShareableResource::create([
            'title' => $request->title,
            'description' => $request->description,
            'url' => $request->url,
            'type' => $request->type,
            'thumbnail_url' => $request->thumbnail_url,
            'created_by' => Auth::id(),
            'visible_to_client' => $visible_to_client ?? true,
            'visible_to_team' => $visible_to_team ?? false,
            'is_private' => $is_private ?? false,
        ]);

        $resource->syncTags($request->tags);


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
            'type' => 'sometimes|required|string|in:youtube,website,document,image,other',
            'thumbnail_url' => 'nullable|url|max:2048',
            'visible_to_client' => 'boolean',
            'visible_to_team' => 'boolean',
            'is_private' => 'boolean',
            'visibility' => 'nullable|string|in:client,team,private'
        ]);

        $resource->syncTags($request->tags ?? []);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Determine visibility updates similar to store()
        $visible_to_client = $request->has('visible_to_client') ? $request->boolean('visible_to_client') : $resource->visible_to_client;
        $visible_to_team = $request->has('visible_to_team') ? $request->boolean('visible_to_team') : $resource->visible_to_team;
        $is_private = $request->has('is_private') ? $request->boolean('is_private') : $resource->is_private;
        if ($request->filled('visibility')) {
            $visible_to_client = $request->visibility === 'client';
            $visible_to_team = $request->visibility === 'team';
            $is_private = $request->visibility === 'private';
        }
        // Enforce exclusivity
        if ($is_private) { $visible_to_client = false; $visible_to_team = false; }
        if ($visible_to_team) { $visible_to_client = false; $is_private = false; }
        if ($visible_to_client) { $visible_to_team = false; $is_private = false; }

        $payload = $request->only([
            'title',
            'description',
            'url',
            'type',
            'thumbnail_url',
        ]);
        $payload['visible_to_client'] = $visible_to_client;
        $payload['visible_to_team'] = $visible_to_team;
        $payload['is_private'] = $is_private;

        $resource->update($payload);

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
