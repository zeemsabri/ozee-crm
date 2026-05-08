<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Email;
use App\Models\FileAttachment;
use App\Models\Project;
use App\Models\UserInteraction;
use App\Services\ChatAttachmentService;
use App\Services\GoogleDriveService;
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
                'files',
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
                'attachments' => $msg->files->map(fn ($file) => $this->serializeAttachment($file))->values(),
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
                'files',
                'interactions' => function ($q) {
                    $q->with('user:id,name')->where('interaction_type', 'read');
                },
            ]);

        // Cursor logic based on since_id and before_id
        if ($request->has('since_id')) {
            $query->where('id', '>', $request->since_id);
        } elseif ($request->has('before_id')) {
            $query->where('id', '<', $request->before_id);
        }

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

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
                'attachments' => $msg->files->map(fn ($file) => $this->serializeAttachment($file))->values(),
            ];
        });

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

        return response()->json($message->load('user'));

    }

    public function storeAttachments(Request $request, Project $project, ChatAttachmentService $chatAttachmentService)
    {
        $request->validate([
            'message' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:chat_messages,id',
            'telegram_topic_id' => 'nullable|integer|exists:telegram_topics,id',
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:20480',
        ]);

        $messageText = trim((string) $request->input('message', ''));
        $chatMessage = $this->createChatMessageWithoutBroadcast([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'parent_id' => $request->parent_id,
            'telegram_topic_id' => $request->telegram_topic_id,
            'message' => $messageText !== '' ? $messageText : 'Shared attachment',
            'type' => 'file',
            'source' => 'crm',
            'meta_data' => null,
        ]);

        try {
            $chatAttachmentService->attachUploadedFiles($project, $chatMessage, $request->file('files'));
        } catch (\Throwable $e) {
            $chatMessage->delete();
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $chatMessage->load(['user', 'client', 'parent.user', 'parent.client', 'files']);
        ChatMessageSent::dispatch($chatMessage);

        return response()->json($this->formatChatMessageResponse($chatMessage, Auth::id()));
    }

    public function createDriveDocument(Request $request, Project $project, ChatAttachmentService $chatAttachmentService)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:chat_messages,id',
            'telegram_topic_id' => 'nullable|integer|exists:telegram_topics,id',
        ]);

        $messageText = trim((string) $request->input('message', ''));
        $title = trim((string) $request->input('title'));

        $chatMessage = $this->createChatMessageWithoutBroadcast([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'parent_id' => $request->parent_id,
            'telegram_topic_id' => $request->telegram_topic_id,
            'message' => $messageText !== '' ? $messageText : "Created document: {$title}",
            'type' => 'file',
            'source' => 'crm',
            'meta_data' => null,
        ]);

        try {
            $chatAttachmentService->attachNewDriveDocument($project, $chatMessage, $title);
        } catch (\Throwable $e) {
            $chatMessage->delete();
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $chatMessage->load(['user', 'client', 'parent.user', 'parent.client', 'files']);
        ChatMessageSent::dispatch($chatMessage);

        return response()->json($this->formatChatMessageResponse($chatMessage, Auth::id()));
    }

    public function referenceDriveFile(Request $request, Project $project, ChatAttachmentService $chatAttachmentService)
    {
        $request->validate([
            'drive_file_id' => 'nullable|string|max:255',
            'drive_url' => 'nullable|url|max:2000',
            'name' => 'nullable|string|max:255',
            'mime_type' => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'parent_id' => 'nullable|integer|exists:chat_messages,id',
            'telegram_topic_id' => 'nullable|integer|exists:telegram_topics,id',
        ]);

        if (! $request->filled('drive_file_id') && ! $request->filled('drive_url')) {
            return response()->json([
                'message' => 'Either drive_file_id or drive_url is required.',
            ], 422);
        }

        $messageText = trim((string) $request->input('message', ''));
        $chatMessage = $this->createChatMessageWithoutBroadcast([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'parent_id' => $request->parent_id,
            'telegram_topic_id' => $request->telegram_topic_id,
            'message' => $messageText !== '' ? $messageText : 'Shared a Google Drive file',
            'type' => 'file',
            'source' => 'crm',
            'meta_data' => null,
        ]);

        $chatAttachmentService->attachDriveReference($project, $chatMessage, $request->only([
            'drive_file_id',
            'drive_url',
            'name',
            'mime_type',
        ]));

        $chatMessage->load(['user', 'client', 'parent.user', 'parent.client', 'files']);
        ChatMessageSent::dispatch($chatMessage);

        return response()->json($this->formatChatMessageResponse($chatMessage, Auth::id()));
    }

    public function browseDriveFolder(
        Request $request,
        Project $project,
        ChatAttachmentService $chatAttachmentService,
        GoogleDriveService $googleDriveService
    ) {
        $request->validate([
            'folder_id' => 'nullable|string|max:255',
            'page_size' => 'nullable|integer|min:1|max:200',
        ]);

        $rootFolderId = $chatAttachmentService->resolveProjectDriveFolderId($project);
        if (! $rootFolderId) {
            return response()->json([
                'message' => 'Project Google Drive folder is not configured.',
            ], 422);
        }

        $folderId = $request->input('folder_id', $rootFolderId);
        $pageSize = (int) $request->input('page_size', 100);

        try {
            $items = $googleDriveService->listFolderItems((string) $folderId, $pageSize);
            return response()->json([
                'root_folder_id' => $rootFolderId,
                'current_folder_id' => (string) $folderId,
                'items' => $items,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to fetch Google Drive folder items.',
            ], 422);
        }
    }

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

    private function createChatMessageWithoutBroadcast(array $attributes): ChatMessage
    {
        $now = now();
        $id = DB::table('chat_messages')->insertGetId([
            'project_id' => $attributes['project_id'],
            'user_id' => $attributes['user_id'] ?? null,
            'client_id' => $attributes['client_id'] ?? null,
            'telegram_topic_id' => $attributes['telegram_topic_id'] ?? null,
            'parent_id' => $attributes['parent_id'] ?? null,
            'telegram_message_id' => $attributes['telegram_message_id'] ?? null,
            'message' => $attributes['message'],
            'source' => $attributes['source'] ?? 'crm',
            'type' => $attributes['type'] ?? 'text',
            'meta_data' => $attributes['meta_data'] ? json_encode($attributes['meta_data']) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $message = ChatMessage::query()->findOrFail($id);

        if ($message->user_id) {
            UserInteraction::firstOrCreate([
                'user_id' => $message->user_id,
                'interactable_id' => $message->id,
                'interactable_type' => ChatMessage::class,
                'interaction_type' => 'read',
            ]);
        }

        return $message;
    }

    private function formatChatMessageResponse(ChatMessage $message, int $userId): array
    {
        $reads = $message->interactions->map(fn ($i) => [
            'user' => $i->user?->name ?? 'Someone',
            'read_at' => $i->updated_at->toDateTimeString(),
        ]);

        $userName = $message->user?->name ?? ($message->client?->name ?? ($message->meta_data['telegram_from']['first_name'] ?? 'Telegram User'));

        return [
            'id' => $message->id,
            'type' => $message->source === 'telegram' ? 'telegram' : ($message->type ?? 'text'),
            'user' => $userName,
            'user_id' => $message->user_id,
            'client_id' => $message->client_id,
            'initials' => strtoupper(substr($userName, 0, 2)),
            'color' => $message->client_id ? 'bg-sky-500' : ($message->source === 'telegram' ? 'bg-sky-500' : 'bg-indigo-600'),
            'message' => $message->message,
            'parent' => $message->parent ? [
                'id' => $message->parent->id,
                'user' => $message->parent->user?->name ?? ($message->parent->client?->name ?? 'System'),
                'message' => Str::limit($message->parent->message, 50),
            ] : null,
            'reads' => $reads,
            'time' => $message->created_at->diffForHumans(),
            'created_at' => $message->created_at->toDateTimeString(),
            'is_me' => $message->user_id === $userId,
            'source' => $message->source,
            'attachments' => $message->files->map(fn ($file) => $this->serializeAttachment($file))->values(),
        ];
    }

    private function serializeAttachment(FileAttachment $file): array
    {
        return [
            'id' => $file->id,
            'filename' => $file->filename,
            'mime_type' => $file->mime_type,
            'file_size' => $file->file_size,
            'path' => $file->path,
            'url' => $file->path_url ?: $file->path,
            'thumbnail_url' => $file->thumbnail_url ?: $file->thumbnail,
            'google_drive_file_id' => $file->google_drive_file_id,
        ];
    }
}
