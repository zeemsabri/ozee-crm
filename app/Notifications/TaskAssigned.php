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

class TaskAssigned extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

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
        $this->setPaylaod();
    }

    private function setPaylaod()
    {
        $this->payload = $this->getPayload();

        return $this;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // Add 'broadcast' to the array of channels
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
        $projectName = $project?->name ?? null;

        return [
            'title' => $this->task->name,
            'view_id' => Str::random(7),
            'project_name' => $projectName,
            'message' => 'You have been assigned a new task: '.$this->task->name,
            'project_id' => $this->task->milestong?->project_id,
            'description' => $this->task->description,
            'task_type' => $this->task->type,
            'priority' => 'low',
            'task_id' => $this->task->id,
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
}
