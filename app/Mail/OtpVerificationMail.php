<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public string $projectName
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Verification Code — '.$this->projectName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp-verification',
            with: [
                'otp'         => $this->otp,
                'projectName' => $this->projectName,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
