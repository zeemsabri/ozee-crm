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

            // Store the JSON payload
            Storage::disk('local')->put($filename, json_encode($payload, JSON_PRETTY_PRINT));

            Log::info("Telegram webhook captured and saved to {$filename}");

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
        $token = '8590115985:AAHF9yqNdyG42TOihJtzUDbIEjrlBhoTOKU'; // Your bot token

        // To send to the Group:
        $chatId = '-5213473326';

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
}
