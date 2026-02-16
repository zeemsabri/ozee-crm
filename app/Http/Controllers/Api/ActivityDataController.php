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

        if ($user) {
            $activityData = $payload['data'] ?? [];
            $url = $activityData['url'] ?? '';
            $domain = parse_url($url, PHP_URL_HOST) ?? 'unknown';
            $now = isset($payload['timestamp']) ? Carbon::parse($payload['timestamp']) : now();
            
            // Merging Threshold: 5 minutes (300 seconds)
            $mergingThreshold = 300;

            // Find the most recent activity for this user
            $lastActivity = UserActivity::where('user_id', $user->id)
                ->latest('recorded_at')
                ->first();

            // Check if we should merge with the last heartbeat
            // Conditions: Same domain AND within the merging threshold from the last heartbeat
            if ($lastActivity && 
                $lastActivity->domain === $domain && 
                $now->diffInSeconds($lastActivity->last_heartbeat_at ?: $lastActivity->recorded_at) < $mergingThreshold) {
                
                // Update existing record
                $lastActivity->update([
                    'last_heartbeat_at' => $now,
                    'duration' => $now->diffInSeconds($lastActivity->recorded_at),
                    // Optionally update title/url if you want the latest one
                    'title' => $activityData['title'] ?? $lastActivity->title,
                    'url' => $url ?: $lastActivity->url,
                    'tab_count' => $activityData['tabCount'] ?? $lastActivity->tab_count,
                ]);
            } else {
                // Create a new activity block
                UserActivity::create([
                    'user_id' => $user->id,
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
                    'duration' => 0,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data received and saved.'
        ]);
    }
}
