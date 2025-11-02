<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    /**
     * Display a listing of the campaigns.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $q = $request->string('q')->toString();
        $isActive = $request->input('is_active');
        $perPage = (int) ($request->input('per_page', 15));
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        $query = Campaign::query()->orderByDesc('id');
        if ($q !== null && $q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('target_audience', 'like', "%{$q}%")
                    ->orWhere('goal', 'like', "%{$q}%");
            });
        }
        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', (bool) $isActive);
        }

        return $query->paginate($perPage)
            ->withPath(url('/api/campaigns'))
            ->withQueryString();
    }

    /**
     * Store a newly created campaign.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'target_audience' => 'nullable|string',
                'services_offered' => 'nullable|array',
                'goal' => 'nullable|string|max:255',
                'ai_persona' => 'nullable|string',
                'email_template' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'shareable_resource_ids' => 'nullable|array',
                'shareable_resource_ids.*' => 'integer|exists:shareable_resources,id',
            ]);

            $resourceIds = $validated['shareable_resource_ids'] ?? [];
            unset($validated['shareable_resource_ids']);

            $campaign = Campaign::create($validated);
            if (! empty($resourceIds)) {
                $campaign->shareableResources()->sync($resourceIds);
            }
            // Return with attached resource ids for convenience
            $campaign->setAttribute('shareable_resource_ids', $campaign->shareableResources()->pluck('shareable_resources.id'));

            return response()->json($campaign, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating campaign: '.$e->getMessage(), ['request' => $request->all()]);

            return response()->json(['message' => 'Failed to create campaign'], 500);
        }
    }

    /**
     * Display the specified campaign.
     */
    public function show(Campaign $campaign)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $campaign->load(['shareableResources:id,title']);
        // Attach just the IDs for easy form binding
        $campaign->setAttribute('shareable_resource_ids', $campaign->shareableResources->pluck('id'));

        return $campaign;
    }

    /**
     * Update the specified campaign.
     */
    public function update(Request $request, Campaign $campaign)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'target_audience' => 'nullable|string',
                'services_offered' => 'nullable|array',
                'goal' => 'nullable|string|max:255',
                'ai_persona' => 'nullable|string',
                'email_template' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'shareable_resource_ids' => 'nullable|array',
                'shareable_resource_ids.*' => 'integer|exists:shareable_resources,id',
            ]);

            $resourceIds = $validated['shareable_resource_ids'] ?? null; // null means do not change
            unset($validated['shareable_resource_ids']);

            $campaign->update($validated);

            if (is_array($resourceIds)) {
                $campaign->shareableResources()->sync($resourceIds);
            }

            $campaign->load('shareableResources:id,title');
            $campaign->setAttribute('shareable_resource_ids', $campaign->shareableResources->pluck('id'));

            return $campaign;
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating campaign: '.$e->getMessage(), ['campaign_id' => $campaign->id, 'request' => $request->all()]);

            return response()->json(['message' => 'Failed to update campaign'], 500);
        }
    }

    /**
     * Remove the specified campaign.
     */
    public function destroy(Campaign $campaign)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            // Add a check to detach leads before deleting if necessary, based on foreign key constraints
            if ($campaign->leads()->exists()) {
                return response()->json(['message' => 'Cannot delete campaign with attached leads. Please detach them first.'], 422);
            }
            $campaign->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting campaign: '.$e->getMessage(), ['campaign_id' => $campaign->id]);

            return response()->json(['message' => 'Failed to delete campaign'], 500);
        }
    }

    /**
     * List leads under a campaign.
     */
    public function leads(Campaign $campaign, Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = (int) ($request->input('per_page', 15));
        $perPage = $perPage > 0 && $perPage <= 100 ? $perPage : 15;

        return $campaign->leads()->orderByDesc('id')->paginate($perPage)
            ->withPath(url("/api/campaigns/{$campaign->id}/leads"))
            ->withQueryString();
    }

    /**
     * Attach a lead to a campaign by setting its campaign_id.
     */
    public function attachLead(Campaign $campaign, Request $request)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
        ]);

        $lead = Lead::findOrFail($validated['lead_id']);

        // Optional: Prevent attaching a lead that's already in another campaign
        if ($lead->campaign_id && $lead->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'This lead is already part of another campaign.'], 422);
        }

        $lead->update(['campaign_id' => $campaign->id]);

        return response()->json(['message' => 'Lead attached to campaign', 'lead' => $lead->fresh()]);
    }

    /**
     * Detach a lead from a campaign by nulling its campaign_id.
     */
    public function detachLead(Campaign $campaign, Lead $lead)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('manage_projects')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($lead->campaign_id !== $campaign->id) {
            return response()->json(['message' => 'Lead is not attached to this campaign'], 422);
        }

        $lead->update(['campaign_id' => null]);

        return response()->json(['message' => 'Lead detached from campaign']);
    }
}
