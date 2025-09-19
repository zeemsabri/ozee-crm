<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Campaign;
use App\Models\Context;
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
            app(\App\Services\ValueSetValidator::class)->validate('Lead','status', \App\Enums\LeadStatus::Converted);
            $lead->update(['status' => \App\Enums\LeadStatus::Converted, 'converted_at' => now()]);
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
        $campaignIdsParam = $request->input('campaign_ids');
        $perPage = (int)($request->input('per_page', 15));
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        // Parse campaign_ids as CSV or array
        $campaignIds = [];
        if (is_string($campaignIdsParam) && trim($campaignIdsParam) !== '') {
            $campaignIds = array_filter(array_map('intval', explode(',', $campaignIdsParam)));
        } elseif (is_array($campaignIdsParam)) {
            $campaignIds = array_filter(array_map('intval', $campaignIdsParam));
        }

        $query = Lead::query()
            ->with(['assignedTo:id,name,email', 'campaign:id,name', 'latestContext'])
            ->search($q)
            ->status($status)
            ->source($source)
            ->assignedTo($assignedTo)
            ->campaigns($campaignIds)
            ->orderByDesc('id');

        return $query->paginate($perPage)
            ->withPath(url('/api/leads'))
            ->withQueryString();
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
            // Normalize website to include scheme if missing so 'url' rule accepts bare domains
            if ($request->filled('website')) {
                $rawWebsite = trim((string) $request->input('website'));
                if ($rawWebsite !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $rawWebsite)) {
                    $request->merge(['website' => 'https://' . $rawWebsite]);
                } else {
                    $request->merge(['website' => $rawWebsite]);
                }
            }
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

            // Soft-validate and coerce status when provided
            if (array_key_exists('status', $validated)) {
                $enum = \App\Enums\LeadStatus::tryFrom($validated['status']) ?? \App\Enums\LeadStatus::tryFrom(strtolower((string)$validated['status']));
                if ($enum) {
                    $validated['status'] = $enum->value;
                }
                app(\App\Services\ValueSetValidator::class)->validate('Lead','status', $validated['status']);
            }

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

        // Eager load relations and contexts ordered by latest
        $lead->load([
            'assignedTo:id,name,email',
            'campaign:id,name',
            'latestContext',
            'contexts' => function ($q) {
                $q->with('user:id,name')->orderByDesc('id');
            },
        ]);

        // Resolve additional campaigns from metadata.additional_campaign_ids
        $additionalIds = [];
        try {
            $metadata = $lead->metadata ?? [];
            if (is_array($metadata) && !empty($metadata['additional_campaign_ids']) && is_array($metadata['additional_campaign_ids'])) {
                $additionalIds = array_values(array_unique(array_filter(array_map('intval', $metadata['additional_campaign_ids']))));
            }
        } catch (\Throwable $e) {
            $additionalIds = [];
        }
        if (!empty($additionalIds)) {
            $additional = Campaign::query()->whereIn('id', $additionalIds)->get(['id', 'name']);
        } else {
            $additional = collect();
        }
        // Attach as a dynamic attribute so it appears in JSON as additional_campaigns
        $lead->setAttribute('additional_campaigns', $additional->values());

        return $lead;
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
            // Normalize website to include scheme if missing so 'url' rule accepts bare domains
            if ($request->filled('website')) {
                $rawWebsite = trim((string) $request->input('website'));
                if ($rawWebsite !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $rawWebsite)) {
                    $request->merge(['website' => 'https://' . $rawWebsite]);
                } else {
                    $request->merge(['website' => $rawWebsite]);
                }
            }
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

            // Soft-validate and coerce status when provided
            if (array_key_exists('status', $validated)) {
                $enum = \App\Enums\LeadStatus::tryFrom($validated['status']) ?? \App\Enums\LeadStatus::tryFrom(strtolower((string)$validated['status']));
                if ($enum) {
                    $validated['status'] = $enum->value;
                }
                app(\App\Services\ValueSetValidator::class)->validate('Lead','status', $validated['status']);
            }

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

        $q = (string) $request->input('q', '');
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $leads = Lead::search($q)
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->json($leads);
    }

    /**
     * Add a context to the given lead.
     */
    public function addContext(Request $request, Lead $lead)
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'summary' => 'required|string|max:2000',
            'meta_data' => 'nullable|array',
        ]);

        try {
            $context = new Context([
                'summary' => $validated['summary'],
                'user_id' => $user->id,
                'meta_data' => $validated['meta_data'] ?? null,
            ]);
            $context->linkable()->associate($lead);
            $context->save();

            // Return fresh contexts list (ordered) for convenience
            $lead->load(['contexts' => function ($q) {
                $q->with('user:id,name')->orderByDesc('id');
            }]);

            return response()->json([
                'message' => 'Context added',
                'contexts' => $lead->contexts,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Failed to add lead context: '.$e->getMessage(), ['lead_id' => $lead->id]);
            return response()->json(['message' => 'Failed to add context'], 500);
        }
    }
}
