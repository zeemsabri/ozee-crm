<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\GenericTemplateMail;
use App\Models\EmailTemplate;
use App\Models\PlaceholderDefinition;
use App\Models\Client; // Assuming Client is a model
use App\Models\User; // Assuming User is a model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendEmailController extends Controller
{
    /**
     * Helper function to replace all placeholders in a string.
     * This will handle both dynamic and static placeholders.
     *
     * @param string $content
     * @param EmailTemplate $template
     * @param array $dynamicData
     * @param mixed $recipient
     * @return string
     */
    private function populateAllPlaceholders(string $content, EmailTemplate $template, array $dynamicData, $recipient): string
    {
        // Replace dynamic placeholders first from user input
        foreach ($dynamicData as $key => $value) {
            $content = str_replace("{{ {$key} }}", $value, $content);
        }

        // Replace static placeholders by fetching data from the database
        foreach ($template->placeholders as $placeholder) {
            if (!$placeholder->is_dynamic) {
                // Get the value from the specified model and attribute
                $value = $this->getPlaceholderValue($placeholder, $recipient);
                $content = str_replace("{{ {$placeholder->name} }}", $value, $content);
            }
        }

        return $content;
    }

    /**
     * Get the value for a static placeholder from its source model.
     *
     * @param PlaceholderDefinition $placeholder
     * @param mixed $recipient
     * @return string
     */
    private function getPlaceholderValue(PlaceholderDefinition $placeholder, $recipient): string
    {
        if (!$placeholder->source_model || !$placeholder->source_attribute) {
            return 'N/A'; // Or handle as an error
        }

        $modelClass = $placeholder->source_model;
        $attribute = $placeholder->source_attribute;

        // Check which model the placeholder refers to
        if ($modelClass === 'App\\Models\\Client' && $recipient instanceof Client) {
            return $recipient->{$attribute} ?? 'N/A';
        }

        if ($modelClass === 'App\\Models\\User') {
            // Find a way to get the current user. For now, we'll assume a hardcoded user or find the logged-in user.
            $user = User::first(); // Placeholder logic
            return $user->{$attribute} ?? 'N/A';
        }

        // Add more models here as needed (e.g., Task, Deliverable, Document)
        // You would need to pass these related models in the request or fetch them here
        // based on the context of the email.

        return 'N/A';
    }

    /**
     * Send an email based on a template and dynamic data.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEmail(Request $request)
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
                $subject = $this->populateAllPlaceholders($template->subject, $template, $dynamicData, $recipient);
                $bodyHtml = $this->populateAllPlaceholders($template->body_html, $template, $dynamicData, $recipient);

                $emailData = [
                    'subject' => $subject,
                    'bodyContent' => $bodyHtml,
                    'senderName' => 'Admin User', // Example: Replace with actual user name
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function preview(Request $request)
    {
        $validatedData = $request->validate([
            'template_id' => 'required|exists:email_templates,id',
            'recipient_id' => 'required|exists:clients,id',
            'dynamic_data' => 'nullable|array',
        ]);

        try {
            $template = EmailTemplate::with('placeholders')->findOrFail($validatedData['template_id']);
            $recipient = Client::findOrFail($validatedData['recipient_id']);
            $dynamicData = $validatedData['dynamic_data'] ?? [];

            $subject = $this->populateAllPlaceholders($template->subject, $template, $dynamicData, $recipient);
            $bodyHtml = $this->populateAllPlaceholders($template->body_html, $template, $dynamicData, $recipient);

            return response()->json([
                'subject' => $subject,
                'body_html' => $bodyHtml,
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate preview: ' . $e->getMessage()], 500);
        }
    }
}
