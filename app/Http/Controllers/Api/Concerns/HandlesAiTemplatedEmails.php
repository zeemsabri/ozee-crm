<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Email;
use Illuminate\Support\Facades\View;
use Exception;

trait HandlesAiTemplatedEmails
{
    /**
     * Renders the subject and body for an AI-generated email.
     *
     * @param Email $email
     * @return array ['subject' => string, 'body' => string, 'dataForView' => array]
     * @throws Exception
     */
    public function renderAiEmailContent(Email $email): array
    {
        $templateData = json_decode($email->template_data, true) ?? [];

        if (!isset($templateData['ai_content'])) {
            throw new Exception('Email is not a valid AI-generated email. Missing ai_content key.');
        }

        $subject = $templateData['subject'] ?? 'A message from OZee Web & Digital';
        $aiContent = $templateData['ai_content'];
        $sender = $email->sender; // The user who initiated the process

        $dataForView = [
            'subject' => $subject,
            'ai_content' => $aiContent,
            'senderName' => $sender->name ?? config('branding.company.name'),
            'senderRole' => 'The Team', // Or derive this as needed
            'emailTrackingUrl' => route('email.track', ['id' => $email->id]),
        ];

        $renderedHtmlBody = View::make('emails.ai_lead_outreach_template', $dataForView)->render();

        return [
            'subject' => $subject,
            'body' => $renderedHtmlBody,
            'dataForView' => $dataForView,
        ];
    }
}
