<?php

namespace App\Http\Controllers\Api;

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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use App\Http\Controllers\Api\Concerns\HandlesTemplatedEmails;

class SendEmailController extends Controller
{
    private MagicLinkService $magicLinkService;

    use HandlesTemplatedEmails;

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

        return $this->renderNewTemplatePreviewResponse(
            $validatedData['template_id'],
            $validatedData['client_id'],
            $validatedData['template_data'] ?? [],
            $project
        );
    }
}
