<?php

namespace App\Mail;

use App\Models\NoticeBoard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public NoticeBoard $notice,
        public string $name,
        public ?string $email = null,
        public bool $isClient = false)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Notice] '.$this->notice->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $emailTrackingUrl = route('notice.track', ['id' => $this->notice->id, 'email' => $this->email]);

        return new Content(
            view: 'emails.notice-board',
            with: [
                'name'             => $this->name,
                'notice'           => $this->notice,
                'emailTrackingUrl' => $emailTrackingUrl,
                'greeting'         => $this->isClient
                    ? 'Hi'
                    : 'As-Salamu Alaykum',
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
