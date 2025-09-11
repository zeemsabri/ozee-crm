<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\PlaceholderDefinition;
use App\Models\Project;
use App\Models\Email;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Exception;

trait HandlesTemplatedEmails
{
    /**
     * Helper function to replace all placeholders in a string.
     *
     * @param string $content
     * @param EmailTemplate $template
     * @param array $templateData
     * @param mixed $recipient
     * @param Project $project
     * @param bool $isFinalSend
     * @return string
     */
    protected function populateAllPlaceholders(string $content, EmailTemplate $template, array $templateData, $recipient, Project $project, bool $isFinalSend): string
    {
        $replacements = [];
        $placeholders = $template->placeholders->keyBy('name');

        // Process dynamic data from the form
        foreach ($templateData as $key => $value) {
            $placeholder = $placeholders->get($key);
            if ($placeholder) {
                $placeholderTag = "{{ {$key} }}";
                $replacementValue = '';

                if ($placeholder->is_dynamic) {
                    $stringValue = is_array($value) ? '' : (string)($value ?? '');
                    if ($placeholder->is_link) {
                        $replacementValue = '<a href="' . e($stringValue) . '">' . e($key) . '</a>';
                    } else {
                        $replacementValue = $stringValue;
                    }
                } elseif ($placeholder->is_repeatable) {
                    if (is_array($value) && !empty($value) && $placeholder->source_model && $placeholder->source_attribute) {
                        $modelClass = $placeholder->source_model;
                        $attribute = $placeholder->source_attribute;
                        $items = $modelClass::whereIn('id', $value)->get();
                        $listHtml = '<ul>';
                        foreach ($items as $item) {
                            $listHtml .= '<li>' . ($item->{$attribute} ?? 'N/A') . '</li>';
                        }
                        $listHtml .= '</ul>';
                        $replacementValue = $listHtml;
                    }
                } elseif ($placeholder->is_selectable) {
                    if ($value && $placeholder->source_model && $placeholder->source_attribute) {
                        $modelClass = $placeholder->source_model;
                        $attribute = $placeholder->source_attribute;
                        $item = $modelClass::find($value);
                        $replacementValue = $item->{$attribute} ?? 'N/A';
                    }
                }

                $replacements[$placeholderTag] = $replacementValue;
            }
        }

        // Process static placeholders from the template
        foreach ($placeholders as $placeholder) {
            $placeholderTag = "{{ {$placeholder->name} }}";
            if (!isset($replacements[$placeholderTag]) || $replacements[$placeholderTag] === '') {
                $replacements[$placeholderTag] = $this->getPlaceholderValue($placeholder, $recipient, $project, $isFinalSend);
            }
        }

        // Handle magic link button as a special case
        if (Str::contains($content, '{{ Magic Link Button }}')) {
            $magicLinkUrl = $this->getMagicLinkUrl($recipient->email, $project->id, $isFinalSend);
            $buttonHtml = '<a href="' . e($magicLinkUrl) . '" style="background-color:#5d50c6;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;font-size:16px;display:inline-block;box-shadow:0 4px 8px rgba(0,0,0,0.1);">Client Portal</a>';
            $content = str_replace('{{ Magic Link Button }}', $buttonHtml, $content);
        }

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return $content;
    }

    /**
     * Get the value for a static placeholder from its source model.
     *
     * @param PlaceholderDefinition $placeholder
     * @param mixed $recipient
     * @param Project $project
     * @param bool $isFinalSend
     * @return string
     */
    protected function getPlaceholderValue(PlaceholderDefinition $placeholder, $recipient, Project|null $project, bool $isFinalSend): string
    {
        if (!$placeholder->source_model || !$placeholder->source_attribute) {
            return 'N/A';
        }

        $modelClass = $placeholder->source_model;
        $attribute = $placeholder->source_attribute;

        if ($modelClass === 'App\\Models\\Client' && $recipient instanceof Client) {
            return $recipient->{$attribute} ?? 'N/A';
        }

        if ($modelClass === 'App\\Models\\User') {
            $user = Auth::user();
            if ($user) {
                return $user->{$attribute} ?? 'N/A';
            }
        }

        if ($modelClass === 'App\\Models\\Project') {
            return $project->{$attribute} ?? 'N/A';
        }

        if ($modelClass === 'App\\Models\\MagicLink' && $placeholder->name === 'Magic Link' && $project) {
            $magicLinkUrl = $this->getMagicLinkUrl($recipient->email, $project->id, $isFinalSend);
            return '<a href="' . e($magicLinkUrl) . '">Client Portal</a>';
        }

        return 'N/A';
    }

