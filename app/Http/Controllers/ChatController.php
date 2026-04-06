<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\ChatMessage;
use App\Models\Project;

class ChatController extends Controller
{
    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $projects = Project::where('user_id', $user->id)->get();

        return response()->json($projects);
    }

    public function sendMessage(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $project = Project::find($request->project_id);

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $chatMessage = ChatMessage::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'message' => $request->message,
        ]);

        // Send to Telegram
        $this->telegramService->sendMessage($project->chat_id, $request->message, $project->topic_id, $request->reply_to_message_id, $chatMessage->id);

        return response()->json(['message' => 'Message sent successfully']);
    }
}
