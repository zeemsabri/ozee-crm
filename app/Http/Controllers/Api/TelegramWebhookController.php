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

            // 1. Handle Callback Queries (for Inline Buttons)
            if (isset($payload['callback_query'])) {
                $callback = $payload['callback_query'];
                $data = $callback['data'] ?? '';
                $fromId = $callback['from']['id'] ?? null;

                if ($fromId && str_starts_with($data, 'switch_project_')) {
                    $projectId = (int) str_replace('switch_project_', '', $data);
                    $account = \App\Models\TelegramAccount::where('telegram_id', $fromId)->first();
                    
                    if ($account && $account->telegramable instanceof \App\Models\Client) {
                        $client = $account->telegramable;
                        $project = \App\Models\Project::find($projectId);
                        
                        if ($project && $client->projects->contains($project->id)) {
                            $client->update(['active_telegram_project_id' => $project->id]);
                            
                            // Acknowledge callback
                            $this->answerCallbackQuery($callback['id'], "Active project set to: {$project->name}");
                            
                            // Update the main menu
                            $telegramService = app(\App\Services\TelegramService::class);
                            $telegramService->updateClientPersistentMenu($client, "✅ Your active project is now set to: *{$project->name}*");
                        }
                    }
                }
                return response()->json(['status' => 'ok']);
            }

            $message = $payload['message'] ?? ($payload['edited_message'] ?? null);
            if (!$message) {
                return response()->json(['status' => 'ok']);
            }

            $text = $message['text'] ?? ($message['caption'] ?? '');
            $chatId = $message['chat']['id'] ?? null;
            $from = $message['from'] ?? null;
            $fromId = $from['id'] ?? null;

            if (!$chatId) {
                return response()->json(['status' => 'ok']);
            }

            // Handle linking: /link #CODE
            if ($text && preg_match('/^\/link\s+#([a-zA-Z0-9]+)$/', $text, $matches)) {
                $code = $matches[1];

                // 1. Try Project linking (linking a group to a project)
                $projectToLink = \App\Models\Project::where('telegram_link_code', $code)->first();
                if ($projectToLink) {
                    $chatName = $message['chat']['title'] ?? ($message['chat']['username'] ?? 'Telegram Group');
                    $projectToLink->update([
                        'telegram_group_id' => $chatId,
                        'telegram_group_name' => $chatName,
                        'telegram_link_code' => null,
                    ]);
                    
                    // Clear the project cache for this chat ID
                    \Illuminate\Support\Facades\Cache::forget("telegram_project_by_chat_{$chatId}");

                    $this->sendMessage($chatId, "✅ Project Linked: *{$projectToLink->name}*");
                    $telegramService = app(\App\Services\TelegramService::class);
                    $telegramService->ensureGeneralTopicExists($projectToLink);
                    $telegramService->createDefaultTopics($projectToLink);
                    return response()->json(['status' => 'ok']);
                }

                // 2. Try User/Client linking (linking a person to their account) - usually via private chat
                // A person can link their account to their Telegram ID.
                if ($fromId) {
                    $linkable = \App\Models\User::where('telegram_link_code', $code)->first()
                             ?? \App\Models\Client::where('telegram_link_code', $code)->first();

                    if ($linkable) {
                        \App\Models\TelegramAccount::updateOrCreate(
                            ['telegram_id' => $fromId],
                            [
                                'telegramable_id' => $linkable->id,
                                'telegramable_type' => get_class($linkable),
                                'username' => $from['username'] ?? null,
                                'first_name' => $from['first_name'] ?? null,
                                'last_name' => $from['last_name'] ?? null,
                            ]
                        );

                        // Clear link code
                        $linkable->update(['telegram_link_code' => null]);

                        // Ensure Client Communication topic exists for all projects of this client
                        if ($linkable instanceof \App\Models\Client) {
                            $telegramService = app(\App\Services\TelegramService::class);
                            foreach ($linkable->projects as $project) {
                                if ($project->telegram_group_id) {
                                    $telegramService->ensureClientTopicExists($project);
                                }
                            }
                        }

                        // Clear cache for this telegram id
                        \Illuminate\Support\Facades\Cache::forget("telegram_account_{$fromId}");

                        $this->sendMessage($chatId, "✅ Account verified! Your Telegram is now linked to: *{$linkable->name}*");

                        // Update menu for client
                        if ($linkable instanceof \App\Models\Client) {
                            app(\App\Services\TelegramService::class)->updateClientPersistentMenu($linkable);
                        }

                        return response()->json(['status' => 'ok']);
                    }
                }

                // If code was provided but not found
                $this->sendMessage($chatId, "❌ Invalid or expired link code.");
                return response()->json(['status' => 'ok']);
            }

            // Identify sender (User or Client) via cache/db
            $senderData = null;
            if ($fromId) {
                $senderData = \Illuminate\Support\Facades\Cache::remember("telegram_account_{$fromId}", 3600, function () use ($fromId) {
                    $account = \App\Models\TelegramAccount::where('telegram_id', $fromId)->first();
                    if ($account) {
                        return [
                            'id' => $account->telegramable_id,
                            'type' => $account->telegramable_type,
                        ];
                    }
                    return null;
                });
            }

            // Handle "Switch Active Project" or multi-project menu triggers
            if ($text && str_contains($text, '(Tap to Switch)') && $senderData && $senderData['type'] === \App\Models\Client::class) {
                $client = \App\Models\Client::find($senderData['id']);
                if ($client && $client->projects->count() > 1) {
                    app(\App\Services\TelegramService::class)->sendProjectSelectionMessage($client);
                    return response()->json(['status' => 'ok']);
                }
            }

            // Check if this chat is already linked to a project (for group chats)
            /** @var \App\Models\Project|null $project */
            $project = \Illuminate\Support\Facades\Cache::remember("telegram_project_by_chat_{$chatId}", 3600, function () use ($chatId) {
                return \App\Models\Project::where('telegram_group_id', $chatId)->first();
            });

            // If it's a private chat with a CLIENT, we might want to use their selected active project
            if (!$project && $senderData && $senderData['type'] === \App\Models\Client::class) {
                $client = \App\Models\Client::find($senderData['id']);
                if ($client) {
                    $project = $client->activeTelegramProject ?? $client->projects->first();
                }
            }

            if (!$project) {
                return response()->json(['status' => 'ok']);
            }

            // Handle message recording (Topic context)
            $threadId = $message['message_thread_id'] ?? null;

            // Handle /client or /reply [message] command for staff to reply to clients from any topic
            $isClientCommand = false;
            $clientCommandText = '';
            if ($text && preg_match('/^\/(client|reply)(?:@[A-Za-z0-9_]+)?\s+(.+)$/s', $text, $matches)) {
                $isClientCommand = true;
                $clientCommandText = $matches[2];
            }

            // Identify sender - ALREADY DONE ABOVE
            
            // Optimize: Cache the general topic ID to avoid DB lookups on every message
            $generalTopicId = \Illuminate\Support\Facades\Cache::remember("project_{$project->id}_general_topic_id", 3600, function () use ($project) {
                $telegramService = app(\App\Services\TelegramService::class);
                return $telegramService->ensureGeneralTopicExists($project)->id;
            });

            // Default target is General
            $targetTopicId = $generalTopicId;
            $relayResults = [];
            $telegramService = app(\App\Services\TelegramService::class);
            $clientMessagingStatus = $telegramService->getClientMessagingStatus($project);

            // If we have a thread ID from Telegram, try to find matching topic
            if ($threadId) {
                $targetTopicId = \Illuminate\Support\Facades\Cache::remember("project_{$project->id}_thread_{$threadId}_topic_id", 3600, function () use ($project, $threadId, $generalTopicId) {
                    $topic = \App\Models\TelegramTopic::where('project_id', $project->id)
                        ->where('telegram_thread_id', $threadId)
                        ->first();
                    return $topic ? $topic->id : $generalTopicId;
                });
            }

            // CRITICAL: If the message is from a CLIENT (DM), it MUST go to the Client Communication (Proxy) topic
            // AND the General topic so the internal team is notified everywhere.
            if ($text && $senderData && $senderData['type'] === \App\Models\Client::class && !isset($message['chat']['title'])) {
                $client = \App\Models\Client::find($senderData['id']);
                $prefix = $client ? $client->name : ($from['first_name'] ?? 'Client');
                
                // 1. Send to Proxy Topic
                $proxyTopic = \App\Models\TelegramTopic::where('project_id', $project->id)
                    ->where('type', \App\Enums\TelegramTopicType::PROXY->value)
                    ->first();
                if ($proxyTopic) {
                    $targetTopicId = $proxyTopic->id;
                    $result = $telegramService->sendMessageToTopic($proxyTopic, $text, $prefix);
                    if ($result) {
                        $relayResults['proxy_topic'] = $result;
                    }
                }

                // 2. Send to General Topic
                $generalTopic = \App\Models\TelegramTopic::where('project_id', $project->id)
                    ->where('type', \App\Enums\TelegramTopicType::GENERAL->value)
                    ->first();
                if ($generalTopic) {
                    $result = $telegramService->sendMessageToTopic($generalTopic, $text, "Incoming Client Message: " . $prefix);
                    if ($result) {
                        $relayResults['general_topic'] = $result;
                    }
                }

                // 3. Send to ALL OTHER clients in the project
                foreach ($project->clients as $otherClient) {
                    if ($otherClient->id !== $client->id) {
                        $result = $telegramService->sendDirectMessageToClient($otherClient, $text, $prefix);
                        if ($result) {
                            $relayResults['client_dms'][] = $result;
                        }
                    }
                }
            }

            // Get Proxy Topic for internal team routing
            $proxyTopic = \App\Models\TelegramTopic::where('project_id', $project->id)
                ->where('type', \App\Enums\TelegramTopicType::PROXY->value)
                ->first();

            // Handle Cross-Topic Team Command (/client or /reply)
            // Fire for ANY sender in a group chat - no need to be a linked CRM User.
            // Clients sending DMs won't have chat.title, so this is safely team-only.
            if ($isClientCommand && isset($message['chat']['title'])) {
                if (!$clientMessagingStatus['enabled']) {
                    $result = $this->sendMessage(
                        $chatId,
                        $telegramService->getClientMessagingUnavailableTelegramText($project),
                        $threadId
                    );

                    if ($result) {
                        $relayResults['client_messaging_unavailable_notice'] = $result;
                    }
                } else {
                // Resolve best sender name
                $prefix = 'Team';
                if ($senderData && $senderData['type'] === \App\Models\User::class) {
                    $user = \App\Models\User::find($senderData['id']);
                    $prefix = $user ? $user->name : ($from['first_name'] ?? 'Team');
                } else {
                    $prefix = $from['first_name'] ?? ($from['username'] ?? 'Team');
                }

                $text = $clientCommandText; // Override text to be saved in DB

                if ($proxyTopic) {
                    $targetTopicId = $proxyTopic->id;

                    // Post a copy in Proxy Topic with attribution
                    $proxyMessage = "*(Sent via /client by {$prefix})*: \n{$text}";
                    $result = $telegramService->sendMessageToTopic($proxyTopic, $proxyMessage);
                    if ($result) {
                        $relayResults['proxy_topic'] = $result;
                    }
                }

                // Relay to all linked clients of this project via DM
                foreach ($project->clients as $clientModel) {
                    $result = $telegramService->sendDirectMessageToClient($clientModel, $text, $prefix);
                    if ($result) {
                        $relayResults['client_dms'][] = $result;
                    }
                }

                // Confirm back to the sender in the same thread/topic
                $result = $this->sendMessage($chatId, "✅ Message sent to client(s).", $threadId);
                if ($result) {
                    $relayResults['confirmation_message'] = $result;
                }
                }
            }
            // DIRECTION 2: Internal Team -> Client DM (from Proxy topic)
            // If the message is from within a Group Topic and the topic is a PROXY topic.
            else if ($text && $threadId && isset($message['chat']['title'])) {
                $currentTopic = \App\Models\TelegramTopic::where('project_id', $project->id)
                    ->where('telegram_thread_id', $threadId)
                    ->first();

                if ($currentTopic && $currentTopic->type === \App\Enums\TelegramTopicType::PROXY) {
                    $targetTopicId = $currentTopic->id;

                    if (!$clientMessagingStatus['enabled']) {
                        $result = $this->sendMessage(
                            $chatId,
                            $telegramService->getClientMessagingUnavailableTelegramText($project),
                            $threadId
                        );

                        if ($result) {
                            $relayResults['client_messaging_unavailable_notice'] = $result;
                        }
                    } else {
                        // Identify internal sender
                        $prefix = 'Team';
                        if ($senderData && $senderData['type'] === \App\Models\User::class) {
                            $user = \App\Models\User::find($senderData['id']);
                            $prefix = $user ? $user->name : ($from['first_name'] ?? 'Team');
                        } else {
                            $prefix = $from['first_name'] ?? ($from['username'] ?? 'Team');
                        }

                        // Relay to all linked clients of this project
                        foreach ($project->clients as $clientModel) {
                            $result = $telegramService->sendDirectMessageToClient($clientModel, $text, $prefix);
                            if ($result) {
                                $relayResults['client_dms'][] = $result;
                            }
                        }
                    }
                }
            }

            if ($text) {
                $msgData = [
                    'project_id' => $project->id,
                    'telegram_topic_id' => $targetTopicId,
                    'telegram_message_id' => $message['message_id'],
                    'message' => $text,
                    'source' => 'telegram',
                    'type' => 'text',
                    'meta_data' => [
                        'telegram_from' => $from,
                        'telegram_relays' => $relayResults, // Keep legacy for now
                    ]
                ];

                if ($senderData) {
                    if ($senderData['type'] === \App\Models\User::class) {
                        $msgData['user_id'] = $senderData['id'];
                    } elseif ($senderData['type'] === \App\Models\Client::class) {
                        $msgData['client_id'] = $senderData['id'];
                    }
                }

                /** @var \App\Models\ChatMessage $chatMsg */
                $chatMsg = \App\Models\ChatMessage::create($msgData);

                // Standardized Tracking for deletion
                $chatMsg->addTelegramResponse([
                    'message_id' => $message['message_id'],
                    'chat' => ['id' => $chatId]
                ], 'source');

                foreach ($relayResults as $key => $val) {
                    if ($key === 'client_dms' && is_array($val)) {
                        foreach ($val as $res) {
                            $chatMsg->addTelegramResponse($res, 'client_relay');
                        }
                    } else if (is_array($val)) {
                        $chatMsg->addTelegramResponse($val, $key);
                    }
                }
                $chatMsg->save();

                // Broadcast
                if (class_exists(\App\Events\ChatMessageSent::class)) {
                     \App\Events\ChatMessageSent::dispatch($chatMsg->load(['user', 'client', 'parent.user']));
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error("Failed to process Telegram webhook: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['status' => 'error'], 500);
        }
    }

    private function sendMessage($chatId, $text, $threadId = null)
    {
        $token = config('services.telegram.bot_token');
        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'message_thread_id' => $threadId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        return $response->successful() ? $response->json('result') : null;
    }

    private function answerCallbackQuery($callbackQueryId, $text = null)
    {
        $token = config('services.telegram.bot_token');
        return Http::post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
        ]);
    }

    private function sendMenu($chatId, $project)
    {
        $token = config('services.telegram.bot_token');

        $telegramAccount = \App\Models\TelegramAccount::where('telegram_id', $chatId)->first();
        if ($telegramAccount && $telegramAccount->telegramable instanceof \App\Models\Client) {
             return app(\App\Services\TelegramService::class)->updateClientPersistentMenu($telegramAccount->telegramable);
        }

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
