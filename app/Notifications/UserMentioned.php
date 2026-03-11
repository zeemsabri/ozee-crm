<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class UserMentioned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Model $sourceModel,
        public User $mentionedByUser
    ) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $content = $this->sourceModel->message ?? $this->sourceModel->content ?? '';
        $cleanMessage = preg_replace('/@\{(\d+):([^}]+)\}/', '@$2', $content);
        
        // Handle project relationship if exists
        $projectName = 'Chat';
        if (isset($this->sourceModel->project)) {
            $projectName = $this->sourceModel->project->name;
        } elseif (method_exists($this->sourceModel, 'project') && $this->sourceModel->project) {
             $projectName = $this->sourceModel->project->name;
        } elseif (isset($this->sourceModel->noteable) && isset($this->sourceModel->noteable->project)) {
             $projectName = $this->sourceModel->noteable->project->name;
        }

        return [
            'view_id' => Str::uuid()->toString(),
            'title' => 'New mention in ' . $projectName,
            'message' => $this->mentionedByUser->name . ' mentioned you: "' . Str::limit($cleanMessage, 100) . '"',
            'project_id' => $this->sourceModel->project_id ?? null,
            'url' => '#', 
        ];
    }
}
