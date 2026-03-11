<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ChatMessage;
use App\Models\Email;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $limit = $request->input('limit', 20);
        $before = $request->input('before'); // timestamp for cursor pagination

        // Fetch Chat Messages
        $messagesQuery = ChatMessage::where('project_id', $project->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($before) {
            $messagesQuery->where('created_at', '<', $before);
        }

        $messages = $messagesQuery->get();

        // Fetch Emails related to project (via conversation)
        // Interleaving logic: we pull emails around the same timeframe
        $emailsQuery = Email::whereHas('conversation', function($q) use ($project) {
                $q->where('project_id', $project->id);
            })
            ->with(['contexts' => function($q) {
                $q->select('id', 'summary', 'referencable_id', 'referencable_type');
            }])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($before) {
            $emailsQuery->where('created_at', '<', $before);
        }

        $emails = $emailsQuery->get()->map(function($email) {
            return [
                'id' => 'email_' . $email->id,
                'type' => 'email',
                'user' => $email->sender_name ?? 'Client',
                'initials' => 'AC',
                'color' => 'bg-slate-700',
                'summary' => $email->contexts->first()?->summary ?? Str::limit($email->body, 100),
                'direction' => $email->type === 'received' ? 'inbound' : 'outbound',
                'time' => $email->created_at->diffForHumans(),
                'created_at' => $email->created_at->toDateTimeString(),
            ];
        });

        $formattedMessages = $messages->map(function($msg) {
            return [
                'id' => $msg->id,
                'type' => 'text',
                'user' => $msg->user?->name ?? 'System',
                'initials' => strtoupper(substr($msg->user?->name ?? 'S', 0, 2)),
                'color' => 'bg-indigo-600',
                'message' => $msg->message,
                'time' => $msg->created_at->diffForHumans(),
                'created_at' => $msg->created_at->toDateTimeString(),
                'is_me' => $msg->user_id === Auth::id(),
            ];
        });

        // Combine and sort chronologically (oldest first, newest last for timeline feel)
        $combined = $formattedMessages->concat($emails)->sortBy('created_at')->values();

        return response()->json($combined);
    }

    public function store(Request $request, Project $project)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = ChatMessage::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'message' => $request->message,
            'type' => 'text',
        ]);

        // Parse mentions using the MentionService
        $mentionService = new \App\Services\MentionService();
        $mentionedUserIds = $mentionService->parseAndNotify($request->message, $message);
        
        if (!empty($mentionedUserIds)) {
            // Store mentioned user IDs in meta_data
            $message->update([
                'meta_data' => array_merge($message->meta_data ?? [], [
                    'mentioned_user_ids' => $mentionedUserIds
                ])
            ]);
        }

        // TODO: Broadcast NewChatMessage event

        return response()->json($message->load('user'));
    }
}
