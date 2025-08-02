<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task;

    /**
     * Create a new notification instance.
     *
     * @param Task $task
     * @return void
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $url = url('/tasks/' . $this->task->id);

        return (new MailMessage)
            ->subject('New Task Assigned: ' . $this->task->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You have been assigned a new task.')
            ->line('Task: ' . $this->task->name)
            ->when($this->task->description, function ($message) {
                return $message->line('Description: ' . $this->task->description);
            })
            ->when($this->task->due_date, function ($message) {
                return $message->line('Due Date: ' . $this->task->due_date->format('Y-m-d'));
            })
            ->when($this->task->milestone, function ($message) {
                return $message->line('Milestone: ' . $this->task->milestone->name);
            })
            ->action('View Task', $url)
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'description' => $this->task->description,
            'due_date' => $this->task->due_date ? $this->task->due_date->format('Y-m-d') : null,
            'milestone_id' => $this->task->milestone_id,
            'milestone_name' => $this->task->milestone ? $this->task->milestone->name : null,
            'project_id' => $this->task->milestone ? $this->task->milestone->project_id : null,
            'project_name' => $this->task->milestone && $this->task->milestone->project ? $this->task->milestone->project->name : null,
        ];
    }
}
