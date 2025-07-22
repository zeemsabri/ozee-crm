<?php

namespace App\Http\Controllers;

use Google\Service\Drive;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Storage; // To store tokens temporarily
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    /**
     * Redirects the user to the Google authentication page.
     * This method initiates the OAuth 2.0 flow.
     * You will access this route once in your browser for initial setup.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToGoogle()
    {
        // Define the Gmail scopes needed for sending and modifying (receiving) emails.
        // 'offline' access_type is crucial to get a refresh token for long-lived access.
        // 'prompt=consent' ensures the consent screen is shown every time (useful during development).
        $scopes = [
            Gmail::GMAIL_SEND,
            Gmail::GMAIL_MODIFY, // Provides access for reading/modifying labels, which is helpful for receiving.
            'email',             // To get the user's email address
            'profile',           // To get basic profile info
            Drive::DRIVE_FILE,
            'https://www.googleapis.com/auth/chat.spaces',
            'https://www.googleapis.com/auth/chat.messages',
//            'https://www.googleapis.com/auth/chat.memberships',

        ];

        return Socialite::driver('google')
            ->scopes($scopes)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->stateless() // Essential for API flows, prevents session-related issues.
            ->redirect();
    }

    /**
     * Handles the Google OAuth callback.
     * Google redirects back to this URL after the user grants/denies permission.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            // Retrieve the user from Google's response.
            $googleUser = Socialite::driver('google')->stateless()->user();

            // For this MVP, we store the tokens in a local file.
            // In a real application with users, these tokens would be stored in the database
            // associated with the user account that granted the permission (e.g., your Super Admin).
            $tokens = [
                'access_token' => $googleUser->token,
                'refresh_token' => $googleUser->refreshToken, // This is key for long-term access without re-auth
                'expires_in' => $googleUser->expiresIn,       // Time until access token expires (in seconds)
                'created_at' => now()->timestamp,             // Timestamp when token was obtained
                'email' => $googleUser->email,                // The email of the authorized Google account
            ];

            // Store the tokens in storage/app/google_tokens.json
            Storage::disk('local')->put('google_tokens.json', json_encode($tokens, JSON_PRETTY_PRINT));

            Log::info('Google authorization successful', ['email' => $googleUser->email, 'token_file' => 'google_tokens.json']);

            return response()->json([
                'message' => 'Google authorization successful! Tokens stored in storage/app/google_tokens.json.',
                'authorized_email' => $googleUser->email,
                'note' => 'You can now proceed with sending/receiving tests.',
            ], 200);

        } catch (\Exception $e) {
            // Log the full error for debugging purposes.
            Log::error('Google OAuth Callback Error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'request_url' => $request->fullUrl(),
            ]);
            return response()->json([
                'message' => 'Google authorization failed. Please check your logs for details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
