<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoogleUserService;
use Google\Service\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GoogleChatUserController extends Controller
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
        $this->middleware('auth');
        $this->googleUserService = $googleUserService;
    }

    /**
     * Check if the user has Google credentials.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkGoogleCredentials(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'has_credentials' => $user->hasGoogleCredentials(),
        ]);
    }

    /**
     * Create a new Google Chat space.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSpace(Request $request)
    {
        try {
            $request->validate([
                'display_name' => 'required|string|max:255',
                'is_direct_message' => 'boolean',
                'allow_external_users' => 'boolean',
            ]);

            $user = $request->user();

            if (!$user->hasGoogleCredentials()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You need to connect your Google account first.',
                    'redirect_url' => route('user.google.redirect'),
                ], 403);
            }

            $client = $this->googleUserService->getClientForUser($user);
            $chatService = new Chat($client);

            $space = new Chat\Space();
            $space->setDisplayName($request->display_name);
            $space->setSpaceType($request->is_direct_message ? 'DIRECT_MESSAGE' : 'SPACE');
            $space->setExternalUserAllowed($request->allow_external_users ?? true);

            $createdSpace = $chatService->spaces->create($space);

            return response()->json([
                'success' => true,
                'space' => [
                    'name' => $createdSpace->getName(),
                    'display_name' => $createdSpace->getDisplayName(),
                    'space_type' => $createdSpace->getSpaceType(),
                    'create_time' => $createdSpace->getCreateTime(),
                    'space_uri' => $createdSpace->getSpaceUri(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create Google Chat space: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create Google Chat space: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add members to a Google Chat space.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMembers(Request $request)
    {
        try {
            $request->validate([
                'space_name' => 'required|string',
                'member_emails' => 'required|array',
                'member_emails.*' => 'email',
            ]);

            $user = $request->user();

            if (!$user->hasGoogleCredentials()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You need to connect your Google account first.',
                    'redirect_url' => route('user.google.redirect'),
                ], 403);
            }

            $client = $this->googleUserService->getClientForUser($user);
            $chatService = new Chat($client);

            $responseArray = [];
            foreach ($request->member_emails as $email) {
                try {
                    $chatUser = new Chat\User();
                    $chatUser->setName('users/' . $email);
                    $chatUser->setType('HUMAN');

                    $membership = new Chat\Membership();
                    $membership->setMember($chatUser);

                    $response = $chatService->spaces_members->create($request->space_name, $membership);

                    $memberName = $response->getMember()?->getName();
                    $chatName = $memberName;
                    if ($memberName && strpos($memberName, 'users/') === 0) {
                        $chatName = substr($memberName, 6);
                    }

                    $responseArray[] = [
                        'email' => $email,
                        'chat_name' => $chatName,
                    ];
                } catch (\Exception $e) {
                    Log::error('Failed to add member ' . $email . ' to Google Chat space: ' . $e->getMessage(), [
                        'user_id' => $user->id,
                        'space_name' => $request->space_name,
                        'exception' => $e,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'members_added' => $responseArray,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add members to Google Chat space: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add members to Google Chat space: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a message to a Google Chat space.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        try {
            $request->validate([
                'space_name' => 'required|string',
                'message_text' => 'required|string',
                'thread_name' => 'nullable|string',
            ]);

            $user = $request->user();

            if (!$user->hasGoogleCredentials()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You need to connect your Google account first.',
                    'redirect_url' => route('user.google.redirect'),
                ], 403);
            }

            $client = $this->googleUserService->getClientForUser($user);
            $chatService = new Chat($client);

            $message = new Chat\Message();
            $message->setText($request->message_text);

            // If thread_name is provided, send as a threaded message
            if ($request->has('thread_name') && $request->thread_name) {
                $thread = new Chat\Thread();
                $thread->setName($request->thread_name);
                $message->setThread($thread);

                $optParams = [
                    'messageReplyOption' => 'REPLY_MESSAGE_OR_FAIL',
                ];

                $sentMessage = $chatService->spaces_messages->create($request->space_name, $message, $optParams);
            } else {
                $sentMessage = $chatService->spaces_messages->create($request->space_name, $message);
            }

            return response()->json([
                'success' => true,
                'message' => [
                    'name' => $sentMessage->getName(),
                    'text' => $sentMessage->getText(),
                    'sender' => $sentMessage->getSender() ? $sentMessage->getSender()->getName() : null,
                    'create_time' => $sentMessage->getCreateTime(),
                    'thread_name' => $sentMessage->getThread() ? $sentMessage->getThread()->getName() : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send message to Google Chat space: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message to Google Chat space: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a standup message to a Google Chat space.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendStandup(Request $request)
    {
        try {
            $request->validate([
                'space_name' => 'required|string',
                'yesterday' => 'required|string',
                'today' => 'required|string',
                'blockers' => 'nullable|string',
            ]);

            $user = $request->user();

            if (!$user->hasGoogleCredentials()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You need to connect your Google account first.',
                    'redirect_url' => route('user.google.redirect'),
                ], 403);
            }

            // Format the standup message
            $messageText = "*Daily Standup - " . now()->format('F j, Y') . "*\n\n";
            $messageText .= "*Yesterday:*\n" . $request->yesterday . "\n\n";
            $messageText .= "*Today:*\n" . $request->today . "\n\n";

            if ($request->blockers) {
                $messageText .= "*Blockers:*\n" . $request->blockers;
            }

            // Reuse the sendMessage method
            $messageRequest = new Request([
                'space_name' => $request->space_name,
                'message_text' => $messageText,
            ]);

            return $this->sendMessage($messageRequest);
        } catch (\Exception $e) {
            Log::error('Failed to send standup to Google Chat space: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send standup to Google Chat space: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a note to a Google Chat space.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendNote(Request $request)
    {
        try {
            $request->validate([
                'space_name' => 'required|string',
                'note_text' => 'required|string',
                'thread_name' => 'nullable|string',
            ]);

            $user = $request->user();

            if (!$user->hasGoogleCredentials()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You need to connect your Google account first.',
                    'redirect_url' => route('user.google.redirect'),
                ], 403);
            }

            // Format the note message
            $messageText = "*Note - " . now()->format('F j, Y g:i A') . "*\n\n";
            $messageText .= $request->note_text;

            // Reuse the sendMessage method
            $messageRequest = new Request([
                'space_name' => $request->space_name,
                'message_text' => $messageText,
                'thread_name' => $request->thread_name,
            ]);

            return $this->sendMessage($messageRequest);
        } catch (\Exception $e) {
            Log::error('Failed to send note to Google Chat space: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send note to Google Chat space: ' . $e->getMessage(),
            ], 500);
        }
    }
}
