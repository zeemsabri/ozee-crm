<?php

namespace App\Traits;

use App\Models\User;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Drive;
use Google\Service\Gmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Trait for managing Google API authentication and services.
 * This refactored version improves dependency injection,
 * separates concerns, and centralizes token handling.
 */
trait GoogleApiAuthTrait
{

    const APP_AS_USER = 'app';

    /** @var Client The Google client instance. */
    protected $client;

    /** @var string The authorized email address. */
    protected $authorizedEmail;

    /** @var Drive The Google Drive service instance. */
    protected $driveService;

    /** @var Calendar The Google Calendar service instance. */
    protected $calendarService;

    /** @var string The ID of the calendar to use. */
    protected $calendarId = 'primary';

    /** @var Gmail The Google Gmail service instance. */
    protected $gmailService;

    protected User|null|string $user = null;

    public function __construct()
    {
        $this->initializeGoogleClient();
        $this->setDriveScope();
        $this->setCalendarScope();
        $this->setGmailScope();
        $this->setGoogleChatScope();
    }

    /**
     * Initializes the Google Client with an authenticated user or a default account.
     *
     * @param User|null $user The authenticated user model.
     * @return $this
     */
    protected function initializeGoogleClient(User|null $user = null): self
    {

        try {
            // Set up the client instance with credentials
            $this->client = new Client();
            $this->client->setClientId(config('services.google.client_id'));
            $this->client->setClientSecret(config('services.google.client_secret'));
            $this->client->setAccessType('offline'); // Ensures we get a refresh token

            // Load tokens and authorized email based on the user or default account
            $this->loadTokens($user);

            // Check if the access token is expired and refresh it if necessary.
            if ($this->client->isAccessTokenExpired()) {
                $this->refreshAccessToken($user);
            }
        }
        catch (Exception $e) {

            if($user) {
                $user->googleAccount()?->delete();
            }
            $this->loadTokens();
            if ($this->client->isAccessTokenExpired()) {
                $this->refreshAccessToken();
            }
        }


        return $this;
    }

    /**
     * Loads the access tokens from the user's account or a local file.
     *
     * @param User|null $user The authenticated user model.
     * @return void
     * @throws Exception If tokens cannot be retrieved.
     */
    private function loadTokens(User|null $user =null): void
    {
        $tokens = null;
        if ($user && $googleAccount = $user->googleAccount) {
            $tokens = $googleAccount->tokens;
            $this->authorizedEmail = $googleAccount->email;
        } elseif (Storage::disk('local')->exists('google_tokens.json')) {
            $tokens = Storage::disk('local')->get('google_tokens.json');
            $this->authorizedEmail = 'info@ozeeweb.com.au';
        }

        if ($tokens) {
            $this->client->setAccessToken(json_decode($tokens, true));
        } else {
            throw new Exception('Google access tokens not found.');
        }
    }

    /**
     * Refreshes the Google access token and saves the new tokens.
     *
     * @param User|null $user The authenticated user model.
     * @return void
     * @throws Exception On failure to refresh the token.
     */
    private function refreshAccessToken(User|null $user = null): void
    {
        try {
            $accessToken = $this->client->getAccessToken();

            if(!$this->client->getClientId()) {
                return;
            }
            // We use the existing refresh token to get a new access token
            $newTokens = $this->client->fetchAccessTokenWithRefreshToken($accessToken['refresh_token']);

            if(!ISSET($newTokens['access_token'])) {
                throw new Exception('Failed to refresh Google access token.');
            }

            // Update the client with the new tokens
            $this->client->setAccessToken($newTokens);

            // Prepare the data to be saved
            $updateData = [
                'access_token' => $newTokens['access_token'] ?? null,
                'expires_in' => $newTokens['expires_in'] ?? null,
                'created' => now()->timestamp,
                'email' => $this->authorizedEmail ?? null,
            ];

            // If a new refresh token is provided, update it
            if (isset($newTokens['refresh_token'])) {
                $updateData['refresh_token'] = $newTokens['refresh_token'];
            }

            // Store the updated tokens
            if ($user) {
                $user->googleAccount()->firstOrCreate(['email' => $updateData['email']])->update($updateData);
            } elseif ($this->authorizedEmail === env('GOOGLE_PRIMARY_EMAIL', config('services.google.primary_email'))) {
                Storage::disk('local')->put('google_tokens.json', json_encode($updateData, JSON_PRETTY_PRINT));
            }

            Log::info('Google access token refreshed successfully.', ['email' => $this->authorizedEmail]);
        } catch (Exception $e) {
            Log::error('Failed to refresh Google access token: ' . $e->getMessage(), [
                'email' => $this->authorizedEmail,
                'error' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Sets the Gmail service scope and instantiates the service.
     *
     * @param User|null $user The authenticated user model.
     * @return $this
     */
    protected function setGmailScope(User|null $user = null): self
    {
        $this->initializeGoogleClient($user);

        $this->client->addScope([
            Gmail::GMAIL_SEND,
            Gmail::GMAIL_MODIFY,
            Calendar::CALENDAR_EVENTS,
            Drive::DRIVE_FILE,
            'email',
            'profile',
            'https://www.googleapis.com/auth/chat.spaces',
            'https://www.googleapis.com/auth/chat.messages',
            'https://www.googleapis.com/auth/chat.memberships'
        ]);
        $this->gmailService = new Gmail($this->client);
        return $this;
    }

    protected function setGoogleChatScope(User|null $user = null): self
    {
        $this->initializeGoogleClient($user);
        $this->client->addScope([
            'profile',
            'https://www.googleapis.com/auth/chat.spaces',
            'https://www.googleapis.com/auth/chat.messages',
            'https://www.googleapis.com/auth/chat.memberships'
        ]);
        return $this;
    }

    /**
     * Sets the Drive service scope and instantiates the service.
     *
     * @param User|null $user The authenticated user model.
     * @return $this
     */
    protected function setDriveScope(User|null $user = null): self
    {
        $this->initializeGoogleClient($user);
        $this->client->addScope(Drive::DRIVE_METADATA_READONLY);
        $this->driveService = new Drive($this->client);
        return $this;
    }

    /**
     * Sets the Calendar service scope and instantiates the service.
     *
     * @param User|null $user The authenticated user model.
     * @return $this
     */
    protected function setCalendarScope(User|null $user = null): self
    {
        $this->initializeGoogleClient($user);
        $this->client->addScope(Calendar::CALENDAR);
        $this->calendarService = new Calendar($this->client);
        return $this;
    }

    /**
     * Sets the calendar ID to use for operations.
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
     * Get the Google Client instance.
     *
     * @return Client
     */
    public function getGoogleClient(): Client
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
