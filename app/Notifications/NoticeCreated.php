<?php

namespace App\Notifications;

use App\Models\NoticeBoard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class NoticeCreated extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public array $channels;

    public function __construct(public NoticeBoard $notice, array $channels = ['broadcast', 'database'])
    {
        // Ensure channels are unique and valid Laravel channels
        $allowed = ['mail', 'broadcast', 'database'];
        $this->channels = array_values(array_intersect($allowed, array_unique($channels)));
        if (empty($this->channels)) {
            $this->channels = ['database'];
        }
    }

    public function via($notifiable)
    {
        return $this->channels;
    }

    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject('[Notice] ' . $this->notice->title)
            ->greeting('Hello ' . ($notifiable->name ?? ''))
            ->line($this->notice->description ?: 'You have a new notice.')
            ->line('Type: ' . $this->notice->type);

        if (!empty($this->notice->url)) {
            $mail->action('View Notice', url('/dashboard'));
        }

        return $mail;
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toArray($notifiable)
    {
        return [
            'notice_id' => $this->notice->id,
            'title' => $this->notice->title,
            'description' => $this->notice->description,
            'type' => $this->notice->type,
            'full_modal'    =>  true,
            'url' => $this->notice->url,
            'created_at' => $this->notice->created_at,
        ];
    }
}
