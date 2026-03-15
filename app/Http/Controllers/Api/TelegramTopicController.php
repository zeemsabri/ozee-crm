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
        // Ensure the local "General" topic exists
        app(\App\Services\TelegramService::class)->ensureGeneralTopicExists($project);

        $topics = TelegramTopic::where('project_id', $project->id)
            ->orderByRaw("CASE WHEN type = 'general' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
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

        $telegramService = app(\App\Services\TelegramService::class);
        $type = $request->type ? (\App\Enums\TelegramTopicType::tryFrom($request->type) ?? \App\Enums\TelegramTopicType::CUSTOM) : \App\Enums\TelegramTopicType::CUSTOM;

        $result = $telegramService->createTopic($project, $request->name, $type);

        if ($result === 'not_a_forum_error') {
            return response()->json([
                'error' => 'Topics feature is not enabled in this Telegram group.',
                'instructions' => 'Please go to Group Info > Edit and toggle "Topics" to ON. This is required for threaded discussions.'
            ], 422);
        }

        if (!$result) {
            return response()->json(['error' => 'Failed to create Telegram topic. Ensure the group is linked and the bot is an administrator.'], 400);
        }

        return response()->json($result);
    }
}
