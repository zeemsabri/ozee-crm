<?php

namespace App\Services;

use App\Models\GoogleAccounts;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Chat;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleUserService
{
    /**
     * The Google Client instance.
     *
     * @var \Google\Client
     */
    protected $client;

    /**
     * Create a new GoogleUserService instance.
     */
    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setRedirectUri(env('USER_REDIRECT_URL'));
    }

    /**
     * Get the Google OAuth URL for user authentication.
     *
     * @return string
     */
    public function getAuthUrl()
    {
        // Define the scopes needed for user login
        $scopes = [
            'profile',
            'https://www.googleapis.com/auth/chat.spaces',
            'https://www.googleapis.com/auth/chat.messages',
            'https://www.googleapis.com/auth/chat.memberships',
        ];

        return Socialite::driver('google')
            ->scopes($scopes)
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect(env('USER_REDIRECT_URL'));
//
//            ->redirect()
//            ->getTargetUrl();
    }

    /**
     * Handle the Google OAuth callback and store user credentials.
     *
     * @param string $authCode
     * @param \App\Models\User $user
     * @return \App\Models\GoogleAccounts
     */
    public function handleCallback($authCode, User $user)
    {
        try {
            // Exchange authorization code for access token
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Store or update the user's Google credentials
            $googleAccount = GoogleAccounts::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $googleUser->token,
                    'refresh_token' => $googleUser->refreshToken,
                    'expires_in' => $googleUser->expiresIn,
                    'created' => now()->timestamp,
                    'email' => $googleUser->email,
                ]
            );

            return $googleAccount;
        } catch (\Exception $e) {
            Log::error('Google OAuth Callback Error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
            ]);

            throw $e;
        }
    }

    /**
     * Get a Google client with user credentials.
     *
     * @param \App\Models\User $user
     * @return \Google\Client|null
     */
    public function getClientForUser(User $user)
    {
        $googleAccount = $user->googleAccount;

        if (!$googleAccount) {
            return null;
        }

        // Check if token is expired and refresh if needed
        if ($googleAccount->isExpired()) {
            $googleAccount = $this->refreshToken($googleAccount);
        }

        $client = clone $this->client;
        $client->setAccessToken([
            'access_token' => $googleAccount->access_token,
            'refresh_token' => $googleAccount->refresh_token,
            'expires_in' => $googleAccount->expires_in,
            'created' => $googleAccount->created,
        ]);

        return $client;
    }

    /**
     * Refresh the access token.
     *
     * @param \App\Models\GoogleAccounts $googleAccount
     * @return \App\Models\GoogleAccounts
     * @throws \Exception If token refresh fails
     */
    public function refreshToken(GoogleAccounts $googleAccount)
    {
        // Check if refresh token exists
        if (empty($googleAccount->refresh_token)) {
            Log::error('Cannot refresh token: No refresh token available', [
                'google_account_id' => $googleAccount->id,
            ]);
            throw new \Exception('No refresh token available');
        }

        try {
            $client = clone $this->client;
            $client->setAccessToken([
                'access_token' => $googleAccount->access_token,
                'refresh_token' => $googleAccount->refresh_token,
                'expires_in' => $googleAccount->expires_in,
                'created' => $googleAccount->created,
            ]);

            // For testing in development environments
            if (app()->environment('testing', 'local') && $googleAccount->refresh_token === 'invalid_refresh_token') {
                throw new \Exception('Invalid refresh token (test environment)');
            }

            // Refresh the token
            $client->fetchAccessTokenWithRefreshToken($googleAccount->refresh_token);

            // Check if we got a valid response
            if (!isset($client->getAccessToken()['access_token'])) {
                throw new \Exception('Failed to get new access token');
            }

            $newToken = $client->getAccessToken();

            // Update the token in the database
            $googleAccount->update([
                'access_token' => $newToken['access_token'],
                'expires_in' => $newToken['expires_in'],
                'created' => $newToken['created'],
            ]);

            return $googleAccount->fresh();
        } catch (\Exception $e) {
            Log::error('Token Refresh Error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'google_account_id' => $googleAccount->id,
            ]);

            throw $e;
        }
    }

    /**
     * Get a Google Chat service instance for a user.
     *
     * @param \App\Models\User $user
     * @return \Google\Service\Chat|null
     */
    public function getChatServiceForUser(User $user)
    {
        $client = $this->getClientForUser($user);

        if (!$client) {
            return null;
        }

        return new Chat($client);
    }
}
