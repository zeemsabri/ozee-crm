<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class FamifyThankYouMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $userType;
    public ?string $name;
    public ?string $parentGoal;
    public ?string $childAge;
    public ?string $creatorGoal;

    public function __construct(string $userType, ?string $name = null, ?string $parentGoal = null, ?string $childAge = null, ?string $creatorGoal = null)
    {
        $this->userType = $userType;
        $this->name = $name;
        $this->parentGoal = $parentGoal;
        $this->childAge = $childAge;
        $this->creatorGoal = $creatorGoal;
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->userType) {
            'Parent' => 'Thanks for reaching out — Famify Hub',
            'Content Creator' => 'Thanks for getting in touch — Famify Hub',
            default => 'Thanks for contacting Famify Hub',
        };

        return new Envelope(
            from: new Address(env('FAMIFY_MAIL_FROM_ADDRESS', 'hello@famifyhub.com.au'), env('FAMIFY_MAIL_FROM_NAME', 'Famify Hub')),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.famify-thankyou',
            with: [
                'userType' => $this->userType,
                'name' => $this->name,
                'parentGoal' => $this->parentGoal,
                'childAge' => $this->childAge,
                'creatorGoal' => $this->creatorGoal,
                'year' => date('Y'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
