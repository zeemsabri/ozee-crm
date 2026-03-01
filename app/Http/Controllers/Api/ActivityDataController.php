<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ActivityDataController extends Controller
{
    /**
     * Store activity data in a JSON file and database for reporting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $payload = $request->input('payload', $request->all());
        $user = $request->user();

        // Save to JSON for inspection (Debug mode)
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = rand(100000, 999999);
        $fileName = "activity_logs/data_{$timestamp}_{$random}.json";

        Storage::disk('local')->put($fileName, json_encode([
            'timestamp' => now()->toDateTimeString(),
            'ip' => $request->ip(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'payload' => $payload,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
        ], JSON_PRETTY_PRINT));

        if ($user) {
            $activityData = $payload['data'] ?? [];
            $taskId = $activityData['taskId'] ?? null;
            $url = $activityData['url'] ?? '';
            $domain = parse_url($url, PHP_URL_HOST) ?? 'unknown';
            $now = isset($payload['timestamp']) ? Carbon::parse($payload['timestamp']) : now();
            $durationReported = (int) ($payload['duration'] ?? 0);
            $idleState = $payload['idleState'] ?? 'unknown';
            $category = $this->categorizeDomain($domain, $user->id);

            // Session Merging Threshold: 2 minutes (120 seconds)
            // If the last activity was for the same domain, same idle state,
            // and happened within this threshold, we append the duration.
            $mergingThreshold = 120;

            $lastActivity = UserActivity::where('user_id', $user->id)
                ->latest('last_heartbeat_at')
                ->latest('id')
                ->first();

            $shouldMerge = $lastActivity &&
                           $lastActivity->domain === $domain &&
                           $lastActivity->idle_state === $idleState &&
                           $lastActivity->task_id == $taskId &&
                           $now->diffInSeconds($lastActivity->last_heartbeat_at) <= $mergingThreshold;

            if ($shouldMerge) {
                // UPDATE: Absorb the new duration into the existing row
                $lastActivity->update([
                    'last_heartbeat_at' => $now,
                    'duration' => $lastActivity->duration + $durationReported,
                    // Keep the title/url updated to the latest state
                    'title' => $activityData['title'] ?? $lastActivity->title,
                    'url' => $url ?: $lastActivity->url,
                    'tab_count' => $activityData['tabCount'] ?? $lastActivity->tab_count,
                ]);
            } else {
                // CREATE: Start a new session row
                UserActivity::create([
                    'user_id' => $user->id,
                    'task_id' => $taskId,
                    'domain' => $domain,
                    'url' => $url,
                    'title' => $activityData['title'] ?? null,
                    'is_incognito' => $activityData['incognito'] ?? false,
                    'is_audible' => $activityData['audible'] ?? false,
                    'tab_count' => $activityData['tabCount'] ?? 0,
                    'hostname' => $payload['hostname'] ?? null,
                    'browser' => $payload['browser'] ?? null,
                    'recorded_at' => $now,
                    'last_heartbeat_at' => $now,
                    'duration' => $durationReported,
                    'idle_state' => $idleState,
                    'category' => $category,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data received and saved.'
        ]);
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
    private function categorizeDomain(string $domain, int $userId = null): string
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
