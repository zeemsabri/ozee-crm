<?php

namespace App\Services;

// Import classes from the new google/apps-chat library
use Exception;
use Google\Apps\Chat\V1\Client\ChatServiceClient;
use Google\Apps\Chat\V1\Message;
// Import classes from the generic Google API client for authentication
use Google\Apps\Chat\V1\Thread;
use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleHttpClient;
use Illuminate\Support\Facades\Log;
// Import HttpClientCache for explicit credential handling
use Illuminate\Support\Facades\Storage;

class GoogleChatServiceV2
{
    protected GoogleClient $client; // Used for token management (same as before)

    protected ChatServiceClient $chatServiceClient; // The new Chat-specific client

    protected string $userEmail;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->client = new GoogleClient;
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        // Scopes are added during the OAuth flow in GoogleAuthController, but the client needs to know them for validation
        $this->client->addScope('https://www.googleapis.com/auth/chat.spaces');
        $this->client->addScope('https://www.googleapis.com/auth/chat.messages');

        $this->loadAndSetAccessToken();

        // 1. Get the current access token
        $accessToken = $this->client->getAccessToken()['access_token'];

        // 2. Create a Guzzle HTTP client with the access token in the Authorization header
        $guzzleClient = new GuzzleHttpClient([
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json', // Ensure content type is set
            ],
            // You might need to set 'http_errors' to false if you want to handle 4xx/5xx responses manually
            // 'http_errors' => false,
        ]);

        // 3. Initialize the new ChatServiceClient with this pre-configured Guzzle client
        // This bypasses the ApplicationDefaultCredentials lookup.
        $this->chatServiceClient = new ChatServiceClient([
            'httpClient' => $guzzleClient,
            // You can also explicitly set the API endpoint if needed, though usually not required
            // 'apiEndpoint' => 'chat.googleapis.com:443',
        ]);
        dd('hit');
    }

    /**
     * Loads tokens from storage and sets them on the Google Client.
     * Refreshes the access token if it's expired using the refresh token.
     *
     * @throws Exception If tokens are not found or cannot be refreshed.
     */
    protected function loadAndSetAccessToken(): void
    {
        if (! Storage::disk('local')->exists('google_tokens.json')) {
            throw new Exception('Google tokens not found. Please run the OAuth authorization flow first via /google/redirect.');
        }

        $tokens = json_decode(Storage::disk('local')->get('google_tokens.json'), true);

        if (empty($tokens['access_token'])) {
            throw new Exception('Access token not found in google_tokens.json. Re-authorize.');
        }

        $this->client->setAccessToken($tokens['access_token']);

        if ($this->client->isAccessTokenExpired()) {
            if (empty($tokens['refresh_token'])) {
                throw new Exception('Refresh token not found. User needs to re-authorize for Google Chat V2.');
            }

            try {
                $this->client->fetchAccessTokenWithRefreshToken($tokens['refresh_token']);
                $newTokens = $this->client->getAccessToken();

                $tokens['access_token'] = $newTokens['access_token'];
                $tokens['expires_in'] = $newTokens['expires_in'];
                $tokens['created_at'] = now()->timestamp;

                if (isset($newTokens['refresh_token'])) {
                    $tokens['refresh_token'] = $newTokens['refresh_token'];
                }

                Storage::disk('local')->put('google_tokens.json', json_encode($tokens, JSON_PRETTY_PRINT));

            } catch (Exception $e) {
                Log::error('Failed to refresh Google access token for Chat Service V2: '.$e->getMessage(), ['exception' => $e]);
                throw new Exception('Failed to refresh Google access token for Google Chat V2. Please re-authorize.');
            }
        }

        $this->userEmail = $tokens['email'] ?? '';
        if (empty($this->userEmail)) {
            Log::warning('Could not determine authorized user\'s email for Chat Service V2. Ensure it was saved during initial auth.');
            throw new Exception("Could not determine authorized user's email for Google Chat V2. Re-authorize or check token storage.");
        }
    }

    /**
     * Sends a message as a reply within an existing thread in a Google Chat space.
     *
     * @param  string  $spaceName  The resource name of the space (e.g., "spaces/AAAAAAAAAAA").
     * @param  string  $threadName  The resource name of the thread (e.g., "spaces/AAAAKysu4M8/threads/t8d2dmkX-1M").
     * @param  string  $messageText  The plain text content of the reply message.
     * @return array The sent message details.
     *
     * @throws Exception If message sending fails.
     */
    public function sendThreadedMessage(string $spaceName, string $threadName, string $messageText): array
    {
        $message = new Message;
        $message->setText($messageText);

        $thread = new Thread;
        $thread->setName($threadName); // Set the full thread resource name

        $message->setThread($thread); // Associate the message with the thread

        // Explicitly set the message reply option to ensure it tries to reply to the thread.
        // REPLY_MESSAGE_FALLBACK_TO_NEW_THREAD is a good default for robustness.
        $message->setMessageReplyOption(MessageReplyOption::REPLY_MESSAGE_FALLBACK_TO_NEW_THREAD);

        try {
            $sentMessage = $this->chatServiceClient->createMessage($spaceName, $message);

            return [
                'name' => $sentMessage->getName(),
                'text' => $sentMessage->getText(),
                'createTime' => $sentMessage->getCreateTime(),
                'thread' => $sentMessage->getThread() ? $sentMessage->getThread()->toArray() : null,
            ];
        } catch (\Google\ApiCore\ApiException $e) {
            Log::error('Failed to send threaded message to Google Chat (V2): '.$e->getMessage(), [
                'space_name' => $spaceName,
                'thread_name' => $threadName,
                'exception' => $e,
            ]);
            throw new Exception('Failed to send threaded message (V2): '.$e->getMessage());
        } catch (Exception $e) {
            Log::error('An unexpected error occurred during threaded message sending (V2): '.$e->getMessage(), ['space_name' => $spaceName, 'exception' => $e]);
            throw new Exception('An unexpected error occurred during threaded message sending (V2): '.$e->getMessage());
        }
    }
}
