<?php

namespace App\Traits;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

trait GoogleApiAuthTrait
{
    protected $client;
    protected $authorizedEmail;

    /**
     * Initialize the Google Client with tokens from storage.
     *
     * @return void
     */
    protected function initializeGoogleClient()
    {
        // Load the stored tokens
        $tokens = json_decode(Storage::disk('local')->get('google_tokens.json'), true);
        $this->client = new GoogleClient();
        $this->client->setAccessToken($tokens['access_token']);
        $this->authorizedEmail = $tokens['email'];

        // Check if token is expired and refresh if necessary
        if ($this->client->isAccessTokenExpired()) {
            try {
                $this->client->fetchAccessTokenWithRefreshToken($tokens['refresh_token']);
                $newTokens = [
                    'access_token' => $this->client->getAccessToken()['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? null,
                    'expires_in' => $tokens['expires_in'] ?? 3600,
                    'created_at' => now()->timestamp,
                    'email' => $tokens['email'] ?? null,
                ];
                Storage::disk('local')->put('google_tokens.json', json_encode($newTokens, JSON_PRETTY_PRINT));
                Log::info('Google access token refreshed', ['email' => $this->authorizedEmail]);
            } catch (\Exception $e) {
                Log::error('Failed to refresh Google access token: ' . $e->getMessage(), [
                    'email' => $this->authorizedEmail,
                    'error' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        }
    }

    /**
     * Get the Google Client instance.
     *
     * @return GoogleClient
     */
    public function getGoogleClient(): GoogleClient
    {
        return $this->client;
    }

    /**
     * Get the authorized email address.
     *
     * @return string
     */
    public function getAuthorizedEmail(): string
    {
        return $this->authorizedEmail;
    }
}
