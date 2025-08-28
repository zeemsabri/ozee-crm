<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
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
}
