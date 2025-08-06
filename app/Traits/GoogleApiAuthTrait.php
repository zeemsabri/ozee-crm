<?php

namespace App\Traits;

use Google\Client;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Drive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

trait GoogleApiAuthTrait
{
    protected $client;
    protected $authorizedEmail;

    protected $driveService;

    protected $calendarService;

    protected $calendarId;

    public function __construct()
    {
        $this->initializeGoogleClient();
        $this->setDriveScope();
        $this->setCalendarScope();
    }

    private function setDriveScope()
    {
        $this->driveService = new Drive($this->getGoogleClient());
        // Scopes are added during the OAuth flow in GoogleAuthController, but the client needs to know them for validation
        $this->client->addScope('https://www.googleapis.com/auth/chat.spaces');
        $this->client->addScope('https://www.googleapis.com/auth/chat.messages');
    }

    private function setCalendarScope()
    {
        $this->calendarService = new Calendar($this->getGoogleClient());
        $this->calendarId = 'primary'; // Default to the user's primary calendar
    }

    /**
     * Set the calendar ID to use for operations.
     *
     * @param string $calendarId
     * @return $this
     */
    public function setCalendarId(string $calendarId): self
    {
        $this->calendarId = $calendarId;
        return $this;
    }

    /**
     * Initialize the Google Client with tokens from storage.
     *
     * @return void
     */
    protected function initializeGoogleClient()
    {
        // Load the stored tokens
        $tokens = Storage::disk('local')->get('google_tokens.json');
        $parsedToken = json_decode($tokens, true);
        $this->client = new GoogleClient();
        $this->client->setAccessToken($tokens);
        $this->authorizedEmail = $parsedToken['email'] ?? null;
        // Check if token is expired and refresh if necessary
        if ($this->client->isAccessTokenExpired()) {
            try {

                $tokens = $this->getNewTokens($parsedToken['refresh_token']);
                $newTokens = [
                    'access_token' => $this->client->getAccessToken()['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? null,
                    'expires_in' => $tokens['expires_in'] ?? 3600,
                    'created' => now()->timestamp,
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

    private function getNewTokens($accessToken)
    {
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        return $this->client->fetchAccessTokenWithRefreshToken($accessToken);
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
