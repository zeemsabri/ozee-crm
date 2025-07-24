<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\HangoutsChat; // This is the Google Chat Service class
use Google\Service\HangoutsChat\Space;
use Google\Service\HangoutsChat\Message;
use Google\Service\HangoutsChat\Membership;
use Google\Service\HangoutsChat\Thread;
use Google\Service\HangoutsChat\User; // Ensure this is imported
use Google\Service\HangoutsChat\SetUpSpaceRequest; // Ensure this is imported
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleChatService
{
    protected GoogleClient $client;
    protected string $userEmail; // The email of the Google Workspace account being used by the app

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI')); // Required for client initialization
        // Scopes are added during the OAuth flow in GoogleAuthController, but the client needs to know them for validation
        $this->client->addScope('https://www.googleapis.com/auth/chat.spaces');
        $this->client->addScope('https://www.googleapis.com/auth/chat.messages');

        $this->loadAndSetAccessToken();
    }

    /**
     * Loads tokens from storage and sets them on the Google Client.
     * Refreshes the access token if it's expired using the refresh token.
     *
     * @throws Exception If tokens are not found or cannot be refreshed.
     */
    protected function loadAndSetAccessToken(): void
    {
        if (!Storage::disk('local')->exists('google_tokens.json')) {
            throw new Exception('Google tokens not found. Please run the OAuth authorization flow first via /google/redirect.');
        }

        $tokens = json_decode(Storage::disk('local')->get('google_tokens.json'), true);

        if (empty($tokens['access_token'])) {
            throw new Exception('Access token not found in google_tokens.json. Re-authorize.');
        }

        // Set the current access token
        $this->client->setAccessToken($tokens['access_token']);

        // Check if the access token is expired
        if ($this->client->isAccessTokenExpired()) {
            Log::info('Google access token expired for Chat Service, attempting to refresh.');
            if (empty($tokens['refresh_token'])) {
                throw new Exception('Refresh token not found. User needs to re-authorize for Google Chat.');
            }

            try {
                // Use the refresh token to get a new access token
                $this->client->fetchAccessTokenWithRefreshToken($tokens['refresh_token']);
                $newTokens = $this->client->getAccessToken();

                // Update stored tokens with the new access token and its expiry
                $tokens['access_token'] = $newTokens['access_token'];
                $tokens['expires_in'] = $newTokens['expires_in'];
                $tokens['created_at'] = now()->timestamp; // Update creation time

                // If Google ever issues a new refresh token (rare, but good to check)
                if (isset($newTokens['refresh_token'])) {
                    $tokens['refresh_token'] = $newTokens['refresh_token'];
                    Log::info('Google refresh token updated for Chat Service.');
                }

                Storage::disk('local')->put('google_tokens.json', json_encode($tokens, JSON_PRETTY_PRINT));
                Log::info('Google access token refreshed successfully for Chat Service.');

            } catch (Exception $e) {
                Log::error('Failed to refresh Google access token for Chat Service: ' . $e->getMessage(), ['exception' => $e]);
                throw new Exception('Failed to refresh Google access token for Google Chat. Please re-authorize.');
            }
        }

        $this->userEmail = $tokens['email'] ?? '';
        if (empty($this->userEmail)) {
            Log::warning('Could not determine authorized user\'s email for Chat Service. Ensure it was saved during initial auth.');
            throw new Exception("Could not determine authorized user's email for Google Chat. Re-authorize or check token storage.");
        }
    }

    /**
     * Creates a new Google Chat space.
     *
     * @param string $displayName The name of the space.
     * @param bool $isDirectMessage If true, creates a direct message space (requires only one other member).
     * @param array $memberEmails An array of email addresses for initial members (excluding the authorizing user).
     * @return array The created space details.
     * @throws Exception If space creation fails.
     */
    public function createSpace(string $displayName, bool $isDirectMessage = false, $allowExternalUsers = true): array
    {
        $service = new HangoutsChat($this->client);

        $space = new Space();
        $space->setDisplayName($displayName);
        // For spaces.create, 'DIRECT_MESSAGE' type is generally used with spaces.setup
        // For a simple create, 'SPACE' is typical.
        $space->setSpaceType($isDirectMessage ? 'DIRECT_MESSAGE' : 'SPACE');
        $space->setExternalUserAllowed($allowExternalUsers);

        try {
            // Use spaces->create for an empty space
            $createdSpace = $service->spaces->create($space);

            Log::info('Google Chat space created successfully (empty)', ['space_name' => $createdSpace->getName(), 'display_name' => $createdSpace->getDisplayName()]);
            return [
                'name' => $createdSpace->getName(),
                'displayName' => $createdSpace->getDisplayName(),
                'spaceType' => $createdSpace->getSpaceType(),
                'createTime' => $createdSpace->getCreateTime(),
                'spaceUri' => $createdSpace->getSpaceUri(),
            ];

        } catch (Exception $e) {
            Log::error('Failed to create Google Chat space: ' . $e->getMessage(), ['display_name' => $displayName, 'exception' => $e]);
            throw new Exception('Failed to create Google Chat space: ' . $e->getMessage());
        }
    }

    /**
     * Adds members to an existing Google Chat space.
     *
     * @param string $spaceName The resource name of the space (e.g., "spaces/AAAAAAAAAAA").
     * @param array $memberEmails An array of email addresses for members to add.
     * @throws Exception If adding a member fails.
     */
    public function addMembersToSpace(string $spaceName, array $memberEmails): array
    {

        $responseArray = [];
        $service = new HangoutsChat($this->client);
        foreach ($memberEmails as $email) {
            try {
                $user = new User();
                $user->setName('users/' . $email); // Format: users/{email}
                $user->setType('HUMAN');

                $membership = new Membership();
                $membership->setMember($user);

                // Add the member to the space
                $response = $service->spaces_members->create($spaceName, $membership);
                Log::info('Added member to Google Chat space', ['space_name' => $spaceName, 'member_email' => $email, 'response' => json_encode($response)]);;
                // Get the member name and extract just the ID part if it starts with 'users/'
                $memberName = $response->getMember()?->getName();
                $chatName = $memberName;
                if ($memberName && strpos($memberName, 'users/') === 0) {
                    $chatName = substr($memberName, 6); // Remove 'users/' prefix
                }

                $responseArray[] = [
                    'email'  =>  $email,
                    'chat_name'  =>  $chatName
                ];

            } catch (Exception $e) {
                Log::error('Failed to add member ' . $email . ' to Google Chat space ' . $spaceName . ': ' . $e->getMessage(), ['exception' => $e]);
                // Log the error but continue to try adding other members
            }
        }

        return $responseArray;

    }

    /**
     * Removes members from an existing Google Chat space.
     *
     * @param string $spaceName The resource name of the space (e.g., "spaces/AAAAAAAAAAA").
     * @param array $users An array of user objects with email and chat_name properties
     * @throws Exception If removing a member fails.
     */
    public function removeMembersFromSpace(string $spaceName, array $users): void
    {
        $service = new HangoutsChat($this->client);

        foreach ($users as $user) {
            try {

                // First try to find the member using chat_name if available
                if ($user && ISSET($user['chat_name'])) {
                    $memberToRemove = $spaceName . '/members/' . $user['chat_name'];
                    $service->spaces_members->delete($memberToRemove);;
                }


            } catch (Exception $e) {
                Log::error('Failed to remove some member(s) from Google Chat space ' . $spaceName . ': ' . $e->getMessage(), ['exception' => $e]);
                // Log the error but continue to try removing other members
            }
        }
    }

    /**
     * Sends a message to a Google Chat space.
     *
     * @param string $spaceName The resource name of the space (e.g., "spaces/AAAAAAAAAAA").
     * @param string $messageText The plain text content of the message.
     * @param array $cards Optional: An array of card objects for rich messages.
     * @return array The sent message details.
     * @throws Exception If message sending fails.
     */
    public function sendMessage(string $spaceName, string $messageText, array $cards = []): array
    {
        $service = new HangoutsChat($this->client);
        $message = new Message();
        $message->setText($messageText);

        if (!empty($cards)) {
            Log::warning('Card messages are not fully implemented. Only text will be sent.');
        }

        try {
            $sentMessage = $service->spaces_messages->create($spaceName, $message);
            Log::info('Message sent to Google Chat space', ['space_name' => $spaceName, 'message_id' => $sentMessage->getName()]);
            // Convert Message object to array manually for consistency
            return [
                'name' => $sentMessage->getName(),
                'text' => $sentMessage->getText(),
                'sender' => $sentMessage->getSender(),
                'createTime' => $sentMessage->getCreateTime()
            ];
        } catch (Exception $e) {
            Log::error('Failed to send message to Google Chat: ' . $e->getMessage(), ['space_name' => $spaceName, 'exception' => $e]);
            throw new Exception('Failed to send message to Google Chat: ' . $e->getMessage());
        }
    }

    /**
     * Sends a welcome message to a newly created Google Chat space.
     *
     * @param string $spaceName The resource name of the space.
     * @param string $projectName The name of the project.
     * @return array The sent message details.
     * @throws Exception If message sending fails.
     */
    public function sendWelcomeMessage(string $spaceName, string $projectName): array
    {
        $messageText = "👋 Welcome to the new project space for *{$projectName}*! Let's collaborate here.";
        Log::info('Sending initial welcome message to new space.', ['space_name' => $spaceName, 'project_name' => $projectName]);
        return $this->sendMessage($spaceName, $messageText);
    }

    /**
     * Pins a project document in a Google Chat space by sending a highlighted message.
     *
     * @param string $spaceName The resource name of the space.
     * @param string $resourceLink The URL of the project document to "pin".
     * @param string $projectName The name of the project for context in the message.
     * @return array The sent message details.
     * @throws Exception If message sending fails.
     */
    public function pinProjectDocument(string $spaceName, string $resourceLink, string $projectName): array
    {
        $messageText = "📌 *Project Document: {$projectName} Overview*: {$resourceLink}";
        Log::info('Simulating pinning project document by sending a highlighted message.', ['space_name' => $spaceName, 'resource_link' => $resourceLink]);
        return $this->sendMessage($spaceName, $messageText);
    }

    /**
     * Sends a message as a reply within an existing thread in a Google Chat space.
     *
     * @param string $spaceName The resource name of the space (e.g., "spaces/AAAAAAAAAAA").
     * @param string $threadName The resource name of the thread (e.g., "spaces/AAAAKysu4M8/threads/t8d2dmkX-1M").
     * @param string $messageText The plain text content of the reply message.
     * @return array The sent message details.
     * @throws Exception If message sending fails.
     */
    public function sendThreadedMessage(string $spaceName, string $threadName, string $messageText): array
    {
        $service = new HangoutsChat($this->client);
        $message = new Message();
        $message->setText($messageText);
        $optParams = array(
            'messageReplyOption' => 'REPLY_MESSAGE_OR_FAIL'
        );

        $thread = new Thread();
        $thread->setName($threadName);
        $message->setThread($thread);

        try {
            $sentMessage = $service->spaces_messages->create($spaceName, $message, $optParams);
            return [
                'space_name' => $spaceName,
                'thread_name' => $threadName,
                'name' => $sentMessage->getName()
            ];

        } catch (Exception $e) {
            Log::error('Failed to send threaded message to Google Chat: ' . $e->getMessage(), [
                'space_name' => $spaceName,
                'thread_name' => $threadName,
                'exception' => $e
            ]);
            throw new Exception('Failed to send threaded message: ' . $e->getMessage());
        }
    }




}
