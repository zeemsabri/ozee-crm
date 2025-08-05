<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MeetingInvitation extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $meeting;
    protected $attendee;
    private array $payload;

    /**
     * Create a new notification instance.
     *
     * @param Meeting $meeting
     * @param MeetingAttendee $attendee
     * @return void
     */
    public function __construct(Meeting $meeting, MeetingAttendee $attendee)
    {
        $this->meeting = $meeting;
        $this->attendee = $attendee;
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
        $url = url('/projects/' . $this->meeting->project_id);
        $startTime = Carbon::parse($this->meeting->start_time)->format('Y-m-d H:i');
        $endTime = Carbon::parse($this->meeting->end_time)->format('Y-m-d H:i');

        return (new MailMessage)
            ->subject('Meeting Invitation: ' . $this->meeting->summary)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('You have been invited to a meeting.')
            ->line('Meeting: ' . $this->meeting->summary)
            ->when($this->meeting->description, function ($message) {
                return $message->line('Description: ' . $this->meeting->description);
            })
            ->line('Start Time: ' . $startTime)
            ->line('End Time: ' . $endTime)
            ->when($this->meeting->location, function ($message) {
                return $message->line('Location: ' . $this->meeting->location);
            })
            ->when($this->meeting->google_meet_link, function ($message) {
                return $message->line('Google Meet Link: ' . $this->meeting->google_meet_link);
            })
            ->action('View Meeting', $url)
            ->line('Thank you for using our application!');
    }

    public function getPayload()
    {
        $project = $this->meeting->project;
        $startTime = Carbon::parse($this->meeting->start_time)->format('Y-m-d H:i');
        $endTime = Carbon::parse($this->meeting->end_time)->format('Y-m-d H:i');

        return [
            'title' => $this->meeting->summary,
            'view_id' => Str::random(7),
            'project_name' => $project->name,
            'message' => 'You have been invited to a meeting: ' . $this->meeting->summary,
            'project_id' => $this->meeting->project_id,
            'description' => $this->meeting->description,
            'meeting_id' => $this->meeting->id,
            'meeting_name' => $this->meeting->summary,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'location' => $this->meeting->location,
            'google_meet_link' => $this->meeting->google_meet_link,
            'button_label' => 'View Meeting',
            'url' => url('/projects/' . $this->meeting->project_id)
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