    /**
     * Get or generate a magic link URL based on the context.
     *
     * @param string $email
     * @param int $projectId
     * @param bool $isFinalSend
     * @return string
     */
    protected function getMagicLinkUrl(string $email, int $projectId, bool $isFinalSend): string
    {
        // For preview, return a placeholder URL to prevent database entries
        if (!$isFinalSend) {
            return '#preview_magic_link_url';
        }

        // Check for an existing, non-expired magic link to reuse it.
        $existingLink = $this->magicLinkService->getValidMagicLink($email, $projectId);
        if ($existingLink) {
            return URL::temporarySignedRoute(
                'client.magic-link-login',
                $existingLink->expires_at,
                ['token' => $existingLink->token]
            );
        }
        // If no valid link exists, generate a new one for the final send.
        return $this->magicLinkService->generateMagicLink($email, $projectId);
    }

    /**
     * Renders the subject and body for an email.
     *
     * @param Email $email
     * @param bool $isFinalSend
     * @return array
     * @throws Exception
     */
    public function renderEmailContent(Email $email, bool $isFinalSend = false)
    {
        if ($email->template_id) {
            $recipientClient = $email->conversation->client ?? $email->conversation->conversable;
            if (!$recipientClient) {
                throw new Exception('Recipient client not found for email ID: ' . $email->id);
            }

            $template = EmailTemplate::with('placeholders')->findOrFail($email->template_id);
            $templateData = json_decode($email->template_data, true) ?? [];

            $subject = $this->populateAllPlaceholders(
                $template->subject,
                $template,
                $templateData,
                $recipientClient,
                $email->conversation?->project,
                $isFinalSend
            );
            $body = $this->populateAllPlaceholders(
                $template->body_html,
                $template,
                $templateData,
                $recipientClient,
                $email->conversation->project,
                $isFinalSend
            );

        } else {
            $subject = $email->subject;
            $body = $email->body;
        }

        $body = nl2br($body);

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Renders a full email preview with the correct template and returns a JSON response for a saved email.
     *
     * @param Email $email
     * @return \Illuminate\Http\JsonResponse
     * @throws Exception
     */
    public function renderFullEmailPreviewResponse(Email $email)
    {

        try {
            // Render the email content (subject and body) first
            $renderedContent = $this->renderEmailContent($email, false);
            $subject = $renderedContent['subject'];
            $body = $renderedContent['body'];

            // Get the sender details
            $senderDetails = $this->getSenderDetails($email);

            // Combine all data into a single array for the view
            $data = $this->getData($subject, $body, $senderDetails);

            $fullHtml = $this->renderHtmlTemplate($data);

            return response()->json([
                'id'    =>  $email->id,
                'subject' => $subject,
                'body_html' => $fullHtml,
                'status'    =>  $email->status
            ]);

        } catch (Exception $e) {
            Log::error('Error rendering full email preview: ' . $e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error generating email view: ' . $e->getMessage()], 500);
        }
    }

    public function renderHtmlTemplate($data, $template = 'email_template')
    {
        try {
            return  View::make('emails.' . $template, $data)->render();
        }
        catch (Exception $e) {

        }

        try {
            return View::make('emails.ai_lead_outreach_template', $data)->render();
        }
        catch (Exception $e) {
            return $e->getMessage();
        }

    }

    public function getCustomEmailReadyForSending(Email $email)
    {
        try {
            // Render the email content (subject and body) first
            $renderedContent = $this->renderEmailContent($email, true);
            $subject = $renderedContent['subject'];
            $body = $renderedContent['body'];
            // Get the sender details

            $senderDetails = $this->getSenderDetails($email);

            // Combine all data into a single array for the view
            $data = $this->getData($subject, $body, $senderDetails);

            // Use only one template for all emails
            $fullHtml = $this->renderHtmlTemplate($data);

            return response()->json([
                'subject' => $subject,
                'body_html' => $fullHtml,
            ]);

        } catch (Exception $e) {
            Log::error('Error rendering full email preview: ' . $e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error generating email view: ' . $e->getMessage()], 500);
        }
    }

    public function getSenderDetails(Email $email)
    {
        $sender = $email->sender;

        if(get_class($email->conversation->conversable) === Lead::class) {
            return [
                'name' => $sender->name ?? 'Original Sender',
                'role'  =>  null
            ];
        }

        return [
            'name' => $sender->name ?? 'Original Sender',
            'role' => $this->getProjectRoleName($sender, $email->conversation->project) ?? 'Staff',
        ];
    }

    public function getData($subject, $body, $senderDetails, $email = null, $isFinalSend = false)
    {
        // Load all reusable data from the new config file
        $config = config('branding');

        $data =  [
            'emailData' => [
                'subject' => $subject,
            ],
            'bodyContent' => json_decode($body) ? json_decode($body) : $body,
            'senderName' => $senderDetails['name'],
            'senderRole' => $senderDetails['role'],
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
            'reviewLink' => null,
            'template'  =>  $email ? $email->template : Email::TEMPLATE_DEFAULT,
        ];

        // Add the tracking URL only for final sends
        if ($isFinalSend && $email) {
            $data['emailTrackingUrl'] = route('email.track', ['id' => $email->id]);
        }

        return $data;
    }
}
