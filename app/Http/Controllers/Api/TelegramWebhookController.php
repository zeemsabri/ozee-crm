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
            // Get the payload
            $payload = $request->all();

            // Create a filename based on timestamp
            $filename = 'telegram/webhooks/' . now()->format('Y-m-d_H-i-s') . '_' . uniqid() . '.json';

            // Store the JSON payload for debugging
            Storage::disk('local')->put($filename, json_encode($payload, JSON_PRETTY_PRINT));
            Log::info("Telegram webhook captured and saved to {$filename}");

            // Safely extract the text and chat ID from the payload
            // The ?? null ensures it doesn't crash if the message is an image/file instead of text
            $text = $payload['message']['text'] ?? null;
            $telegramId = $payload['message']['chat']['id'] ?? null;

            // Check if we received text and it matches the /start command
            if ($text === '/start' && $telegramId) {

                $token = config('services.telegram.bot_token'); // Ensure your token is in config/services.php

                Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $telegramId,
                    'text' => "Welcome to Acme CRM! Use the menu below to navigate.",
                    'reply_markup' => json_encode([
                        'keyboard' => [
                            // Row 1
                            [
                                ['text' => '📁 Chaya Villa'],
                                ['text' => '📁 SL Conveyancing']
                            ],
                            // Row 2
                            [
                                ['text' => '❓ Help'],
                                ['text' => '👤 Request Call']
                            ]
                        ],
                        'resize_keyboard' => true, // Makes the buttons smaller and neater
                        'is_persistent' => true,   // Keeps the menu always visible!
                    ])
                ]);

                return response()->json(['status' => 'ok']);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook captured successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to capture Telegram webhook: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to capture webhook',
            ], 500);
        }
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
