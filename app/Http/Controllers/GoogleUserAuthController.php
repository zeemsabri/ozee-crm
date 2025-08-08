<?php

namespace App\Http\Controllers;

use App\Models\GoogleAccounts;
use App\Services\GoogleUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class GoogleUserAuthController extends Controller
{
    /**
     * The Google User Service instance.
     *
     * @var \App\Services\GoogleUserService
     */
    protected $googleUserService;

    /**
     * Create a new controller instance.
     *
     * @param \App\Services\GoogleUserService $googleUserService
     * @return void
     */
    public function __construct(GoogleUserService $googleUserService)
    {
        $this->middleware('auth')->except('handleCallback');
        $this->googleUserService = $googleUserService;
    }

    /**
     * Redirect the user to the Google authentication page.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToGoogle()
    {
        $scopes = [
            'profile',
            'https://www.googleapis.com/auth/chat.spaces',
            'https://www.googleapis.com/auth/chat.messages',
            'https://www.googleapis.com/auth/chat.memberships',
        ];

        return Socialite::driver('google')
            ->scopes($scopes)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirectUrl(env('USER_REDIRECT_URL', 'services.google.user_redirect'))
            ->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleCallback(Request $request)
    {
        try {
             //Get the authenticated user
            $user = Auth::user();

            // If not authenticated through the web session, try to find user from state
            if (!$user && $request->has('state')) {
                $state = json_decode(base64_decode($request->state), true);
                if (isset($state['user_id'])) {
                    $user = \App\Models\User::find($state['user_id']);
                }
            }

            if (!$user) {
                return redirect()->route('login')
                    ->with('error', 'You must be logged in to connect your Google account.');
            }

            // Get the Google user
           $googleUser = Socialite::driver('google')->redirectUrl(env('USER_REDIRECT_URL'))->stateless()->user();

            // Store or update the user's Google credentials
            GoogleAccounts::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $googleUser->token,
                    'refresh_token' => $googleUser->refreshToken,
                    'expires_in' => $googleUser->expiresIn,
                    'created' => now()->timestamp,
                    'email' => $googleUser->email,
                ]
            );

            return redirect()->route('dashboard')
                ->with('success', 'Google account connected successfully.');
        } catch (\Exception $e) {
            Log::error('Google OAuth Callback Error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'request_url' => $request->fullUrl(),
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Failed to connect Google account. Please try again.');
        }
    }

    /**
     * Check if the user has connected their Google account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkGoogleConnection(Request $request)
    {
        $user = $request->user();
        $hasGoogleAccount = $user->hasGoogleCredentials();

        return response()->json([
            'connected' => $hasGoogleAccount,
        ]);
    }

    /**
     * Disconnect the user's Google account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function disconnectGoogle(Request $request)
    {
        $user = $request->user();
        $googleAccount = $user->googleAccount;

        if ($googleAccount) {
            $googleAccount->delete();
            return redirect()->back()->with('success', 'Google account disconnected successfully.');
        }

        return redirect()->back()->with('info', 'No Google account connected.');
    }
}
