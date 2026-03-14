<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TelegramTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramTopicController extends Controller
{
    /**
     * Get all topics for a project.
     */
    public function index(Request $request, Project $project)
    {
        $topics = TelegramTopic::where('project_id', $project->id)
            ->latest()
            ->get();

        return response()->json($topics);
    }

    /**
     * Create a new Telegram topic for a project.
     * Must be called when a user wants to create a new topic to chat in.
     */
    public function store(Request $request, Project $project)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string',
            'is_private' => 'nullable|boolean',
        ]);

        $token = config('services.telegram.bot_token');
        $chatId = $project->telegram_group_id; 

        // If no telegram group ID exists on project, we just create it locally for CRM use for now
        $threadId = null;

        if ($chatId && $token) {
            try {
                // Create Topic in Telegram
                $topicResponse = Http::post("https://api.telegram.org/bot{$token}/createForumTopic", [
                    'chat_id' => $chatId,
                    'name' => $request->name,
                ]);

                if ($topicResponse->successful()) {
                    $topicData = $topicResponse->json();
                    if (isset($topicData['result']['message_thread_id'])) {
                        $threadId = $topicData['result']['message_thread_id'];

                        // Optional: Send initial welcome text to the thread
                        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                            'chat_id' => $chatId,
                            'message_thread_id' => $threadId,
                            'text' => "🚀 Topic '{$request->name}' created via CRM.",
                        ]);
                    }
                } else {
                    Log::error('Failed to create Telegram forum topic: ' . $topicResponse->body());
                    return response()->json(['error' => 'Failed to create Telegram topic.'], 400);
                }
            } catch (\Exception $e) {
                Log::error('Exception creating Telegram forum topic: ' . $e->getMessage());
                // We could choose to proceed and create it locally even if telegram fails, 
                // but usually the user wants it synced. Let's return error.
                return response()->json(['error' => 'Could not connect to Telegram API.'], 500);
            }
        }

        $topic = TelegramTopic::create([
            'project_id' => $project->id,
            'name' => $request->name,
            'telegram_thread_id' => $threadId,
            'type' => $request->type ?? 'general',
            'is_private' => $request->is_private ?? false,
        ]);

        return response()->json($topic);
    }
}
