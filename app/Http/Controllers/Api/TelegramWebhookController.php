<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle the incoming Telegram webhook.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();

            // Store the JSON payload for debugging
            $filename = 'telegram/webhooks/' . now()->format('Y-m-d_H-i-s') . '_' . uniqid() . '.json';
            Storage::disk('local')->put($filename, json_encode($payload, JSON_PRETTY_PRINT));
            Log::info("Telegram webhook captured and saved to {$filename}");

            $message = $payload['message'] ?? ($payload['edited_message'] ?? null);
            if (!$message) {
                return response()->json(['status' => 'ok']);
            }

            $text = $message['text'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$chatId) {
                return response()->json(['status' => 'ok']);
            }

            // Check if this chat is already linked to a project
            $project = \App\Models\Project::where('telegram_group_id', $chatId)->first();

            if (!$project) {
                // Not linked. Check for link command: /link #CODE
                if ($text && preg_match('/^\/link\s+#([a-zA-Z0-9]+)$/', $text, $matches)) {
                    $code = $matches[1];
                    $projectToLink = \App\Models\Project::where('telegram_link_code', $code)->first();

                    if ($projectToLink) {
                        $chatName = $message['chat']['title'] ?? ($message['chat']['username'] ?? 'Telegram Group');
                        $projectToLink->update([
                            'telegram_group_id' => $chatId,
                            'telegram_group_name' => $chatName,
                            'telegram_link_code' => null, // Clear code after use
                        ]);

                        $this->sendMessage($chatId, "✅ Success! This group (*{$chatName}*) is now linked to project: *{$projectToLink->name}*");

                        $telegramService = app(\App\Services\TelegramService::class);
                        // Ensure General topic exists locally
                        $telegramService->ensureGeneralTopicExists($projectToLink);
                        // Create default topics in Telegram
                        $telegramService->createDefaultTopics($projectToLink);

                        return response()->json(['status' => 'ok']);
                    } else {
                        $this->sendMessage($chatId, "❌ Invalid link code. Please generate a new code from the project settings.");
                        return response()->json(['status' => 'ok']);
                    }
                }

                return response()->json(['status' => 'ok']);
            }

            // If already linked, handle commands
            if ($text === '/start') {
                $this->sendMenu($chatId, $project);
                return response()->json(['status' => 'ok']);
            }

            // Handle message recording
            $threadId = $message['message_thread_id'] ?? null;
            $telegramService = app(\App\Services\TelegramService::class);
            $generalTopic = $telegramService->ensureGeneralTopicExists($project);

            $targetTopicId = $generalTopic->id;

            if ($threadId) {
                $topic = \App\Models\TelegramTopic::where('project_id', $project->id)
                    ->where('telegram_thread_id', $threadId)
                    ->first();
                if ($topic) {
                    $targetTopicId = $topic->id;
                }
            }

            if ($text) {
                $chatMsg = \App\Models\ChatMessage::create([
                    'project_id' => $project->id,
                    'telegram_topic_id' => $targetTopicId,
                    'telegram_message_id' => $message['message_id'],
                    'message' => $text,
                    'source' => 'telegram',
                    'type' => 'text',
                    'meta_data' => [
                        'telegram_from' => $message['from'] ?? null,
                    ]
                ]);

                // Broadcast
//                \App\Events\ChatMessageSent::dispatch($chatMsg->load(['user', 'parent.user']));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to process Telegram webhook: " . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    private function sendMessage($chatId, $text)
    {
        $token = config('services.telegram.bot_token');
        return Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    private function sendMenu($chatId, $project)
    {
        $token = config('services.telegram.bot_token');
        return Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => "Welcome! This group is linked to *{$project->name}*.\nUse the menu below to navigate.",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'keyboard' => [
                    [
                        ['text' => '📁 View Project'],
                        ['text' => '❓ Help']
                    ],
                ],
                'resize_keyboard' => true,
                'is_persistent' => true,
            ])
        ]);
    }

    public function send()
    {
        $token = config('services.telegram.bot_token');

        // To send to the Group:
        $chatId = '-1003821883588';

        // To send to you privately, swap it to your User ID:
        // $chatId = '5087832895';

        $response = Http::post("https://api.telegram.org/bot{$token}/sendPhoto", [
            'chat_id' => $chatId,
            // You can pass any public URL to an image here
            'photo' => 'https://images.unsplash.com/photo-1618761714954-0b8cd0026356?auto=format&fit=crop&w=800&q=80',
            'caption' => "🎨 *New Wireframes Ready*\n\nHey team, the new dashboard layouts are ready for review.",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        // Button 1: Opens a web page (Deep link to your CRM)
                        ['text' => '💻 Open in CRM', 'url' => 'https://your-local-crm.test/projects/123'],

                        // Button 2: Sends a silent background request back to your webhook
                        ['text' => '✅ Approve', 'callback_data' => 'action_approve_task_123']
                    ]
                ]
            ])
        ]);

        return $response->json();
    }

    public function sendThreadMessage()
    {
        $token = config('services.telegram.bot_token');
        $chatId = '5087832895'; // The Supergroup ID
        $threadId = 8; // The ID of the specific Topic/Thread you just created
        $userIdToMention = '5087832895';

        $response = Http::post("https://api.telegram.org/bot{$token}/sendPhoto", [
            'chat_id' => $chatId,
            'message_thread_id' => $threadId, // <-- THIS routes it into the topic!

            // You can pass any public URL to an image here
            'photo' => 'https://images.unsplash.com/photo-1618761714954-0b8cd0026356?auto=format&fit=crop&w=800&q=80',

            // Tagging the user using Markdown link syntax: [Display Name](tg://user?id=THE_ID)
            'caption' => "🎨 *New Wireframes Ready*\n\nHey [Zeeshan](tg://user?id={$userIdToMention}), the new dashboard layouts are ready for review.",
            'parse_mode' => 'Markdown',

            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        // Button 1: Opens a web page (Deep link to your CRM)
                        ['text' => '💻 Open in CRM', 'url' => 'https://your-local-crm.test/projects/123'],

                        // Button 2: Sends a silent background request back to your webhook
                        ['text' => '✅ Approve', 'callback_data' => 'action_approve_task_123']
                    ]
                ]
            ])
        ]);

        return $response->json();
    }

    public function createTopic()
    {
        $token = config('services.telegram.bot_token');
        $chatId = '-1003821883588'; // Your Group ID (Must have Topics enabled)

        // 1. Create the Topic (Thread)
        $topicResponse = Http::post("https://api.telegram.org/bot{$token}/createForumTopic", [
            'chat_id' => $chatId,
            'name' => 'Project: Website Redesign', // Name of the thread
            // 'icon_color' => 7322096, // Optional: RGB color hex
        ]);

        $topicData = $topicResponse->json();


        // If successful, extract the Thread ID
        if (isset($topicData['result']['message_thread_id'])) {
            $threadId = $topicData['result']['message_thread_id'];

            // 2. Send a message specifically into this new Topic
            $messageResponse = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'message_thread_id' => $threadId, // THIS is the magic parameter
                'text' => "🚀 Thread created successfully! All updates for this project will go here.",
            ]);

            return [
                'topic_creation' => $topicData,
                'message_delivery' => $messageResponse->json()
            ];
        }

        return $topicData; // Return error if creation failed
    }
}
