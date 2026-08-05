<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use GPBMetadata\Google\Api\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\GenericOtpService;
use App\Services\RememberDeviceService;
use App\Mail\GenericOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

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

        if ($user->user_type === 'supplier') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'Access denied. Supplier accounts cannot log in.',
            ]);
        }

        // Check for extension mandatory enforcement
        if ($user->extension_mandatory && !$user->is_online) {
            // Check if user has bypass permission
            if ($user->hasPermission('by_pass_extension')) {
                // If they haven't confirmed the bypass yet, show warning
                if (!$request->boolean('bypass_extension')) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'extension_bypass_required' => 'You are currently offline. As an administrator, you can bypass this check, but your activity will not be tracked.',
                    ]);
                }
            } else {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'Access denied. You must be online via the Chrome extension to log in.',
                ]);
            }
        }

        $user->load(['role.permissions']);

        // Update user's timezone (if provided) and last login timestamp
        $timezone = $request->input('timezone');
        if ($timezone && is_string($timezone)) {
            $user->timezone = $timezone;
        }
        $user->last_login_at = now();
        $user->save();

        if ($request->wantsJson() || $request->isXmlHttpRequest()) {
            $rememberService = app(RememberDeviceService::class);
            $otpService = app(GenericOtpService::class);
            
            $otpEnabled = filter_var(config('services.otp_enabled'), FILTER_VALIDATE_BOOLEAN);

            if ($otpEnabled && !$rememberService->isDeviceRemembered($user, $request)) {
                // Log them out temporarily until OTP is verified
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                try {
                    // Generate OTP
                    $otp = $otpService->generate($user->email, 'login');
                    Mail::to($user->email)->send(new GenericOtpMail($otp));
                } catch (ValidationException $e) {
                    // If it's a cooldown exception, we just proceed and show the OTP screen
                    // again without generating a new code.
                    if (!isset($e->errors()['otp'])) {
                        throw $e;
                    }
                }

                return response()->json([
                    'requires_otp' => true,
                    'identifier' => $user->email,
                ]);
            }

            // If device is remembered, proceed with login
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
     * Handle an incoming authentication request.
     */
    public function storeapp(LoginRequest $request): RedirectResponse|JsonResponse
    {

        \Illuminate\Support\Facades\Log::info('User logged in try');
        $request->authenticate();

        $request->session()->regenerate();

        // Load the user's role with permissions to ensure they're available immediately after login
        $user = $request->user();

        \Illuminate\Support\Facades\Log::info('User logged in: ' . $user->name);

        if ($user->user_type === 'supplier') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Access denied. Supplier accounts cannot log in.',
                'errors' => ['email' => ['Access denied. Supplier accounts cannot log in.']]
            ], 422);
        }

        // Check for extension mandatory enforcement
        if ($user->extension_mandatory && !$user->is_online) {
            // Check if user has bypass permission
            if ($user->hasPermission('by_pass_extension')) {
                // If they haven't confirmed the bypass yet, show warning
                if (!$request->boolean('bypass_extension')) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return response()->json([
                        'message' => 'Extension bypass required',
                        'errors' => ['extension_bypass_required' => ['You are currently offline. As an administrator, you can bypass this check, but your activity will not be tracked.']]
                    ], 422);
                }
            } else {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->json([
                    'message' => 'Access denied. You must be online via the Chrome extension to log in.',
                    'errors' => ['email' => ['Access denied. You must be online via the Chrome extension to log in.']]
                ], 422);
            }
        }

        $user->load(['role.permissions']);

        // Update user's timezone (if provided) and last login timestamp
        $timezone = $request->input('timezone');
        if ($timezone && is_string($timezone)) {
            $user->timezone = $timezone;
        }
        $user->last_login_at = now();
        $user->save();

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

    /**
     * Handle an incoming authentication request from third-party applications.
     * Returns only a token without session management.
     */
    public function getToken(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        // Load the user with role and permissions
        $user = $request->user();
        $user->load(['role.permissions']);

        // Create a token for the authenticated user
        $token = $user->createToken('third-party-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'role' => $user->role->name,
        ]);
    }

    /**
     * Revoke the user's API token.
     * Used for API logout functionality for third-party applications.
     */
    public function revokeToken(Request $request): JsonResponse
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token revoked successfully',
        ]);
    }

    /**
     * Verify OTP for login
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|email',
            'otp' => 'required|string',
            'remember' => 'boolean',
        ]);

        $otpService = app(GenericOtpService::class);
        $result = $otpService->verify($request->identifier, $request->otp, 'login');

        if (!$result['success']) {
            throw ValidationException::withMessages([
                'otp' => $result['message'],
            ]);
        }

        // OTP is valid. Log the user in.
        $user = \App\Models\User::where('email', $request->identifier)->firstOrFail();
        Auth::guard('web')->login($user, false);

        $request->session()->regenerate();

        if ($request->boolean('remember')) {
            $rememberService = app(RememberDeviceService::class);
            // We'll queue the cookie so it's attached to the response
            Cookie::queue($rememberService->rememberDevice($user, $request));
        }

        $user->load(['role.permissions', 'projects']);
        $token = $user->createToken($user->email)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'role' => $user->role,
        ]);
    }

    /**
     * Resend OTP for login
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|email',
        ]);

        // Just ensure user exists
        $user = \App\Models\User::where('email', $request->identifier)->firstOrFail();

        $otpService = app(GenericOtpService::class);
        $otp = $otpService->generate($user->email, 'login');
        Mail::to($user->email)->send(new GenericOtpMail($otp));

        return response()->json([
            'message' => 'OTP resent successfully.',
        ]);
    }
}
