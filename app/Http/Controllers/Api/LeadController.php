<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        // Use existing permission used widely for admin actions to avoid adding new permission entries for now
        if (!$user || !$user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $q = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();
        $assignedTo = $request->input('assigned_to_id');
        $perPage = (int)($request->input('per_page', 15));
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        $query = Lead::query()
            ->with(['assignedTo:id,name,email'])
            ->search($q)
            ->status($status)
            ->source($source)
            ->assignedTo($assignedTo)
            ->orderByDesc('id');

        return $query->paginate($perPage);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255|unique:leads,email',
                'phone' => 'nullable|string|max:50',
                'company' => 'nullable|string|max:255',
                'title' => 'nullable|string|max:255',
                'status' => 'nullable|string|max:100',
                'source' => 'nullable|string|max:100',
                'pipeline_stage' => 'nullable|string|max:100',
                'estimated_value' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'assigned_to_id' => 'nullable|exists:users,id',
                'contacted_at' => 'nullable|date',
                'converted_at' => 'nullable|date',
                'lost_reason' => 'nullable|string|max:500',
                'website' => 'nullable|url|max:255',
                'country' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'address' => 'nullable|string|max:255',
                'zip' => 'nullable|string|max:50',
                'tags' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            $validated['created_by_id'] = $user->id;
            $lead = Lead::create($validated);

            return response()->json($lead->load('assignedTo:id,name,email'), 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating lead: ' . $e->getMessage(), ['request' => $request->all()]);
            return response()->json(['message' => 'Failed to create lead'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Lead $lead)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return $lead->load('assignedTo:id,name,email');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Lead $lead)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255|unique:leads,email,' . $lead->id,
                'phone' => 'nullable|string|max:50',
                'company' => 'nullable|string|max:255',
                'title' => 'nullable|string|max:255',
                'status' => 'nullable|string|max:100',
                'source' => 'nullable|string|max:100',
                'pipeline_stage' => 'nullable|string|max:100',
                'estimated_value' => 'nullable|numeric|min:0',
                'currency' => 'nullable|string|size:3',
                'assigned_to_id' => 'nullable|exists:users,id',
                'contacted_at' => 'nullable|date',
                'converted_at' => 'nullable|date',
                'lost_reason' => 'nullable|string|max:500',
                'website' => 'nullable|url|max:255',
                'country' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'city' => 'nullable|string|max:100',
                'address' => 'nullable|string|max:255',
                'zip' => 'nullable|string|max:50',
                'tags' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            $lead->update($validated);

            return $lead->fresh()->load('assignedTo:id,name,email');
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating lead: ' . $e->getMessage(), ['lead_id' => $lead->id, 'request' => $request->all()]);
            return response()->json(['message' => 'Failed to update lead'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lead $lead)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $lead->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting lead: ' . $e->getMessage(), ['lead_id' => $lead->id]);
            return response()->json(['message' => 'Failed to delete lead'], 500);
        }
    }
}
