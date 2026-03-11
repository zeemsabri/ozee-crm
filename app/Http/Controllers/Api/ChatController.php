<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Email;
use App\Models\Project;
use App\Models\UserInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $limit  = $request->input('limit', 20);
        $before = $request->input('before');
        $userId = Auth::id();

        // Fetch Chat Messages with read receipts
        $messagesQuery = ChatMessage::where('project_id', $project->id)
            ->with([
                'user',
                'parent.user',
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

        // Fetch Emails related to project (via conversation)
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

        $formattedMessages = $messages->map(function ($msg) use ($userId) {
            $reads = $msg->interactions->map(fn ($i) => [
                'user'    => $i->user?->name ?? 'Someone',
                'read_at' => $i->updated_at->toDateTimeString(),
            ]);

            return [
                'id'         => $msg->id,
                'type'       => 'text',
                'user'       => $msg->user?->name ?? 'System',
                'user_id'    => $msg->user_id,
                'initials'   => strtoupper(substr($msg->user?->name ?? 'S', 0, 2)),
                'color'      => 'bg-indigo-600',
                'message'    => $msg->message,
                'parent'     => $msg->parent ? [
                    'id'      => $msg->parent->id,
                    'user'    => $msg->parent->user?->name ?? 'System',
                    'message' => Str::limit($msg->parent->message, 50),
                ] : null,
                'reads'      => $reads,
                'time'       => $msg->created_at->diffForHumans(),
                'created_at' => $msg->created_at->toDateTimeString(),
                'is_me'      => $msg->user_id === $userId,
            ];
        });

        // Also auto-mark messages as read when fetched
        $this->markProjectRead($project, $userId);

        // Combine and sort chronologically
        $combined = $formattedMessages->concat($emails)->sortBy('created_at')->values();

        return response()->json($combined);
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
            'message'   => 'required|string',
            'parent_id' => 'nullable|integer|exists:chat_messages,id',
        ]);

        $message = ChatMessage::create([
            'project_id' => $project->id,
            'user_id'    => Auth::id(),
            'parent_id'  => $request->parent_id,
            'message'    => $request->message,
            'type'       => 'text',
        ]);

        // Parse mentions using the MentionService
        $mentionService     = new \App\Services\MentionService();
        $mentionedUserIds   = $mentionService->parseAndNotify($request->message, $message);

        if (!empty($mentionedUserIds)) {
            $message->update([
                'meta_data' => array_merge($message->meta_data ?? [], [
                    'mentioned_user_ids' => $mentionedUserIds,
                ]),
            ]);
        }

        // Mark sender's own message as read immediately
        UserInteraction::firstOrCreate([
            'user_id'          => Auth::id(),
            'interactable_id'  => $message->id,
            'interactable_type' => ChatMessage::class,
            'interaction_type' => 'read',
        ]);

        return response()->json($message->load('user'));
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
