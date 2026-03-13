<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Traits\GroupableNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskAssigned extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable, GroupableNotification;

    protected $task;

    private array $payload;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->setPayload();
    }

    private function setPayload()
    {
        $this->payload = $this->getPayload();

        return $this;
    }

    protected ?array $databaseData = null;

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $project = $this->task->milestone?->project ?? null;
        $projectId = $project?->id ?? 0;
        $groupKey = "task_assigned_project_{$projectId}";

        $data = $this->payload;
        $data['type'] = 'task_assigned';

        // Call our specialized grouping logic
        $this->databaseData = $this->groupInDatabase($notifiable, $groupKey, $data);

        // If databaseData is null, it means groupInDatabase already updated an existing record.
        // In this case, we skip the 'database' channel to avoid creating a new (empty) row.
        if ($this->databaseData === null) {
            return ['broadcast'];
        }

        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $url = url('/tasks/'.$this->task->id);

        return (new MailMessage)
            ->subject('New Task Assigned: '.$this->task->name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You have been assigned a new task.')
            ->line('Task: '.$this->task->name)
            ->when($this->task->description, function ($message) {
                return $message->line('Description: '.$this->task->description);
            })
            ->when($this->task->due_date, function ($message) {
                return $message->line('Due Date: '.$this->task->due_date->format('Y-m-d'));
            })
            ->when($this->task->milestone, function ($message) {
                return $message->line('Milestone: '.$this->task->milestone->name);
            })
            ->action('View Task', $url)
            ->line('Thank you for using our application!');
    }

    public function getPayload()
    {
        $project = $this->task->milestone?->project ?? null;
        $projectId = $project?->id ?? 0;
        $projectName = $project?->name ?? null;

        // Use a stable view_id based on the project grouping to ensure consistency
        // when multiple notifications are merged into one database row.
        $view_id = $projectId ? substr(md5("task_assigned_project_{$projectId}"), 0, 8) : Str::random(7);

        return [
            'title' => $this->task->name,
            'view_id' => $view_id,
            'project_name' => $projectName,
            'message' => 'You have been assigned a new task: '.$this->task->name,
            'project_id' => $project?->id,
            'description' => $this->task->description,
            'task_type' => $this->task->type,
            'priority' => 'low',
            'task_id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'task_name' => $this->task->name,
            'button_label' => 'View Task',
            'due_date' => $this->task->due_date ? $this->task->due_date->format('Y-m-d') : null,
            'url' => url('/project/'.$project?->id.'/task/'.$this->task->id),
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

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return $this->payload;
    }

    /**
     * Get the database representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return $this->databaseData ?: [];
    }
}
