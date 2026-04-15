<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Email;
use App\Models\Project;
use App\Models\UserInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $limit  = $request->input('limit', 20);
        $before = $request->input('before');
        $userId = Auth::id();

        // Fetch Chat Messages with read receipts
        $topicId = $request->input('topic_id');
        $isGeneralTopic = false;

        if ($topicId) {
            $topic = \App\Models\TelegramTopic::find($topicId);
            $isGeneralTopic = ($topic && $topic->type === \App\Enums\TelegramTopicType::GENERAL);
        } else {
            // If no topic_id provided, assume we want to see general or all?
            // The sidebar usually always has a topic selected now.
            $isGeneralTopic = true;
        }

        $messagesQuery = ChatMessage::where('project_id', $project->id)
            ->when($topicId, function($q) use ($topicId, $isGeneralTopic) {
                if ($isGeneralTopic) {
                    $q->where(function($sub) use ($topicId) {
                        $sub->where('telegram_topic_id', $topicId)
                           ->orWhereNull('telegram_topic_id');
                    });
                } else {
                    $q->where('telegram_topic_id', $topicId);
                }
            }, function($q) {
                // If no topic_id, we can choose to show all or just general.
                // Let's show all for now if explicitly no topic requested.
            })
            ->with([
                'user',
                'client',
                'parent.user',
                'parent.client',
                'interactions' => function ($q) {
                    $q->with('user:id,name')->where('interaction_type', 'read');
                },
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($before) {
            $messagesQuery->where('created_at', '<', $before);
        }

        $messages = $messagesQuery->get();

        // Fetch Emails related to project (via conversation) - Only for General or when no topic is selected
        $emails = collect();
        if ($isGeneralTopic) {
            $emailsQuery = Email::whereHas('conversation', function ($q) use ($project) {
                $q->where('project_id', $project->id);
            })
                ->with(['contexts' => function ($q) {
                    $q->select('id', 'summary', 'referencable_id', 'referencable_type');
                }])
                ->orderBy('created_at', 'desc')
                ->limit($limit);

            if ($before) {
                $emailsQuery->where('created_at', '<', $before);
            }

            $emails = $emailsQuery->get()->map(function ($email) {
                return [
                    'id'         => 'email_' . $email->id,
                    'type'       => 'email',
                    'user'       => $email->sender_name ?? 'Client',
                    'initials'   => 'AC',
                    'color'      => 'bg-slate-700',
                    'summary'    => $email->contexts->first()?->summary ?? Str::limit($email->body, 100),
                    'direction'  => $email->type === 'received' ? 'inbound' : 'outbound',
                    'time'       => $email->created_at->diffForHumans(),
                    'created_at' => $email->created_at->toDateTimeString(),
                ];
            });
        }

        $formattedMessages = $messages->map(function ($msg) use ($userId) {
            $reads = $msg->interactions->map(fn ($i) => [
                'user'    => $i->user?->name ?? 'Someone',
                'read_at' => $i->updated_at->toDateTimeString(),
            ]);

            $userName = $msg->user?->name ?? ($msg->client?->name ?? ($msg->meta_data['telegram_from']['first_name'] ?? 'Telegram User'));

            return [
                'id'         => $msg->id,
                'type'       => $msg->source === 'telegram' ? 'telegram' : 'text',
                'user'       => $userName,
                'user_id'    => $msg->user_id,
                'client_id'  => $msg->client_id,
                'initials'   => strtoupper(substr($userName, 0, 2)),
                'color'      => $msg->client_id ? 'bg-sky-500' : ($msg->source === 'telegram' ? 'bg-sky-500' : 'bg-indigo-600'),
                'message'    => $msg->message,
                'parent'     => $msg->parent ? [
                    'id'      => $msg->parent->id,
                    'user'    => $msg->parent->user?->name ?? ($msg->parent->client?->name ?? 'System'),
                    'message' => Str::limit($msg->parent->message, 50),
                ] : null,
                'reads'      => $reads,
                'time'       => $msg->created_at->diffForHumans(),
                'created_at' => $msg->created_at->toDateTimeString(),
                'is_me'      => $msg->user_id === $userId,
                'source'     => $msg->source,
            ];
        });

        // Also auto-mark messages as read when fetched
        $this->markProjectRead($project, $userId);

        // Combine and sort chronologically
        $combined = $formattedMessages->concat($emails)->sortBy('created_at')->values();

        return response()->json($combined);
    }

    /**
     * Optimized Chat Pagination for Native App
     */
    public function indexNative(Request $request, Project $project)
    {
        $userId = Auth::id();
        $topicId = $request->input('topic_id');
        $perPage = $request->input('per_page', 50);
//        Log::info($request->all());

        $query = ChatMessage::where('project_id', $project->id)
            ->when($topicId, function($q) use ($topicId) {
                $topic = \App\Models\TelegramTopic::find($topicId);
                $isGeneralTopic = ($topic && $topic->type === \App\Enums\TelegramTopicType::GENERAL);

                if ($isGeneralTopic) {
                    $q->where(function($sub) use ($topicId) {
                        $sub->where('telegram_topic_id', $topicId)
                           ->orWhereNull('telegram_topic_id');
                    });
                } else {
                    $q->where('telegram_topic_id', $topicId);
                }
            })
            ->with([
                'user',
                'client',
                'parent.user',
                'parent.client',
                'interactions' => function ($q) {
                    $q->with('user:id,name')->where('interaction_type', 'read');
                },
            ]);

        // Cursor logic based on since_id and before_id
        if ($request->has('since_id')) {
            $query->where('id', '>', $request->since_id)->orderBy('id', 'asc');
        } elseif ($request->has('before_id')) {
            $query->where('id', '<', $request->before_id)->orderBy('id', 'desc');
        } else {
            $query->orderBy('id', 'desc');
        }

        $paginator = $query->paginate($perPage);

        $formattedMessages = collect($paginator->items())->map(function ($msg) use ($userId) {
            $reads = $msg->interactions->map(fn ($i) => [
                'user'    => $i->user?->name ?? 'Someone',
                'read_at' => $i->updated_at->toDateTimeString(),
            ]);

            $userName = $msg->user?->name ?? ($msg->client?->name ?? ($msg->meta_data['telegram_from']['first_name'] ?? 'Telegram User'));

            return [
                'id'         => $msg->id,
                'type'       => $msg->source === 'telegram' ? 'telegram' : 'text',
                'user'       => $userName,
                'user_id'    => $msg->user_id,
                'client_id'  => $msg->client_id,
                'initials'   => strtoupper(substr($userName, 0, 2)),
                'color'      => $msg->client_id ? 'bg-sky-500' : ($msg->source === 'telegram' ? 'bg-sky-500' : 'bg-indigo-600'),
                'message'    => $msg->message,
                'parent'     => $msg->parent ? [
                    'id'      => $msg->parent->id,
                    'user'    => $msg->parent->user?->name ?? ($msg->parent->client?->name ?? 'System'),
                    'message' => Str::limit($msg->parent->message, 50),
                ] : null,
                'reads'      => $reads,
                'time'       => $msg->created_at->diffForHumans(),
                'created_at' => $msg->created_at->toDateTimeString(),
                'is_me'      => $msg->user_id === $userId,
                'source'     => $msg->source,
            ];
        });

        // For "since_id" (new messages), we want them in ASC order for the app to append.
        // For "before_id" (older history) or "latest", we keep DESC so the app knows these are previous.
        // Wait, if I use orderBy('id', 'asc') for since_id, the returned collection is ASC.
        // If I use orderBy('id', 'desc') for before_id, the returned collection is DESC.
        // This is correct as per instructions.

        $response =  response()->json([
            'data' => $formattedMessages,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'has_more'     => $paginator->hasMorePages(),
            ]
        ]);

//        Log::info($response);

        return $response;
    }

    /**
     * Mark all messages in a project as read by the current user.
     */
    public function markRead(Request $request, Project $project)
    {
        $userId = Auth::id();
        $this->markProjectRead($project, $userId);
        return response()->json(['ok' => true]);
    }

    /**
     * Get unread chat message counts per project for the authenticated user.
     */
    public function unreadCounts()
    {
        $userId = Auth::id();

        // All project IDs the user has chat access to (project_user pivot + super admins)
        $user = Auth::user();
        if ($user->hasPermission('view_all_projects')) {
            $projectIds = Project::pluck('id');
        } else {
            $projectIds = $user->projects()->pluck('projects.id');
        }

        // Count messages per project where the current user has NOT interacted (read)
        $unreadMap = DB::table('chat_messages')
            ->select('project_id', DB::raw('COUNT(*) as unread'))
            ->whereIn('project_id', $projectIds)
            ->where('user_id', '!=', $userId) // Exclude user's own messages
            ->whereNotExists(function ($q) use ($userId) {
                $q->from('user_interactions')
                    ->whereColumn('user_interactions.interactable_id', 'chat_messages.id')
                    ->where('user_interactions.interactable_type', ChatMessage::class)
                    ->where('user_interactions.user_id', $userId)
                    ->where('user_interactions.interaction_type', 'read');
            })
            ->groupBy('project_id')
            ->pluck('unread', 'project_id');

        return response()->json($unreadMap);
    }

    public function store(Request $request, Project $project)
    {
        $request->validate([
            'message' => 'required|string',
            'parent_id' => 'nullable|integer|exists:chat_messages,id',
            'telegram_topic_id' => 'nullable|integer|exists:telegram_topics,id',
        ]);

        // 1. Detect if this is a Cross-Topic `/client` command from the CRM
        $originalMessage = $request->message;
        $isClientCommand = false;
        $clientCommandText = '';
        $targetTopicId = $request->telegram_topic_id;
        $proxyTopic = null;
        $selectedTopic = $request->telegram_topic_id
            ? \App\Models\TelegramTopic::find($request->telegram_topic_id)
            : null;

        if (preg_match('/^\/(client|reply)(?:@[A-Za-z0-9_]+)?\s+(.+)$/s', $originalMessage, $matches)) {
            $isClientCommand = true;
            $clientCommandText = $matches[2];

            $proxyTopic = \App\Models\TelegramTopic::where('project_id', $project->id)
                ->where('type', \App\Enums\TelegramTopicType::PROXY->value)
                ->first();

            if ($proxyTopic) {
                $targetTopicId = $proxyTopic->id;
            }
        }

        $requiresLinkedTelegramClients = $isClientCommand
            || ($selectedTopic && $selectedTopic->type === \App\Enums\TelegramTopicType::PROXY);

        if ($requiresLinkedTelegramClients) {
            $telegramService = app(\App\Services\TelegramService::class);
            $clientMessagingStatus = $telegramService->getClientMessagingStatus($project);

            if (!$clientMessagingStatus['enabled']) {
                return response()->json([
                    'message' => $clientMessagingStatus['message'],
                    'client_messaging' => $clientMessagingStatus,
                ], 422);
            }
        }

        $messageToSave = $isClientCommand ? $clientCommandText : $originalMessage;

        $message = ChatMessage::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'parent_id' => $request->parent_id,
            'telegram_topic_id' => $targetTopicId,
            'message' => $messageToSave,
            'type' => 'text',
            'source' => 'crm',
        ]);

        // Parse mentions using the MentionService
        $mentionService = new \App\Services\MentionService();
        $mentionedUserIds = $mentionService->parseAndNotify($messageToSave, $message);

        if (!empty($mentionedUserIds)) {
            $message->update([
                'meta_data' => array_merge($message->meta_data ?? [], [
                    'mentioned_user_ids' => $mentionedUserIds,
                ]),
            ]);
        }

        // 2. Sync with Telegram if applicable
        if ($project->telegram_group_id) {
            $user = Auth::user();
            $prefix = $user ? $user->name : 'Team';
            $telegramService = app(\App\Services\TelegramService::class);

            if ($isClientCommand) {
                // Sent via Cross-Topic Command
                if ($proxyTopic) {
                    $proxyMessage = "*(Sent via command by {$prefix})*: \n{$clientCommandText}";
                    $result = $telegramService->sendMessageToTopic($proxyTopic, $proxyMessage);
                    if ($result) {
                        $message->addTelegramResponse($result, 'proxy_topic');
                    }
                }

                // Original topic echo (so team knows it was sent)
                if ($request->telegram_topic_id && $request->telegram_topic_id != ($proxyTopic->id ?? 0)) {
                    $originalTopic = \App\Models\TelegramTopic::find($request->telegram_topic_id);
                    if ($originalTopic) {
                        $result = $telegramService->sendMessageToTopic($originalTopic, "✅ Reply sent to client(s): \n_{$clientCommandText}_");
                        if ($result) {
                            $message->addTelegramResponse($result, 'topic_echo');
                        }
                    }
                }

                // Relay to all clients directly
                foreach ($project->clients as $client) {
                    $result = $telegramService->sendDirectMessageToClient($client, $clientCommandText, $prefix);
                    if ($result) {
                        $message->addTelegramResponse($result, 'client_dm');
                    }
                }
            } else if ($request->telegram_topic_id) {
                // Sent normally
                $topic = \App\Models\TelegramTopic::find($request->telegram_topic_id);

                if ($topic) {
                    // Send to the Telegram group topic (keeps team synced)
                    $result = $telegramService->sendMessageToTopic($topic, $originalMessage, $prefix);
                    if ($result) {
                        $message->addTelegramResponse($result, 'topic_message');
                    }

                    // If it's the Proxy topic (Client Communication), relay to all clients directly
                    if ($topic->type === \App\Enums\TelegramTopicType::PROXY) {
                        foreach ($project->clients as $client) {
                            $result = $telegramService->sendDirectMessageToClient($client, $originalMessage, $prefix);
                            if ($result) {
                                $message->addTelegramResponse($result, 'client_dm');
                            }
                        }
                    }
                }
            }

            // Persistence is handled by saving the message if it was modified
            if ($message->isDirty('meta_data') || $message->isDirty('telegram_message_id')) {
                $message->save();
            }
        }

    }

