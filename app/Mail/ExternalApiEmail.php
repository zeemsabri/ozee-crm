<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalApiEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public ?string $htmlBody,
        public ?string $textBody,
        public string $fromAddress,
        public ?string $fromName = null,
        public ?string $replyToAddress = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            from: new Address($this->fromAddress, $this->fromName ?: null),
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress)] : [],
        );
    }

    public function content(): Content
    {
        $html = $this->htmlBody ?? nl2br(e((string) $this->textBody));

        return new Content(
            htmlString: $html,
        );
    }
}
