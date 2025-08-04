<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use App\Http\Controllers\Api\Concerns\HandlesTemplatedEmails;

class SendEmailController extends Controller
{
    private MagicLinkService $magicLinkService;

    use HandlesTemplatedEmails, HasProjectPermissions;

    public function __construct(MagicLinkService $magicLinkService)
    {
        $this->magicLinkService = $magicLinkService;
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
            'clients' => 'required|array',
            'clients.*' => 'exists:clients,id',
            'template_data' => 'nullable|array',
        ]);

        try {
            $template = EmailTemplate::with('placeholders')->findOrFail($validatedData['template_id']);
            $recipients = Client::whereIn('id', $validatedData['clients'])->get();
            $templateData = $validatedData['template_data'] ?? [];

            foreach ($recipients as $recipient) {
                // Populate the subject and body for the final send
                $subject = $this->populateAllPlaceholders($template->subject, $template, $templateData, $recipient, $project, true);
                $bodyHtml = $this->populateAllPlaceholders($template->body_html, $template, $templateData, $recipient, $project, true);

                $formattedBodyHtml = nl2br($bodyHtml);

                $emailData = [
                    'subject' => $subject,
                    'bodyContent' => $formattedBodyHtml,
                    'senderName' => Auth::user()?->name,
                ];

                // Mail::to($recipient->email)->send(new GenericTemplateMail($emailData));
            }

            return response()->json(['message' => 'Emails sent successfully.'], 200);

        } catch (\Exception $e) {
            Log::error('Failed to send email: ' . $e->getMessage());
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
            'client_id' => 'required|exists:clients,id',
            'template_data' => 'nullable|array',
        ]);

        try {
            $template = EmailTemplate::with('placeholders')->findOrFail($validatedData['template_id']);
            $recipientClient = Client::findOrFail($validatedData['client_id']);
            $templateData = $validatedData['template_data'] ?? [];

            // Populate placeholders for the subject and body
            $subject = $this->populateAllPlaceholders(
                $template->subject,
                $template,
                $templateData,
                $recipientClient,
                $project,
                false // Not a final send, so generate preview links
            );

            $bodyHtml = $this->populateAllPlaceholders(
                $template->body_html,
                $template,
                $templateData,
                $recipientClient,
                $project,
                false // Not a final send
            );

            $bodyHtml = nl2br($bodyHtml);

            $sender = Auth::user();
            $senderDetails = [
                'name' => $sender?->name ?? 'Staff',
                'role' => $this->getProjectRoleName($sender, $project) ?? 'Staff',
            ];

            // Load all reusable data from the branding config file
            $config = config('branding');

            // Combine all data into a single array for the view
            $data = [
                'emailData' => [
                    'subject' => $subject,
                ],
                'bodyContent' => $bodyHtml, // The populated body content
                'senderName' => $senderDetails['name'],
                'senderRole' => $senderDetails['role'],
                'senderPhone' => $config['company']['phone'],
                'senderWebsite' => $config['company']['website'],
                'signatureTagline' => $config['signature']['tagline'],
                'companyLogoUrl' => asset($config['company']['logo_url']),
                'socialIcons' => $config['social_icons'],
                'brandPrimaryColor' => $config['branding']['brand_primary_color'],
                'brandSecondaryColor' => $config['branding']['brand_secondary_color'],
                'backgroundColor' => $config['branding']['background_color'],
                'textColorPrimary' => $config['branding']['text_color_primary'],
                'textColorSecondary' => $config['branding']['text_color_secondary'],
                'borderColor' => $config['branding']['border_color'],
                'reviewLink' => 'https://www.example.com/review', // Example review link
            ];

            // Use the single, consolidated template for the preview
            $fullHtml = View::make('emails.email_template', $data)->render();

            return response()->json([
                'subject' => $subject,
                'body_html' => $fullHtml,
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating email preview: ' . $e->getMessage(), [
                'template_id' => $validatedData['template_id'],
                'error' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error generating email preview: ' . $e->getMessage()], 500);
        }
    }
}
