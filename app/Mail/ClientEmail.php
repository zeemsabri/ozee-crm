<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth; // To get the authenticated user's details

class ClientEmail extends Mailable
{
    use Queueable, SerializesModels;

    public array $emailData;
    public string $senderName;
    public string $senderRole;
    public string $senderPhone;
    public string $senderWebsite;
    public string $companyLogoUrl;
    public string $brandPrimaryColor;
    public string $brandSecondaryColor;
    public string $textColorPrimary;
    public string $textColorSecondary;
    public string $borderColor;
    public string $backgroundColor;
    public string $emailTemplate;

    /**
     * Create a new message instance.
     */
    public function __construct(array $emailData, array $senderDetails, array $companyDetails, string $emailTemplate)
    {
        $this->emailData = $emailData;
        $this->emailTemplate = $emailTemplate;

        // Sender Details
        $this->senderName = $senderDetails['name'] ?? 'Sender Name';
        $this->senderRole = $senderDetails['role'] ?? 'Sender Role';

        // Company Details for Signature
        $this->senderPhone = $companyDetails['phone'];
        $this->senderWebsite = $companyDetails['website'];
        $this->companyLogoUrl = $companyDetails['logo_url'];
        $this->brandPrimaryColor = $companyDetails['brand_primary_color'];
        $this->brandSecondaryColor = $companyDetails['brand_secondary_color'];
        $this->textColorPrimary = $companyDetails['text_color_primary'];
        $this->textColorSecondary = $companyDetails['text_color_secondary'];
        $this->borderColor = $companyDetails['border_color'];
        $this->backgroundColor = $companyDetails['background_color'];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailData['subject'],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.' . $this->emailTemplate,
            with: [
                'bodyContent' => $this->emailData['body'],
                'greetingType' => $this->emailData['greeting_type'],
                'customGreetingName' => $this->emailData['custom_greeting_name'],
                'clientName' => $this->emailData['clientName'] ?? 'Valued Client', // Ensure clientName is passed
                // Pass all sender and company details for the signature
                'senderName' => $this->senderName,
                'senderRole' => $this->senderRole,
                'senderPhone' => $this->senderPhone,
                'senderWebsite' => $this->senderWebsite,
                'companyLogoUrl' => $this->companyLogoUrl,
                'brandPrimaryColor' => $this->brandPrimaryColor,
                'brandSecondaryColor' => $this->brandSecondaryColor,
                'textColorPrimary' => $this->textColorPrimary,
                'textColorSecondary' => $this->textColorSecondary,
                'borderColor' => $this->borderColor,
                'backgroundColor' => $this->backgroundColor,
                // Social media icon URLs (hardcoded as they are external and consistent)
                'facebookIconUrl' => 'https://img.icons8.com/ios-filled/20/' . substr($this->brandPrimaryColor, 1) . '/facebook-new.png',
                'twitterIconUrl' => 'https://img.icons8.com/ios-filled/20/' . substr($this->brandPrimaryColor, 1) . '/twitter.png',
                'linkedinIconUrl' => 'https://img.icons8.com/ios-filled/20/' . substr($this->brandPrimaryColor, 1) . '/linkedin.png',
                'instagramIconUrl' => 'https://img.icons8.com/ios-filled/20/' . substr($this->brandPrimaryColor, 1) . '/instagram-new.png',
            ]
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
