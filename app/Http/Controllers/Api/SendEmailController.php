<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesTemplatedEmails;
use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Project;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SendEmailController extends Controller
{
    private MagicLinkService $magicLinkService;

    use HandlesTemplatedEmails, HasProjectPermissions;

    public function __construct(MagicLinkService $magicLinkService)
    {
        $this->magicLinkService = $magicLinkService;
    }

    /**
     * Preview an email based on a template and dynamic data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function preview(Request $request, Project $project)
    {
        $validatedData = $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'client_id' => 'required|exists:clients,id',
            'template_data' => 'nullable|array',
        ]);
        //
        //        try {
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

        $data = $this->getData($subject, $bodyHtml, $senderDetails);

        $fullHtml = $this->renderHtmlTemplate($data);

        return response()->json([
            'subject' => $subject,
            'body_html' => $fullHtml,
        ]);

        //        } catch (\Exception $e) {
        //            Log::error('Error generating email preview: ' . $e->getMessage(), [
        //                'template_id' => $validatedData['template_id'],
        //                'error' => $e->getTraceAsString(),
        //            ]);
        //            return response()->json(['message' => 'Error generating email preview: ' . $e->getMessage()], 500);
        //        }
    }
}
