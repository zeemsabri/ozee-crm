<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;

class PublicLeadApiController extends Controller
{
    /**
     * Public POST endpoint to accept lead submissions from external systems.
     * Path variable "firefly" is captured as the source identifier.
     *
     * Expects: name, email, phone, metadata (array or JSON string)
     * Requires: Authorization header containing a valid API key (raw or Bearer <key>).
     */
    public function store(Request $request, string $firefly)
    {
        // 1) Validate API key from Authorization header
        $authHeader = (string) $request->header('Authorization', '');
        $providedKey = '';
        if (preg_match('/^Bearer\s+(.*)$/i', $authHeader, $m)) {
            $providedKey = trim($m[1]);
        } else {
            $providedKey = trim($authHeader);
        }

        $allowedKeys = collect((array) config('public_api.keys', []))
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values();

        if ($providedKey === '' || ! $allowedKeys->contains($providedKey)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 2) Validate payload
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'metadata' => 'nullable', // accept array or JSON string; we'll normalize below
        ]);

        // Normalize metadata
        $metaInput = $validated['metadata'] ?? null;
        if (is_string($metaInput)) {
            $decoded = json_decode($metaInput, true);
            $metadata = is_array($decoded) ? $decoded : [];
        } elseif (is_array($metaInput)) {
            $metadata = $metaInput;
        } else {
            $metadata = [];
        }

        // Split name into first and last name (on first whitespace)
        $fullName = trim((string) ($validated['name'] ?? ''));
        $firstName = null;
        $lastName = null;
        if ($fullName !== '') {
            $parts = preg_split('/\s+/', $fullName, 2, PREG_SPLIT_NO_EMPTY);
            if ($parts) {
                $firstName = $parts[0] ?? null;
                $lastName = $parts[1] ?? null;
            }
        }

        $email = $validated['email'] ?? null;
        $phone = $validated['phone'] ?? null;

        // 3) Resolve campaign by {firefly} name (case-insensitive); create if missing
        $normalizedCampaignName = trim(strtolower($firefly));
        $campaign = Campaign::whereRaw('lower(name) = ?', [$normalizedCampaignName])->first();
        if (! $campaign) {
            $campaign = Campaign::create([
                'name' => $firefly,
                'is_active' => true,
            ]);
        }

        // 4) Upsert by email when available (including soft-deleted)
        $lead = null;
        if ($email) {
            $lead = Lead::withTrashed()->where('email', $email)->first();
            if ($lead && $lead->trashed()) {
                $lead->restore();
            }
        }

        $payload = array_filter([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'source' => $firefly,
            'status' => LeadStatus::New,
            'campaign_id' => $campaign->id,
        ], fn ($v) => ! is_null($v) && $v !== '');

        if ($lead) {
            $lead->fill($payload);
            $existingMeta = is_array($lead->metadata) ? $lead->metadata : [];
            $lead->metadata = array_replace($existingMeta, $metadata);
            $lead->save();
        } else {
            $lead = Lead::create(array_merge($payload, [
                'metadata' => $metadata,
            ]));
        }

        // 4) Choose a different API key to return back
        $otherKey = $allowedKeys->first(fn ($k) => $k !== $providedKey);
        $responseKey = $otherKey ?: null;

        return response()->json([
            'ok' => true,
            'lead_id' => $lead->id,
            'api_key' => $responseKey,
        ], $lead->wasRecentlyCreated ? 201 : 200);
    }
}
