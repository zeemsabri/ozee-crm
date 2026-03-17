<?php

namespace App\Notifications;

use App\Mail\NoticeMail;
use App\Models\NoticeBoard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NoticeCreated extends Notification implements ShouldBroadcast, ShouldQueue
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
        // Use routeNotificationForMail() if available (works for both User and Client),
        // otherwise fall back to the email attribute directly.
        $email = method_exists($notifiable, 'routeNotificationForMail')
            ? $notifiable->routeNotificationForMail()
            : $notifiable->email;

        return (new NoticeMail($this->notice, $notifiable->name, $email))
            ->to($email);
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
            'full_modal' => true,
            'url' => $this->notice->url,
            'created_at' => $this->notice->created_at,
        ];
    }
}
