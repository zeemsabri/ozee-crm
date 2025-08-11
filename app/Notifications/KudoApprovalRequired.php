<?php

namespace App\Notifications;

use App\Models\Kudo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Str;

class KudoApprovalRequired extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected Kudo $kudo;
    private array $payload;

    public function __construct(Kudo $kudo)
    {
        $this->kudo = $kudo->load(['sender', 'recipient', 'project']);
        $this->setPayload();
    }

    private function setPayload(): self
    {
        $this->payload = $this->getPayload();
        return $this;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function getPayload(): array
    {
        $project = $this->kudo->project;
        $projectName = $project?->name;

        return [
            'title' => 'Kudo Approval Required',
            'view_id' => Str::random(7),
            'project_name' => $projectName,
            'message' => 'A new kudo requires your approval for ' . ($this->kudo->recipient?->name ?? 'a teammate'),
            'project_id' => $project?->id,
            'description' => substr((string) $this->kudo->comment, 0, 100) . (strlen((string) $this->kudo->comment) > 100 ? '...' : ''),
            'task_type' => 'kudo_approval',
            'priority' => 'low',
            'kudo_id' => $this->kudo->id,
            'button_label'  =>  'View Project',
            'correlation_id' => 'kudo_approval_' . $this->kudo->id,
            'url' => $project ? url('/projects/' . $project->id) : url('/dashboard'),
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->payload);
    }

    public function toArray($notifiable)
    {
        return $this->payload;
    }
}
