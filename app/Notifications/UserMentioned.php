<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class UserMentioned extends Notification implements ShouldQueue
{
    use Queueable;

    private array $payload;

    public function __construct(
        public Model $sourceModel,
        public User $mentionedByUser
    ) {
        $this->setPayload();
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    private function setPayload()
    {
        $this->payload = $this->getPayload();

        return $this;
    }

    public function toArray($notifiable)
    {
        return $this->payload;
    }

    private function getPayload()
    {
        $content = $this->sourceModel->message ?? $this->sourceModel->content ?? '';
        // Clean both @{id:name} and #{id:task_number}
        $cleanMessage = preg_replace('/[@#]\{(\d+):([^}]+)\}/', '$2', $content);

        // Handle project relationship if exists
        $projectName = 'Chat';
        $taskNumber = null;
        if (isset($this->sourceModel->project)) {
            $projectName = $this->sourceModel->project->name;
        } elseif (method_exists($this->sourceModel, 'project') && $this->sourceModel->project) {
            $projectName = $this->sourceModel->project->name;
        } elseif (isset($this->sourceModel->noteable) && isset($this->sourceModel->noteable->project)) {
            $projectName = $this->sourceModel->noteable->project->name;
        }

        $taskId = null;
        if ($this->sourceModel instanceof \App\Models\ProjectNote && $this->sourceModel->noteable_type === 'App\Models\Task') {
            $taskId = $this->sourceModel->noteable_id;
            if ($this->sourceModel->noteable) {
                $taskNumber = $this->sourceModel->noteable->task_number;
            }
        }

        return [
            'view_id' => Str::uuid()->toString(),
            'title' => 'New mention in ' . $projectName,
            'message' => $this->mentionedByUser->name . ' mentioned you: "' . Str::limit($cleanMessage, 100) . '"',
            'project_id' => $this->sourceModel->project_id ?? null,
            'task_id' => $taskId,
            'task_number' => $taskNumber,
            'source_id' => $this->sourceModel->id,
            'source_type' => class_basename($this->sourceModel),
            'url' => '#',
            'type' => 'user_mentioned',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return BroadcastMessage
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->payload);
    }
}
