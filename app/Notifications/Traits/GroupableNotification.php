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
     * @return array|null
     */
    protected function groupInDatabase($notifiable, string $groupKey, array $data): ?array
    {
        // 1. Look for an existing, UNREAD notification with this key
        $existing = $notifiable->unreadNotifications()
            ->where('data->group_key', $groupKey)
            ->first();

        if ($existing) {
            // 2. If it exists, UPDATE it instead of creating a new one
            $currentData = is_string($existing->data) ? json_decode($existing->data, true) : (array) $existing->data;
            if (!is_array($currentData)) $currentData = [];

            $currentCount = $currentData['count'] ?? 1;
            $history = $currentData['history'] ?? [];
            if (!is_array($history)) $history = [];
            
            // Capture the current summary into history
            $entry = $currentData;
            $entry['created_at'] = $existing->created_at ? $existing->created_at->toDateTimeString() : now()->toDateTimeString();
            unset($entry['history']); // Keep it flat
            $history[] = $entry;

            // Maintain a specifically requested "tasks" array
            $tasks = $currentData['tasks'] ?? [];
            if (isset($data['task_id'])) {
                $tasks[] = [
                    'id' => $data['task_id'],
                    'name' => $data['task_name'] ?? $data['title'] ?? 'Task',
                    'task_number' => $data['task_number'] ?? null,
                    'url' => $data['url'] ?? '#',
                ];
            }

            // Prepare the new data set
            $newData = array_merge($currentData, $data, [
                'count' => $currentCount + 1,
                'history' => array_slice($history, -10),
                'tasks' => $tasks,
                'last_updated_at' => now()->toDateTimeString(),
                'group_key' => $groupKey,
            ]);

            // Save back using raw SQL
            \Illuminate\Support\Facades\DB::table('notifications')
                ->where('id', $existing->id)
                ->update([
                    'data' => json_encode($newData),
                    'created_at' => now(), // Move to top
                    'updated_at' => now(),
                    'read_at' => null,
                ]);
            
            return null; // Return null to signal it was already handled/grouped
        }

        // 3. If no unread notification exists, create a fresh one
        $tasks = [];
        if (isset($data['task_id'])) {
            $tasks[] = [
                'id' => $data['task_id'],
                'name' => $data['task_name'] ?? $data['title'] ?? 'Task',
                'task_number' => $data['task_number'] ?? null,
                'url' => $data['url'] ?? '#',
            ];
        }

        return array_merge($data, [
            'group_key' => $groupKey,
            'count' => 1,
            'tasks' => $tasks,
            'last_updated_at' => now()->toDateTimeString(),
        ]);
    }
}
