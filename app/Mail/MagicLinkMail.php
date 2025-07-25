<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The magic link instance.
     *
     * @var \App\Models\MagicLink
     */
    public $magicLink;

    /**
     * The project instance.
     *
     * @var \App\Models\Project
     */
    public $project;

    /**
     * The magic link URL.
     *
     * @var string
     */
    public $url;

    /**
     * Create a new message instance.
     *
     * @param  \App\Models\MagicLink  $magicLink
     * @param  \App\Models\Project  $project
     * @param  string  $url
     */
    public function __construct($magicLink, $project, $url)
    {
        $this->magicLink = $magicLink;
        $this->project = $project;
        $this->url = $url;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Magic Link for {$this->project->name} Project",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
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
