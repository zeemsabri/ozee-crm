<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\EmailType;
use App\Models\Client;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\PlaceholderDefinition;
use App\Models\Project;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

trait HandlesTemplatedEmails
{
    /**
     * Helper function to replace all placeholders in a string.
     *
     * @param  mixed  $recipient
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

                $isDynamic = (bool) $placeholder->is_dynamic;
                $isRepeatable = (bool) $placeholder->is_repeatable;
                $isSelectable = (bool) $placeholder->is_selectable;
                $isLink = (bool) $placeholder->is_link;

                // Helper: parse (Label)[URL]
                $parseLink = function ($str) {
                    if (! is_string($str)) {
                        return null;
                    }
                    if (preg_match('/^\((.*?)\)\[(.*?)\]$/', $str, $m)) {
                        return ['label' => $m[1], 'url' => $m[2]];
                    }

                    return null;
                };
                // Helper: linkify inline (Label)[URL] occurrences within any text
                $linkifyInline = function ($text) {
                    if (! is_string($text) || $text === '') {
                        return '';
                    }
                    $pattern = '/\((.*?)\)\[(.*?)\]/';
                    $result = '';
                    $offset = 0;
                    while (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset)) {
                        $start = $m[0][1];
                        $len = strlen($m[0][0]);
                        $before = substr($text, $offset, $start - $offset);
                        $result .= e($before);
                        $label = $m[1][0] ?? '';
                        $url = $m[2][0] ?? '';
                        if ($url !== '') {
                            $result .= '<a href="'.e($url).'">'.e($label ?: $url).'</a>';
                        } else {
                            // If URL empty, just render the original text escaped
                            $result .= e($m[0][0]);
                        }
                        $offset = $start + $len;
                    }
                    $result .= e(substr($text, $offset));

                    return $result;
                };

                if ($isRepeatable && $isDynamic) {
                    // New: repeatable dynamic values (array of strings), mixed text and links
                    if (is_array($value) && ! empty($value)) {
                        $html = '';
                        foreach ($value as $item) {
                            $link = $parseLink($item);
                            if ($link && ! empty($link['url'])) {
                                $label = e($link['label'] ?: $link['url']);
                                $url = e($link['url']);
                                $html .= '<p><a href="'.$url.'">'.$label.'</a></p>';
                            } else {
                                $html .= '<p>'.$linkifyInline((string) $item).'</p>';
                            }
                        }
                        $replacementValue = $html;
                    }
                } elseif ($isRepeatable) {
                    // Existing: repeatable from source model (IDs)
                    if (is_array($value) && ! empty($value) && $placeholder->source_model && $placeholder->source_attribute) {
                        $modelClass = $placeholder->source_model;
                        $attribute = $placeholder->source_attribute;
                        $items = $modelClass::whereIn('id', $value)->get();
                        $listHtml = '<ul>';
                        foreach ($items as $item) {
                            $listHtml .= '<li>'.e($item->{$attribute} ?? 'N/A').'</li>';
                        }
                        $listHtml .= '</ul>';
                        $replacementValue = $listHtml;
                    }
                } elseif ($isDynamic) {
                    // Single dynamic value (string); may be link syntax
                    $stringValue = is_array($value) ? '' : (string) ($value ?? '');
                    $link = $parseLink($stringValue);
                    if ($link && ! empty($link['url'])) {
                        $label = e($link['label'] ?: $link['url']);
                        $url = e($link['url']);
                        $replacementValue = '<a href="'.$url.'">'.$label.'</a>';
                    } else {
                        // Also linkify inline patterns within the text
                        $replacementValue = $linkifyInline($stringValue);
                    }
                } elseif ($isSelectable) {
                    if ($value && $placeholder->source_model && $placeholder->source_attribute) {
                        $modelClass = $placeholder->source_model;
                        $attribute = $placeholder->source_attribute;
                        $item = $modelClass::find($value);
                        $replacementValue = e($item->{$attribute} ?? 'N/A');
                    }
                }

                $replacements[$placeholderTag] = $replacementValue;
            }
        }

        // Process static placeholders from the template
        foreach ($placeholders as $placeholder) {
            $placeholderTag = "{{ {$placeholder->name} }}";
            if (! isset($replacements[$placeholderTag]) || $replacements[$placeholderTag] === '') {
                $replacements[$placeholderTag] = $this->getPlaceholderValue($placeholder, $recipient, $project, $isFinalSend);
            }
        }

        // Handle magic link button as a special case
        if (Str::contains($content, '{{ Magic Link Button }}')) {
            $magicLinkUrl = $this->getMagicLinkUrl($recipient->email, $project->id, $isFinalSend);
            $buttonHtml = '<a href="'.e($magicLinkUrl).'" style="background-color:#5d50c6;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;font-size:16px;display:inline-block;box-shadow:0 4px 8px rgba(0,0,0,0.1);">Client Portal</a>';
            $content = str_replace('{{ Magic Link Button }}', $buttonHtml, $content);
        }

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return $content;
    }

    /**
     * Get the value for a static placeholder from its source model.
     *
     * @param  mixed  $recipient
     */
    protected function getPlaceholderValue(PlaceholderDefinition $placeholder, $recipient, ?Project $project, bool $isFinalSend): string
    {
        // Heuristic fallbacks when no source model/attribute defined
        if (! $placeholder->source_model || ! $placeholder->source_attribute) {
            $name = strtolower(trim($placeholder->name));
            if ($recipient && ($name === 'client name' || $name === 'client_name' || $name === 'client')) {
                return $recipient->name ?? 'N/A';
            }
            if ($project && ($name === 'project name' || $name === 'project_name' || $name === 'project')) {
                return $project->name ?? 'N/A';
            }

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

            return '<a href="'.e($magicLinkUrl).'">Client Portal</a>';
        }

        return 'N/A';
    }

