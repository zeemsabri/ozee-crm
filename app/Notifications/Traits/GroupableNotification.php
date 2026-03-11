<?php

namespace App\Notifications\Traits;

trait GroupableNotification
{
    /**
     * Group a notification at the database level.
     * 
     * @param mixed $notifiable
     * @param string $groupKey
     * @param array $data
     * @return array
     */
    protected function groupInDatabase($notifiable, string $groupKey, array $data)
    {
        // 1. Look for an existing, UNREAD notification with this key
        $existing = $notifiable->unreadNotifications()
            ->where('data->group_key', $groupKey)
            ->first();

        if ($existing) {
            // 2. If it exists, UPDATE it instead of creating a new one
            $currentData = $existing->data;
            $currentCount = $currentData['count'] ?? 1;
            $updates = $currentData['updates'] ?? [];
            
            // Capture the current data as an update before overwriting it
            $capturedUpdate = $currentData;
            $capturedUpdate['created_at'] = $existing->created_at->toDateTimeString();
            unset($capturedUpdate['updates']); // Remove nested updates to keep it flat
            $updates[] = $capturedUpdate;

            $existing->update([
                'data' => array_merge($currentData, $data, [
                    'count' => $currentCount + 1,
                    'updates' => array_slice($updates, -10), // Store up to 10 previous updates
                    'last_updated_at' => now()->toDateTimeString(),
                ]),
                'created_at' => now(), // Bump to the top
            ]);
            
            // Return empty to prevent Laravel from creating a new row if we're using database channel
            // Note: This relies on the calling notification returning [] for toDatabase()
            return [];
        }

        // 3. If no unread notification exists, create a fresh one
        return array_merge($data, [
            'group_key' => $groupKey,
            'count' => 1,
            'last_updated_at' => now()->toDateTimeString(),
        ]);
    }
}
