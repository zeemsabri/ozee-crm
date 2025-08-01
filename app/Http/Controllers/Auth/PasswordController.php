<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // Get the user instance
        $user = $request->user();

        // Unset the global_permissions attribute before updating to prevent SQL error
        if (isset($user->global_permissions)) {
            unset($user->global_permissions);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back();
    }
}
