<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
    private const EXTENSION_VERSION_CACHE_HOURS = 8;

    /**
     * Explicitly update the authenticated user's online status.
     */
    public function updateOnlineStatus(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:online,offline,active,inactive',
            'reason' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = $request->string('status')->lower()->value();
        $isOnline = in_array($status, ['online', 'active'], true);

        $context = array_merge($request->input('metadata', []), [
            'source' => 'presence_endpoint',
            'requested_status' => $status,
            'reason' => $request->input('reason'),
        ]);

        $changed = $user->setOnlineStatus($isOnline, $context);

        return response()->json([
            'message' => $changed ? 'Online status updated successfully.' : 'Online status already set.',
            'status' => $isOnline ? 'online' : 'offline',
            'is_online' => $isOnline,
            'changed' => $changed,
            'last_status_change' => data_get($user->fresh()->online_data, 'last_status_change'),
        ]);
    }

    /**
     * Update a single profile field for the authenticated user.
     * Accepts a generic payload and updates allowed fields only.
     */
    public function updateField(Request $request)
    {
        $user = Auth::user();

        // Define allowed fields and validation rules
        $allowed = [
            'timezone' => 'required|string|max:255',
            // In the future, add more fields like: 'phone' => 'required|string|max:50'
        ];

        $field = $request->input('field');
        $value = $request->input('value');

        if (! $field || ! array_key_exists($field, $allowed)) {
            return response()->json([
                'message' => 'Field is not allowed to be updated via this endpoint.',
            ], 422);
        }

        $validator = Validator::make(['value' => $value], ['value' => $allowed[$field]]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->{$field} = $value;
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->only(['id', 'name', 'email', 'timezone']),
        ]);
    }

    /**
     * Get the current user's online status and extension settings.
     */
    public function status(Request $request)
    {
        $user = Auth::user();

        $extensionVersionStatus = $this->getExtensionVersionStatus(
            $user,
            $request->boolean('force')
        );

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'is_online' => (bool) $user->is_online,
            'extension_mandatory' => (bool) $user->extension_mandatory,
            'can_bypass' => (bool) $user->hasPermission('by_pass_extension'),
            'last_activity' => $user->last_activity,
            'last_status_change' => data_get($user->online_data, 'last_status_change'),
            'telegram_link_code' => $user->telegram_link_code,
            'telegram_account' => $user->telegramAccount,
        ] + $extensionVersionStatus);
    }

    /**
     * Resolve the user's cached extension version status from recent activity metadata.
     *
     * @return array<string, mixed>
     */
    private function getExtensionVersionStatus($user, bool $forceRefresh = false): array
    {
        $cacheKey = "user-extension-version-status:{$user->id}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember(
            $cacheKey,
            now()->addHours(self::EXTENSION_VERSION_CACHE_HOURS),
            function () use ($user) {
                $latestActivity = UserActivity::query()
                    ->where('user_id', $user->id)
                    ->whereNotNull('metadata')
                    ->latest('recorded_at')
                    ->latest('id')
                    ->limit(50)
                    ->get(['metadata', 'recorded_at'])
                    ->first(fn (UserActivity $activity) => filled(data_get($activity->metadata, 'extension_version')));

                $reportedVersion = $latestActivity
                    ? data_get($latestActivity->metadata, 'extension_version')
                    : null;

                $requiredVersion = config('services.extension.version');
                $isMissingVersion = filled($requiredVersion) && blank($reportedVersion);
                $isOutdatedVersion = filled($requiredVersion)
                    && filled($reportedVersion)
                    && version_compare($reportedVersion, $requiredVersion, '<');

                return [
                    'reported_extension_version' => $reportedVersion,
                    'required_extension_version' => $requiredVersion,
                    'extension_version_last_seen_at' => $latestActivity?->recorded_at?->toIso8601String(),
                    'extension_version_checked_at' => now()->toIso8601String(),
                    'extension_version_missing' => $isMissingVersion,
                    'extension_version_outdated' => $isOutdatedVersion,
                    'extension_reminder_reason' => $isMissingVersion
                        ? 'missing_version'
                        : ($isOutdatedVersion ? 'outdated_version' : null),
                ];
            }
        );
    }
}
