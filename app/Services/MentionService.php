<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserMentionedInChat;
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
}
