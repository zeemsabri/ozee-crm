<?php

namespace App\Notifications;

use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Str;

class EmailApprovalRequired extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $email;
    private array $payload;

    /**
     * Create a new notification instance.
     *
     * @param Email $email
     * @return void
     */
    public function __construct(Email $email)
    {
        $this->email = $email;
        $this->setPayload();
    }

    private function setPayload()
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
        // Only using database and broadcast channels as specified in requirements
        return ['database', 'broadcast'];
    }

    /**
     * Get the payload for the notification.
     *
     * @return array
     */
    public function getPayload()
    {
        $project = $this->email->conversation->project ?? null;
        $projectName = $project?->name ?? null;
        $emailType = $this->email->type === 'received' ? 'received' : 'sent';

        $title = $emailType === 'received'
            ? 'Received Email Approval Required'
            : 'Email Approval Required';

        $message = $emailType === 'received'
            ? 'A received email requires your approval: ' . $this->email->subject
            : 'An email requires your approval: ' . $this->email->subject;

        return [
            'title' => $title,
            'view_id' => Str::random(7),
            'project_name' => $projectName,
            'message' => $message,
            'project_id' => $project?->id,
            'description' => substr($this->email->body, 0, 100) . (strlen($this->email->body) > 100 ? '...' : ''),
            'task_type' => 'email_approval',
            'priority' => 'medium',
            'email_id' => $this->email->id,
            'email_subject' => $this->email->subject,
            'email_type' => $emailType,
            'button_label'  =>  'View Project',
            'correlation_id' => 'email_approval_' . $this->email->id,
            'url' => url('/projects/' . $project->id)
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
