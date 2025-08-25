<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class FamifyContactMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The subject line for the email.
     */
    public string $subjectText;

    /**
     * Primary known fields (may be absent if not provided).
     */
    public ?string $name;
    public ?string $email;
    public ?string $phone;
    public ?string $company;
    public ?string $messageBody;

    /**
     * All submitted fields (key => value string), already sanitized.
     * @var array<int, array{key:string,value:string}>
     */
    public array $allFields;

    /**
     * Create a new message instance.
     *
     * @param string $subjectText
     * @param array<string,mixed> $data  Validated/known data from request
     * @param array<int, array{key:string,value:string}> $allFields  All fields to display
     */
    public function __construct(string $subjectText, array $data, array $allFields)
    {
        $this->subjectText = $subjectText;
        $this->name = $data['name'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->company = $data['company'] ?? null;
        $this->messageBody = $data['message'] ?? null;
        $this->allFields = $allFields;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(env('FAMIFY_MAIL_FROM_ADDRESS', 'hello@famifyhub.com.au'), env('FAMIFY_MAIL_FROM_NAME', 'Famify Hub')),
            subject: $this->subjectText,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.famify-contact',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'company' => $this->company,
                'messageBody' => $this->messageBody,
                'allFields' => $this->allFields,
                'submittedAt' => now()->toDateTimeString(),
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
