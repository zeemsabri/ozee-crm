<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Email;
use App\Models\Lead;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

trait HandlesAiTemplatedEmails
{
    /**
     * Renders the subject and body for an AI-generated email.
     *
     * @param Email $email
     * @param bool $isFinalSend
     * @return array With keys 'subject' and 'body'.
     * @throws Exception
     */
    public function renderAiEmailContent(Email $email, bool $isFinalSend = false): array
    {
        // The entire AI response is stored in template_data for logging/future use.
        $fullAiResponse = json_decode($email->template_data, true) ?? [];

        // The structured content for the body is stored in the 'body' column.
        $structuredBody = json_decode($email->body, true) ?? [];

        if (empty($structuredBody) || !isset($fullAiResponse['subject'])) {
            throw new Exception('AI response data is missing or malformed for email ID: ' . $email->id);
        }

        $recipient = $email->conversation->conversable;
        if (!($recipient instanceof Lead)) {
            throw new Exception('Recipient for AI email is not a Lead. Email ID: ' . $email->id);
        }

        $senderDetails = $this->getSenderDetails($email);

        // This data array will be passed to the Blade template
        $viewData = [
            'greeting' => $structuredBody['greeting'] ?? 'Hello,',
            'paragraphs' => $structuredBody['paragraphs'] ?? [],
            'call_to_action' => $structuredBody['call_to_action'] ?? null,
            'senderName' => $senderDetails['name'],
            'senderRole' => $senderDetails['role'],
            // Dynamically load all branding details from the config file
            ...$this->getBrandingData($email, $isFinalSend)
        ];

        $renderedBody = View::make('emails.ai_lead_outreach_template', $viewData)->render();

        return [
            'subject' => $fullAiResponse['subject'],
            'body' => $renderedBody,
        ];
    }

    /**
     * Helper to get sender details (assuming this method exists from HandlesTemplatedEmails).
     */
    abstract protected function getSenderDetails(Email $email);

    /**
     * Helper to get branding data from the config file.
     */
    private function getBrandingData(Email $email, bool $isFinalSend): array
    {
        $config = config('branding');
        $data = [
            'senderPhone' => $config['company']['phone'],
            'senderWebsite' => $config['company']['website'],
            'companyLogoUrl' => asset($config['company']['logo_url']),
            'socialIcons' => $config['social_icons'],
            'brandPrimaryColor' => $config['branding']['brand_primary_color'],
            'brandSecondaryColor' => $config['branding']['brand_secondary_color'],
            'backgroundColor' => $config['branding']['background_color'],
            'textColorPrimary' => $config['branding']['text_color_primary'],
            'textColorSecondary' => $config['branding']['text_color_secondary'],
            'borderColor' => $config['branding']['border_color'],
            'reviewLink' => $config['reviewLink'],
            'companyAddress' => $config['company']['address'],
            'signatureTagline' => $config['signature']['tagline'],
        ];

        if ($isFinalSend) {
            $data['emailTrackingUrl'] = route('email.track', ['id' => $email->id]);
        }

        return $data;
    }
}

