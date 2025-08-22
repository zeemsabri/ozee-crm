<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskApprovalCompleted extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected Task $task;
    private array $payload;

    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->setPayload();
    }

    private function setPayload(): self
    {
        $this->payload = $this->getPayload();
        return $this;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail($notifiable)
    {
        $url = url('/project/' . ($this->task->milestone?->project?->id) . '/task/' . $this->task->id);

        return (new MailMessage)
            ->subject('Task Completed (Approval Needed): ' . $this->task->name)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . '!')
            ->line('A task that you requested approval for has been marked complete and is awaiting your review.')
            ->line('Task: ' . $this->task->name)
            ->when($this->task->description, fn($m) => $m->line('Description: ' . $this->task->description))
            ->when($this->task->milestone, fn($m) => $m->line('Milestone: ' . $this->task->milestone->name))
            ->action('View Task', $url)
            ->line('Thank you!');
    }

    public function getPayload(): array
    {
        $project = $this->task->milestone?->project;
        return [
            'title' => $this->task->name,
            'view_id' => Str::random(7),
            'project_name' => $project?->name,
            'message' => 'Task marked complete and awaiting your approval: ' . $this->task->name,
            'project_id' => $project?->id,
            'description' => $this->task->description,
            'task_type' => $this->task->type,
            'priority' => $this->task->priority ?? 'medium',
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'button_label' => 'View Task',
            'due_date' => $this->task->due_date ? $this->task->due_date->format('Y-m-d') : null,
            'url' => url('/project/' . ($project?->id) . '/task/' . $this->task->id)
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
