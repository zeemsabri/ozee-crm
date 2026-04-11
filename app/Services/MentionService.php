<?php

namespace App\Services;

use App\Models\TelegramAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MentionService
{
    /**
     * Parse mentions in a string and send notifications.
     * 
     * @param string $content
     * @param Model $sourceModel The model where the mention happened (ChatMessage, ProjectNote, etc)
     * @return array List of mentioned user IDs
     */
    public function parseAndNotify(string $content, Model $sourceModel): array
    {
        $matches = [];
        preg_match_all('/@\{(\d+):([^}]+)\}/', $content, $matches);
        
        if (empty($matches[1])) {
            return [];
        }

        $mentionedUserIds = array_unique($matches[1]);
        $currentUser = Auth::user();
        
        if (!$currentUser instanceof User) {
            return $mentionedUserIds;
        }

        foreach ($mentionedUserIds as $userId) {
            // Don't notify yourself
            if ($userId == $currentUser->id) continue;
            
            $user = User::find($userId);
            if ($user) {
                // We use the same notification for now, or we could customize based on sourceModel
                $user->notify(new \App\Notifications\UserMentioned($sourceModel, $currentUser));
            }
        }

        return $mentionedUserIds;
    }

    /**
     * Convert CRM-specific tokens into plain text for external channels.
     */
    public function renderPlainText(string $content): string
    {
        preg_match_all('/@\{(\d+):([^}]+)\}/', $content, $matches);

        $usernamesById = [];

        if (!empty($matches[1])) {
            $mentionedUserIds = array_unique(array_map('intval', $matches[1]));

            $usernamesById = TelegramAccount::query()
                ->where('telegramable_type', User::class)
                ->whereIn('telegramable_id', $mentionedUserIds)
                ->get(['telegramable_id', 'username'])
                ->mapWithKeys(function (TelegramAccount $account) {
                    $username = trim((string) $account->username);

                    return [(int) $account->telegramable_id => ltrim($username, '@')];
                })
                ->all();
        }

        $content = preg_replace_callback('/@\{(\d+):([^}]+)\}/', function (array $matches) use ($usernamesById) {
            $userId = (int) ($matches[1] ?? 0);
            $name = trim((string) ($matches[2] ?? ''));
            $telegramUsername = $usernamesById[$userId] ?? '';

            if ($telegramUsername !== '') {
                return '@' . $telegramUsername;
            }

            return $name;
        }, $content);

        return preg_replace_callback('/#\{\d+:([^}:]+)(?::([^}]+))?\}/', function (array $matches) {
            $taskNumber = $matches[1] ?? '';
            $title = $matches[2] ?? '';

            return $title !== '' ? "#{$taskNumber} {$title}" : "#{$taskNumber}";
        }, $content);
    }
}
