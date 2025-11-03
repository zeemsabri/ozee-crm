<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        // Build key entries from config: entries[] (with domains) + keys[] (simple)
        $configEntries = collect(config('public_api.entries', []))
            ->filter(fn ($e) => is_array($e) && ! empty($e['key']))
            ->map(function ($e) {
                $domains = [];
                if (! empty($e['domains']) && is_array($e['domains'])) {
                    $domains = array_values(array_filter(array_map('strval', $e['domains'])));
                }

                return [
                    'key' => trim((string) $e['key']),
                    'domains' => $domains,
                ];
            });
        $envEntries = collect((array) config('public_api.keys', []))
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->map(fn ($k) => ['key' => $k, 'domains' => []]);
        $allEntries = $configEntries->concat($envEntries)->values();

        // Validate key exists
        if ($providedKey === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $matched = $allEntries->first(fn ($e) => hash_equals($e['key'], $providedKey));
        if (! $matched) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // If domains are specified for this key, enforce Origin/Referer host match
        if (! empty($matched['domains'])) {
            $origin = (string) ($request->headers->get('Origin') ?? $request->headers->get('Referer') ?? '');
            $host = $origin ? parse_url($origin, PHP_URL_HOST) : null;
            if (! is_string($host) || $host === '') {
                return response()->json(['message' => 'Forbidden: missing or invalid origin'], 403);
            }
            $host = strtolower($host);
            $allowed = false;
            foreach ($matched['domains'] as $pattern) {
                $pattern = strtolower(trim((string) $pattern));
                if ($pattern === '') {
                    continue;
                }
                if (str_starts_with($pattern, '*.')) {
                    $suffix = substr($pattern, 1); // ".example.com"
                    if ($suffix !== '' && str_ends_with($host, $suffix)) {
                        $allowed = true;
                        break;
                    }
                } else {
                    if ($host === $pattern) {
                        $allowed = true;
                        break;
                    }
                }
            }
            if (! $allowed) {
                return response()->json(['message' => 'Forbidden: domain not allowed'], 403);
            }
        }

        // Build a list of keys for response rotation
        $allowedKeys = $allEntries->pluck('key');

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

        // 5) Rotate the used key (one-time use): generate a new key and persist to config/public_api.php if it exists in entries
        $this->rotateApiKey($providedKey);

        return response()->json([
            'ok' => true,
            'lead_id' => $lead->id,
            'api_key' => $responseKey,
        ], $lead->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Replace a used API key in config/public_api.php entries[] with a newly generated key.
     * Returns the new key on success, or null if not rotated (e.g., key not found in entries or write failed).
     */
    protected function rotateApiKey(string $usedKey): ?string
    {
        try {
            $entries = config('public_api.entries', []);
            if (! is_array($entries) || empty($entries)) {
                return null; // nothing to rotate
            }

            $idx = null;
            foreach ($entries as $i => $entry) {
                if (is_array($entry) && isset($entry['key']) && hash_equals((string) $entry['key'], $usedKey)) {
                    $idx = $i;
                    break;
                }
            }
            if ($idx === null) {
                return null; // used key not from entries[] (likely from env keys)
            }

            $newKey = Str::random(64);
            $entries[$idx]['key'] = $newKey;

            // Rebuild config/public_api.php contents deterministically
            $path = base_path('config/public_api.php');
            $php = $this->buildPublicApiConfigPhp($entries);

            // Atomic-ish write
            $tmp = $path.'.tmp';
            if (file_put_contents($tmp, $php, LOCK_EX) === false) {
                return null;
            }
            if (! @rename($tmp, $path)) {
                // Fallback: write directly
                if (file_put_contents($path, $php, LOCK_EX) === false) {
                    return null;
                }
            }

            return $newKey;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildPublicApiConfigPhp(array $entries): string
    {
        // Use nowdoc to keep formatting simple; var_export for entries
        $entriesExport = var_export($entries, true);
        $php = <<<'PHP'
<?php

return [
    // 1) Simple keys via env (comma-separated). Example: PUBLIC_API_KEYS=key1,key2
    'keys' => collect(explode(',', (string) env('PUBLIC_API_KEYS', '')))
        ->map(fn ($v) => is_string($v) ? trim($v) : $v)
        ->filter(fn ($v) => is_string($v) && $v !== '')
        ->values()
        ->all(),

    // 2) Advanced entries allow per-key domain restrictions.
    // Each entry: ['key' => 'your-key', 'domains' => ['example.com', '*.example.org']]
    // If 'domains' is provided and non-empty, incoming requests must have an Origin or Referer host matching one of the patterns.
    'entries' => %s,
];
PHP;

        return sprintf($php, $entriesExport);
    }
}
