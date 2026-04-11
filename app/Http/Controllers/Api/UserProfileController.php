<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
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
    public function status()
    {
        $user = Auth::user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'is_online' => (bool)$user->is_online,
            'extension_mandatory' => (bool)$user->extension_mandatory,
            'can_bypass' => (bool)$user->hasPermission('by_pass_extension'),
            'last_activity' => $user->last_activity,
            'telegram_link_code' => $user->telegram_link_code,
            'telegram_account' => $user->telegramAccount,
        ]);
    }
}
