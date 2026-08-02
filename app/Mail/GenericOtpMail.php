<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $subjectTitle = 'Your Verification Code',
        public string $contextMessage = 'Use the code below to verify your identity.'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.generic-otp',
            with: [
                'otp' => $this->otp,
                'contextMessage' => $this->contextMessage,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
