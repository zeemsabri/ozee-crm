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
     * List presentations belonging to a lead.
     */
    public function presentations(Lead $lead)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $items = $lead->presentations()->withCount('slides')->orderByDesc('id')->get()->map(function($p){
            return [
                'id' => $p->id,
                'title' => $p->title,
                'type' => $p->type,
                'is_template' => (bool)$p->is_template,
                'slides_count' => $p->slides_count,
                'share_token' => $p->share_token,
                'source' => 'lead',
            ];
        });
        return response()->json(['data' => $items]);
    }
    /**
     * Get emails related to a given lead (by conversation conversable).
     */
    public function emails(Lead $lead, Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = (int)($request->input('per_page', 15));
        $page = (int)($request->input('page', 1));

        $query = \App\Models\Email::with(['sender', 'approver', 'conversation.project'])
            ->whereHas('conversation', function ($q) use ($lead) {
                $q->where('conversable_type', \App\Models\Lead::class)
                  ->where('conversable_id', $lead->id);
            })
            ->orderByDesc('created_at');

        // Optional filters
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($qq) use ($search) {
                $qq->where('subject', 'like', "%{$search}%")
                   ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $emails = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($emails);
    }
    /**
     * Convert a lead into a client and return new client id.
     */
    public function convert(Lead $lead)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // naive conversion: create client from lead minimal fields
        try {
            $clientModel = \App\Models\Client::create([
                'name' => trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? '')) ?: ($lead->company ?? 'Client '.$lead->id),
                'email' => $lead->email,
                'phone' => $lead->phone,
                'address' => $lead->address,
                'notes' => $lead->notes,
                'lead_id' => $lead->id,
            ]);
            $lead->update(['status' => 'converted', 'converted_at' => now()]);
            return response()->json(['client_id' => $clientModel->id], 200);
        } catch (\Throwable $e) {
            Log::error('Lead convert error: '.$e->getMessage(), ['lead_id' => $lead->id]);
            return response()->json(['message' => 'Conversion failed'], 500);
        }
    }
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
                // Require at least one of first_name or last_name
                'first_name' => 'nullable|required_without:last_name|string|max:255',
                'last_name' => 'nullable|required_without:first_name|string|max:255',
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
                'campaign_id' => 'nullable|exists:campaigns,id',
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
            ], [
                'first_name.required_without' => 'Please provide at least a first or last name.',
                'last_name.required_without' => 'Please provide at least a first or last name.',
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
                'campaign_id' => 'nullable|exists:campaigns,id',
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

    /**
     * Search for leads, typically for attaching to a campaign.
     */
    public function search(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_projects')) { // Or a more general "view_leads" permission
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Search for leads not already in ANY campaign to avoid confusion
        $leads = Lead::query()
            ->whereNull('campaign_id')
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('company', 'like', "%{$query}%");
            })
            // Use the existing search scope from your Lead model for consistency
            // ->search($query)
            ->select('id', 'first_name', 'last_name', 'email')
            ->limit(10)
            ->get();

        return response()->json($leads);
    }
}