    /**
     * Get or generate a magic link URL based on the context.
     */
    protected function getMagicLinkUrl(string $email, int $projectId, bool $isFinalSend): string
    {
        // For preview, return a placeholder URL to prevent database entries
        if (! $isFinalSend) {
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
     * @return array
     *
     * @throws Exception
     */
    /**
     * Read `template_data` whichever way it is stored.
     *
     * Delegates to App\Support\TemplateData so the trait, the plain readers in
     * EmailController and the AI trait all share one implementation — a trait cannot be
     * called statically from a class that does not compose it.
     */
    public static function decodeTemplateData($value): array
    {
        return \App\Support\TemplateData::decode($value);
    }

    /**
     * @param  bool  $nl2br  Convert newlines to <br>. Default true, which is what every
     *                       send path has always done and must keep doing — a custom email
     *                       is composed in a textarea and its line breaks only survive
     *                       because of this. Display code passes false and decides for
     *                       itself: running nl2br over a TEMPLATE's own HTML inserts a
     *                       <br> after every newline in its source, which is where the
     *                       stray double-spacing in templated emails comes from. Fixing
     *                       that here would change what clients receive, so it is opt-in
     *                       and the inbox opts in on its own. See EmailHtml.
     */
    public function renderEmailContent(Email $email, bool $isFinalSend = false, bool $nl2br = true)
    {
        if ($email->template_id) {
            $recipientClient = $email->conversation->client ?? $email->conversation->conversable;
            if (! $recipientClient) {
                throw new Exception('Recipient client not found for email ID: '.$email->id);
            }

            // Combine names for multiple recipients if applicable
            $toEmails = is_array($email->to) ? $email->to : (empty($email->to) ? [] : [$email->to]);
            if (count($toEmails) > 1) {
                $clients = Client::whereIn('email', $toEmails)->get();
                if ($clients->count() > 1) {
                    $combinedName = $clients->pluck('name')->join(' & ');
                    $recipientClient = clone $recipientClient;
                    $recipientClient->name = $combinedName;
                }
            }

            $template = EmailTemplate::with('placeholders')->findOrFail($email->template_id);
            $templateData = self::decodeTemplateData($email->template_data);

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

            /*
             * A body the redesigned composer stored as markdown (flagged at creation —
             * see MarkdownBody for why the flag, never sniffing). Rendering here covers
             * every path in one place: the real send (EmailProcessingService via
             * ProcessDraftEmailJob calls this with $isFinalSend = true), edit-and-
             * approve, previews and "see it as the client does". nl2br must NOT run on
             * top — the renderer owns line breaks, and nl2br over its output would
             * double-space it exactly the way it double-spaces templates.
             */
            if (\App\Services\Inbox\MarkdownBody::isMarkdown($email)) {
                return ['subject' => $subject, 'body' => \App\Services\Inbox\MarkdownBody::render($body)];
            }
        }

        if ($nl2br) {
            $body = nl2br($body);
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Renders a full email preview with the correct template and returns a JSON response for a saved email.
     *
     * @return \Illuminate\Http\JsonResponse
     *
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
            $data = $this->getData($subject, $body, $senderDetails, $email, false);

            // Pick the correct blade template based on saved email_template or fallback
            $template = $email->email_template ?: Email::TEMPLATE_DEFAULT;
            $fullHtml = $this->renderHtmlTemplate($data, $template);

            $conversation = $email->conversation()->with(['project', 'conversable'])->first();
            $availableProjects = [];
            if ($conversation && $conversation->conversable instanceof Client) {
                $availableProjects = $conversation->conversable->projects()->get(['projects.id', 'projects.name']);
            }

            return response()->json([
                'id' => $email->id,
                'subject' => $subject,
                'body_html' => $fullHtml,
                'status' => $email->status,
                'contexts' => $email->contexts()->get(['id', 'summary', 'meta_data']),
                'conversation' => $conversation,
                'available_projects' => $availableProjects,
                'rejection_reason' => $email->rejection_reason,
                'type' => $email->type,
                'is_private' => $email->is_private,
            ]);

        } catch (Exception $e) {
            Log::error('Error rendering full email preview: '.$e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Error generating email view: '.$e->getMessage()], 500);
        }
    }

    public function renderHtmlTemplate($data, $template = 'email_template')
    {

        try {
            return View::make('emails.'.$template, $data)->render();
        } catch (Exception $e) {

        }

        try {
            return View::make('emails.ai_lead_outreach_template', $data)->render();
        } catch (Exception $e) {
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
            $data = $this->getData($subject, $body, $senderDetails, $email, true);

            // Use saved blade template when available
            $template = $email->email_template ?: Email::TEMPLATE_DEFAULT;
            $fullHtml = $this->renderHtmlTemplate($data, $template);

            return response()->json([
                'subject' => $subject,
                'body_html' => $fullHtml,
            ]);

        } catch (Exception $e) {
            Log::error('Error rendering full email preview: '.$e->getMessage(), [
                'email_id' => $email->id,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Error generating email view: '.$e->getMessage()], 500);
        }
    }

    public function getSenderDetails(Email $email)
    {

        $sender = $email->sender;

        if ($email->type === EmailType::Received) {
            return [
                'name' => '',
                'role' => '',
            ];
        }

        if ($sender && ($email->conversation?->conversable_type === \App\Models\Lead::class || ($email->conversation?->conversable && get_class($email->conversation->conversable) === \App\Models\Lead::class))) {
            return [
                'name' => $sender->name ?? 'Original Sender',
                'role' => null,
            ];
        }

        $project = $email->conversation?->project;
        $roleName = $project ? ($this->getProjectRoleName($sender, $project) ?? 'Staff') : 'Staff';

        return [
            'name' => $sender?->name ?? 'Original Sender',
            'role' => $roleName,
        ];
    }

    public function getData($subject, $body, $senderDetails, $email = null, $isFinalSend = false, $project = null)
    {
        // Load all reusable data from the new config file
        $config = config('branding');

        $body = json_decode($body) ? json_decode($body) : $body;
        $showSignatures = ! ((! $isFinalSend && isset($body->paragraphs)));

        $data = [
            'emailData' => [
                'subject' => $subject,
            ],
            'projectName' => $project?->name ?? $email?->conversation?->project?->name,
            'bodyContent' => $body,
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
            'template' => $email ? $email->template : Email::TEMPLATE_DEFAULT,
            'show_signature' => true,
        ];

        if ($email?->type === EmailType::Received) {
            $data['show_signature'] = false;
        }

        // Add the tracking URL only for final sends
        if ($isFinalSend && $email) {
            $data['emailTrackingUrl'] = route('email.track', ['id' => $email->id]);
        }

        return $data;
    }
}
