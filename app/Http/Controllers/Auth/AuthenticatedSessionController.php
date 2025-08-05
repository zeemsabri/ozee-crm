<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Load the user's role with permissions to ensure they're available immediately after login
        $user = $request->user();
        $user->load(['role.permissions']);

        if ($request->wantsJson() || $request->isXmlHttpRequest()) {
            // Revoke old tokens if you want only one active token per device
            // auth()->user()->tokens()->delete();

            // Create a new token for the authenticated user
            $token = $user->createToken($request->email)->plainTextToken;

            return response()->json([
                'user' => $user->load('projects'), // Load projects so the frontend has immediate access
                'token' => $token,
                'role' => $user->role, // Explicitly send role
            ]);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
