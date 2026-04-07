<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ActivityDataController extends Controller
{
    private $taskIdChecked = [];

    /**
     * Validate Task ID existence and return it or null if invalid.
     *
     * @param mixed $taskId
     * @return int|null
     */
    private function getValidatedTaskId($taskId)
    {
        if (empty($taskId) || !is_numeric($taskId)) {
            return null;
        }

        if (isset($this->taskIdChecked[$taskId])) {
            return $this->taskIdChecked[$taskId] ? (int) $taskId : null;
        }

        $exists = \App\Models\Task::where('id', $taskId)->exists();
        $this->taskIdChecked[$taskId] = $exists;

        return $exists ? (int) $taskId : null;
    }

    /**
     * Store activity data in a JSON file and database for reporting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $raw = $request->all();
        $requestMetadata = $this->extractRequestMetadata($request);

        Log::info('Received activity data', [
            'user_id' => $user?->id,
            'extension_version' => $requestMetadata['extension_version'] ?? null,
            'data' => $raw,
        ]);
        // Detect batch mode: either { events: [...] } or a direct array [...]
        $payloads = null;
        if (isset($raw['events']) && is_array($raw['events'])) {
            $payloads = $raw['events'];
        } elseif (isset($raw[0]) || (is_array($raw) && array_is_list($raw) && count($raw) > 0)) {
            $payloads = $raw;
        }

        if ($payloads !== null) {
            // ── BATCH MODE ──────────────────────────────────────────────────────────
            // Process each item in order. We thread $lastActivity through the loop so
            // we only do ONE initial DB query per batch instead of N queries.
            if ($user && count($payloads) > 0) {
                $lastActivity = UserActivity::where('user_id', $user->id)
                    ->latest('last_heartbeat_at')
                    ->latest('id')
                    ->first();

                foreach ($payloads as $payload) {
                    $lastActivity = $this->processPayload($payload, $user, $lastActivity, $requestMetadata);
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Batch data received and saved.',
                'count'   => count($payloads),
                'activeTask'    =>  $user->activeTask
            ]);
        }

        // ── SINGLE MODE ─────────────────────────────────────────────────────────────
        $payload = $request->input('payload', $raw);

        if ($user) {
            $lastActivity = UserActivity::where('user_id', $user->id)
                ->latest('last_heartbeat_at')
                ->latest('id')
                ->first();

            $this->processPayload($payload, $user, $lastActivity, $requestMetadata);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Data received and saved.',
            'activeTask'    =>  $user->activeTask
        ]);
    }

    /**
     * Process a single activity payload for the given user.
     * Returns the activity record that was created or updated (useful for batch chaining).
     *
     * @param  array                        $payload
     * @param  \App\Models\User             $user
     * @param  \App\Models\UserActivity|null $lastActivity
     * @param  array<string, mixed>         $requestMetadata
     * @return \App\Models\UserActivity|null
     */
    private function processPayload(array $payload, $user, ?UserActivity $lastActivity, array $requestMetadata = []): ?UserActivity
    {
        $activityData    = $payload['data'] ?? [];
        $type            = $activityData['type'] ?? 'heartbeat';

        if ($type === 'session_pulse') {
            $sessionStatus = $activityData['sessionStatus'] ?? 'inactive';
            $isNowOnline   = ($sessionStatus === 'active');

            if ($user->is_online !== $isNowOnline) {
                $user->update([
                    'is_online' => $isNowOnline,
                    'online_data' => array_merge($user->online_data ?? [], [
                        'last_status_change' => now()->toIso8601String()
                    ])
                ]);

                // Use Spatie Activity Log for status changes
                activity('online_status')
                    ->performedOn($user)
                    ->withProperties(['status' => $isNowOnline ? 'online' : 'offline', 'data' => $activityData])
                    ->log("User went " . ($isNowOnline ? 'online' : 'offline'));
            }
            return null; // session_pulse doesn't write to UserActivity table
        }

        $taskId          = $this->getValidatedTaskId($activityData['taskId'] ?? null);
        $url             = $activityData['url'] ?? '';
        $domain          = parse_url($url, PHP_URL_HOST) ?? 'unknown';
        $now             = isset($payload['timestamp']) ? Carbon::parse($payload['timestamp']) : now();
        $durationReported = max(0, (int) ($payload['duration'] ?? 0));
        $idleState       = $payload['idleState'] ?? 'unknown';
        $category        = $this->categorizeDomain($domain, $user->id);
        $metadata        = $this->buildActivityMetadata($payload, $requestMetadata);

        // Session Merging Threshold: 2 minutes (120 seconds)
        $mergingThreshold = 120;

        $shouldMerge = $lastActivity &&
                       $lastActivity->domain === $domain &&
                       $lastActivity->idle_state === $idleState &&
                       $lastActivity->task_id == $taskId &&
                       $now->diffInSeconds($lastActivity->last_heartbeat_at) <= $mergingThreshold;

        if ($shouldMerge) {
            // UPDATE: Absorb the new duration into the existing row
            $lastActivity->update([
                'last_heartbeat_at' => $now,
                'duration'          => $lastActivity->duration + $durationReported,
                'title'             => $activityData['title'] ?? $lastActivity->title,
                'url'               => $url ?: $lastActivity->url,
                'tab_count'         => $activityData['tabCount'] ?? $lastActivity->tab_count,
                'metadata'          => $this->mergeActivityMetadata($lastActivity->metadata, $metadata),
            ]);

            return $lastActivity;
        }

        // CREATE: Start a new session row
        return UserActivity::create([
            'user_id'          => $user->id,
            'task_id'          => $taskId,
            'domain'           => $domain,
            'url'              => $url,
            'title'            => $activityData['title'] ?? null,
            'is_incognito'     => $activityData['incognito'] ?? false,
            'is_audible'       => $activityData['audible'] ?? false,
            'tab_count'        => $activityData['tabCount'] ?? 0,
            'hostname'         => $payload['hostname'] ?? null,
            'browser'          => $payload['browser'] ?? null,
            'recorded_at'      => $now,
            'last_heartbeat_at' => $now,
            'duration'         => $durationReported,
            'idle_state'       => $idleState,
            'category'         => $category,
            'metadata'         => $metadata,
        ]);
    }

    /**
     * Extract request-level metadata that should be attached to each activity row.
     *
     * @return array<string, mixed>
     */
    private function extractRequestMetadata(Request $request): array
    {
        return array_filter([
            'extension_version' => $request->header('X-EXTENSION-VERSION'),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Build metadata that is useful for reporting but not worth a dedicated column.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $requestMetadata
     * @return array<string, mixed>
     */
    private function buildActivityMetadata(array $payload, array $requestMetadata = []): array
    {
        $activityData = $payload['data'] ?? [];
        $url = $activityData['url'] ?? '';
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return array_filter([
            'extension_version' => $requestMetadata['extension_version'] ?? null,
            'event_count' => 1,
            'event_type' => $activityData['type'] ?? 'heartbeat',
            'session_status' => $activityData['sessionStatus'] ?? null,
            'url_scheme' => is_string($scheme) ? strtolower($scheme) : null,
            'is_internal_url' => $this->isInternalUrl($url, $scheme),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Merge newly observed metadata into the current activity row.
     *
     * @param  array<string, mixed>|null  $existingMetadata
     * @param  array<string, mixed>  $newMetadata
     * @return array<string, mixed>
     */
    private function mergeActivityMetadata(?array $existingMetadata, array $newMetadata): array
    {
        $mergedMetadata = array_replace($existingMetadata ?? [], $newMetadata);
        $mergedMetadata['event_count'] = (int) (($existingMetadata['event_count'] ?? 0) + ($newMetadata['event_count'] ?? 0));

        return $mergedMetadata;
    }

    /**
     * Identify browser-internal URLs such as chrome:// pages.
     */
    private function isInternalUrl(string $url, string|int|null $scheme = null): ?bool
    {
        if ($url === '') {
            return null;
        }

        $normalizedScheme = is_string($scheme) ? strtolower($scheme) : strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($normalizedScheme === '') {
            return null;
        }

        return !in_array($normalizedScheme, ['http', 'https'], true);
    }

    /**
     * Update the category of an activity.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCategory(Request $request, $id)
    {
        $request->validate([
            'category' => 'required|string|in:productive,development,communication,social_media,neutral,unproductive'
        ]);

        $activity = UserActivity::findOrFail($id);

        $activity->update([
            'category' => $request->category,
            'is_category_override' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully.',
            'activity' => $activity
        ]);
    }

    /**
     * Categorize a domain based on configured patterns and user overrides.
     *
     * @param  string  $domain
     * @param  int  $userId
     * @return string
     */
    private function categorizeDomain(string $domain, int|null $userId = null): string
    {
        $categories = config('activity_categories.categories', []);
        $defaultCategory = config('activity_categories.default_category', 'neutral');

        // Normalize domain (remove www., convert to lowercase)
        $normalizedDomain = strtolower(str_replace('www.', '', $domain));

        // FEATURE 2: Check if user has previously categorized this domain
        if ($userId) {
            $userOverride = UserActivity::where('user_id', $userId)
                ->where('domain', $domain)
                ->where('is_category_override', true)
                ->whereNotNull('category')
                ->latest('updated_at')
                ->first();

            if ($userOverride) {
                return $userOverride->category;
            }
        }

        // Check configured patterns
        foreach ($categories as $categoryKey => $categoryConfig) {
            $patterns = $categoryConfig['patterns'] ?? [];

            foreach ($patterns as $pattern) {
                // Check if the domain contains the pattern
                if (str_contains($normalizedDomain, strtolower($pattern))) {
                    return $categoryKey;
                }
            }
        }

        return $defaultCategory;
    }
}