//        // Mark sender's own message as read immediately
//        UserInteraction::firstOrCreate([
//            'user_id'          => Auth::id(),
//            'interactable_id'  => $message->id,
//            'interactable_type' => ChatMessage::class,
//            'interaction_type' => 'read',
//        ]);
//
//        // Broadcast to all project members via Reverb so the message appears
//        // in real-time for everyone without a page refresh.
//        ChatMessageSent::dispatch($message->load(['user', 'parent.user']));
//
//        return response()->json($message->load('user'));
//    }

    public function destroy(Request $request, Project $project, ChatMessage $chatMessage)
    {
        // Ensure message belongs to the project
        if ($chatMessage->project_id !== $project->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Authorize: Only the sender or a project admin can delete
        $user = Auth::user();
        if ($chatMessage->user_id !== $user->id && !$user->hasPermission('manage_projects')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // 1. Delete from Telegram (all instances)
        $telegramResults = $chatMessage->deleteFromTelegram();

        // 2. Delete from CRM
        $chatMessage->delete();

        return response()->json([
            'success' => true,
            'telegram_results' => $telegramResults
        ]);
    }

    // ---------------------------------------------------------------
    private function markProjectRead(Project $project, int $userId): void
    {
        // Find unread message IDs for this project (excluding own messages)
        $messageIds = ChatMessage::where('project_id', $project->id)
            ->where('user_id', '!=', $userId)
            ->whereNotExists(function ($q) use ($userId) {
                $q->from('user_interactions')
                    ->whereColumn('user_interactions.interactable_id', 'chat_messages.id')
                    ->where('user_interactions.interactable_type', ChatMessage::class)
                    ->where('user_interactions.user_id', $userId)
                    ->where('user_interactions.interaction_type', 'read');
            })
            ->pluck('id');

        $now = now()->toDateTimeString();
        $rows = $messageIds->map(fn ($id) => [
            'user_id'          => $userId,
            'interactable_id'  => $id,
            'interactable_type' => ChatMessage::class,
            'interaction_type' => 'read',
            'created_at'       => $now,
            'updated_at'       => $now,
        ])->all();

        if (!empty($rows)) {
            DB::table('user_interactions')->insertOrIgnore($rows);
        }
    }
}
