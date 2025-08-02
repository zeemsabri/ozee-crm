<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GenericTemplateMail;
use App\Models\EmailTemplate;
use App\Models\PlaceholderDefinition;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use App\Models\Project;
use App\Notifications\TaskAssigned;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class SendEmailController extends Controller
{
    private MagicLinkService $magicLinkService;

    public function __construct(MagicLinkService $magicLinkService)
    {
        $this->magicLinkService = $magicLinkService;
    }

    /**
     * Helper function to replace all placeholders in a string.
     *
     * @param string $content
     * @param EmailTemplate $template
     * @param array $dynamicData
     * @param mixed $recipient
     * @param Project $project
     * @param bool $isFinalSend
     * @return string
     */
    private function populateAllPlaceholders(string $content, EmailTemplate $template, array $dynamicData, $recipient, Project $project, bool $isFinalSend): string
    {

        $replacements = [];
        $placeholders = $template->placeholders->keyBy('name');

        foreach ($dynamicData as $key => $value) {
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

        foreach ($placeholders as $placeholder) {
            $placeholderTag = "{{ {$placeholder->name} }}";
            if (!isset($replacements[$placeholderTag])) {
                $replacements[$placeholderTag] = $this->getPlaceholderValue($placeholder, $recipient, $project, $isFinalSend);
            }
        }

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
    private function getPlaceholderValue(PlaceholderDefinition $placeholder, $recipient, Project $project, bool $isFinalSend): string
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

        if ($modelClass === 'App\\Models\\MagicLink' && $placeholder->name === 'Magic Link') {
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
    private function getMagicLinkUrl(string $email, int $projectId, bool $isFinalSend): string
    {
        if ($isFinalSend) {
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

        // For preview, return a placeholder URL to prevent database entries
        return '#preview_magic_link_url';
    }

    /**
     * Send an email based on a template and dynamic data.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEmail(Request $request, Project $project)
    {
        $validatedData = $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'recipients' => 'required|array',
            'recipients.*' => 'exists:clients,id',
            'dynamic_data' => 'nullable|array',
        ]);

        try {
            $template = EmailTemplate::with('placeholders')->findOrFail($validatedData['template_id']);
            $recipients = Client::whereIn('id', $validatedData['recipients'])->get();
            $dynamicData = $validatedData['dynamic_data'] ?? [];

            foreach ($recipients as $recipient) {
                $subject = $this->populateAllPlaceholders($template->subject, $template, $dynamicData, $recipient, $project, true);
                $bodyHtml = $this->populateAllPlaceholders($template->body_html, $template, $dynamicData, $recipient, $project, true);

                $formattedBodyHtml = nl2br($bodyHtml);

                $emailData = [
                    'subject' => $subject,
                    'bodyContent' => $formattedBodyHtml,
                    'senderName' => Auth::user()?->name,
                ];

                Mail::to($recipient->email)->send(new GenericTemplateMail($emailData));
            }

            return response()->json(['message' => 'Emails sent successfully.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Preview an email based on a template and dynamic data.
     *
     * @param Request $request
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function preview(Request $request, Project $project)
    {
        $validatedData = $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'recipient_id' => 'required|exists:clients,id',
            'dynamic_data' => 'nullable|array',
        ]);

        try {
            $template = EmailTemplate::with('placeholders')->findOrFail($validatedData['template_id']);
            $recipient = Client::findOrFail($validatedData['recipient_id']);
            $dynamicData = $request->input('dynamic_data', []);

            $subject = $this->populateAllPlaceholders($template->subject, $template, $dynamicData, $recipient, $project, false);
            $bodyHtml = $this->populateAllPlaceholders($template->body_html, $template, $dynamicData, $recipient, $project, false);

            $formattedBodyHtml = nl2br($bodyHtml);
            $user = Auth::user();
            $data = [
                'emailData' => [
                    'subject' => $subject,
                ],
                'bodyContent' => $formattedBodyHtml,
                'senderName' => $user?->name,
                'senderRole' => $user?->getProjectRoleName($project),
                'senderPhone' => '+61 456 639 389',
                'senderWebsite' => 'ozeeweb.com.au',
                'socialIcons' => [
                    ['name' => 'Facebook', 'url' => 'https://www.facebook.com/ozeeweb.and.digital/', 'iconUrl' => 'https://placehold.co/24x24/3b5998/ffffff?text=f'],
                    ['name' => 'Twitter', 'url' => 'https://x.com/OZee_Web', 'iconUrl' => 'https://placehold.co/24x24/1da1f2/ffffff?text=t'],
                    ['name' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/ozee-web', 'iconUrl' => 'https://placehold.co/24x24/0077b5/ffffff?text=in'],
                    ['name' => 'Instagram', 'url' => 'https://www.instagram.com/ozee_web_and_digital/', 'iconUrl' => 'https://placehold.co/24x24/833ab4/ffffff?text=ig'],
                ],
                'companyLogoUrl' => '/logo.png',
//                'reviewLink' => '#',
                'brandPrimaryColor' => '#5d50c6',
                'backgroundColor' => '#f4f4f4',
            ];

            $fullHtml = View::make('emails.email_template', $data)->render();

            return response()->json([
                'subject' => $subject,
                'body_html' => $fullHtml,
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate preview: ' . $e->getMessage()], 500);
        }
    }
}
