<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TelegramTopic;
use App\Enums\TelegramTopicType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $token;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token') ?? '';
    }

    /**
     * Create a topic (forum thread) in a Telegram group.
     */
    public function createTopic(Project $project, string $name, TelegramTopicType $type = TelegramTopicType::CUSTOM)
    {
        $chatId = $project->telegram_group_id;

        if (!$chatId || !$this->token) {
            return null;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$this->token}/createForumTopic", [
                'chat_id' => $chatId,
                'name' => $name,
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['result']['message_thread_id'])) {
                $threadId = $data['result']['message_thread_id'];

                return TelegramTopic::create([
                    'project_id' => $project->id,
                    'name' => $name,
                    'telegram_thread_id' => $threadId,
                    'type' => $type->value,
                    'is_private' => false,
                ]);
            }

            // Handle specific error codes
            if (!$response->successful()) {
                $errorCode = $data['error_code'] ?? null;
                $description = $data['description'] ?? '';

                if ($errorCode === 403 || str_contains($description, 'not enough rights') || str_contains($description, 'administrator')) {
                    $this->sendManualInstructions($chatId);
                    return 'permission_error';
                }

                if (str_contains($description, 'chat is not a forum')) {
                    $this->sendForumInstructions($chatId);
                    return 'not_a_forum_error';
                }

                Log::error('Telegram API Error creating topic', [
                    'response' => $data,
                    'project_id' => $project->id
                ]);
            }

        }
        catch (\Exception $e) {
            Log::error('Telegram Service Exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Send instructions back to the chat if bot lacks permissions.
     */
    protected function sendManualInstructions($chatId)
    {
        $instructions = "⚠️ *Bot Permissions Required*\n\n" .
            "I don't have permission to create topics in this group.\n\n" .
            "*Action Needed:*\n" .
            "1. Open Group Info > *Edit*\n" .
            "2. Go to *Administrators*\n" .
            "3. Select this bot\n" .
            "4. Enable '*Manage Topics*' (or 'Manage Channels').";

        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $instructions,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Send instructions if the chat does not have Topics enabled.
     */
    protected function sendForumInstructions($chatId)
    {
        $instructions = "⚠️ *Topics Feature Not Enabled*\n\n" .
            "This Telegram group does not have the 'Topics' feature enabled, which is required for categorized discussions.\n\n" .
            "*Action Needed:*\n" .
            "1. Open Group Info > *Edit*\n" .
            "2. Locate the '*Topics*' toggle\n" .
            "3. Turn it *ON* and save.\n\n" .
            "After enabling, you may need to re-link or manually create topics from the CRM.";

        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $instructions,
            'parse_mode' => 'Markdown',
        ]);
    }

    /**
     * Create default topics for a newly linked project.
     */
    public function createDefaultTopics(Project $project)
    {
        $this->ensureClientTopicExists($project);
    }

    /**
     * Ensure a "Client Communication" (Proxy) topic exists for the project.
     */
    public function ensureClientTopicExists(Project $project): ?TelegramTopic
    {
        $topic = TelegramTopic::where('project_id', $project->id)
            ->where('type', TelegramTopicType::PROXY->value)
            ->first();

        if (!$topic) {
            return $this->createTopic($project, 'Client Communication', TelegramTopicType::PROXY);
        }

        return $topic;
    }

    /**
     * Ensure a local "General" topic exists for the project.
     * This is used for messages that don't belong to a specific Telegram thread.
     */
    public function ensureGeneralTopicExists(Project $project): TelegramTopic
    {
        $integrations = $project->integrations ?? [];
        
        // 1. Check if we already have it tracked in integrations
        $generalTopicId = $integrations['telegram_general_topic_id'] ?? null;
        if ($generalTopicId) {
            $topic = TelegramTopic::find($generalTopicId);
            if ($topic) {
                return $topic;
            }
        }

        // 2. Fallback: Search by name/type for this project
        $topic = TelegramTopic::where('project_id', $project->id)
            ->where('name', 'General')
            ->first();

        if (!$topic) {
            $topic = TelegramTopic::create([
                'project_id' => $project->id,
                'name' => 'General',
                'type' => TelegramTopicType::GENERAL->value,
                'telegram_thread_id' => null, // No specific thread ID for the main General chat
                'is_private' => false,
            ]);
        }

        // Update integrations JSON if needed
        if (($integrations['telegram_general_topic_id'] ?? null) !== $topic->id) {
            $integrations['telegram_general_topic_id'] = $topic->id;
            $project->update(['integrations' => $integrations]);
        }

        return $topic;
    }

    /**
     * Send a message from the CRM to a specific Telegram Topic.
     */
    public function sendMessageToTopic(TelegramTopic $topic, string $text, string $prefix = null)
    {
        $project = $topic->project;
        $chatId = $project->telegram_group_id;

        if (!$chatId || !$this->token) {
            return null;
        }

        if ($prefix) {
            $text = "💬 *{$prefix}:* \n{$text}";
        }

        $response = Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'message_thread_id' => $topic->telegram_thread_id, // Works for null (General) or specific threads
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        return $response->successful() ? $response->json('result') : null;
    }

    /**
     * Send a DM from the bot to a client.
     */
    public function sendDirectMessageToClient(\App\Models\Client $client, string $text, string $prefix = null)
    {
        $account = $client->telegramAccount;
        if (!$account || !$account->telegram_id) {
            return null;
        }

        if ($prefix) {
            $text = "💬 *{$prefix}:* \n{$text}";
        }

        $response = Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $account->telegram_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        return $response->successful() ? $response->json('result') : null;
    }

    /**
     * Delete a message from Telegram.
     */
    public function deleteMessage($chatId, $messageId): bool
    {
        if (!$chatId || !$messageId || !$this->token) {
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$this->token}/deleteMessage", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Telegram deleteMessage Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update the persistent keyboard menu for a client based on their projects.
     */
    public function updateClientPersistentMenu(\App\Models\Client $client, string $notificationText = null)
    {
        $account = $client->telegramAccount;
        if (!$account || !$account->telegram_id) {
            return;
        }

        $projects = $client->projects;
        $activeProject = $client->activeTelegramProject ?? $projects->first();

        // Prepare keyboard
        $keyboard = [];
        if ($projects->count() > 1) {
            $projectName = $activeProject ? $activeProject->name : 'None';
            $keyboard[] = [['text' => "📁 Active: {$projectName} (Tap to Switch)"]];
        } else {
            $keyboard[] = [['text' => '📁 View Project']];
        }

        $keyboard[] = [['text' => '❓ Help'], ['text' => '👤 Request Call']];

        // 1. Update the menu by sending a message (or just the menu)
        $text = $notificationText ?? ($activeProject ? "Your menu has been updated for project: *{$activeProject->name}*" : "Welcome! Your menu is ready.");

        $response = Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $account->telegram_id,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'is_persistent' => true,
            ])
        ]);

        return $response->successful() ? $response->json('result') : null;
    }

    /**
     * Send an inline keyboard allowing the client to switch their active project.
     */
    public function sendProjectSelectionMessage(\App\Models\Client $client)
    {
        $account = $client->telegramAccount;
        if (!$account || !$account->telegram_id) {
            return;
        }

        $projects = $client->projects;
        $activeProjectId = $client->active_telegram_project_id;

        $buttons = [];
        foreach ($projects as $project) {
            $prefix = ($project->id == $activeProjectId) ? "✅ " : "📁 ";
            $buttons[] = [['text' => $prefix . $project->name, 'callback_data' => "switch_project_{$project->id}"]];
        }

        $response = Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $account->telegram_id,
            'text' => "Select a project to set as active:",
            'reply_markup' => json_encode([
                'inline_keyboard' => $buttons
            ])
        ]);

        return $response->successful() ? $response->json('result') : null;
    }
}