<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\Client;
use App\Models\Project;
use App\Models\PlaceholderDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log; // Added for error logging

class EmailPreviewController extends Controller
{
    /**
     * Renders a specific email template with sample data for preview.
     *
     * @param string $slug The slug of the email template to preview.
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\Response
     */
    public function preview($slug = 'deliverables-for-approval')
    {
        // Fetch the template by slug
        $template = EmailTemplate::where('slug', $slug)->first();

        if (!$template) {
            return response('Email template not found.', 404);
        }

        // --- Generate Sample Data ---
        // This is a crucial step for testing your templates.
        // You would typically get this data from your application's logic.
        $client = Client::first();
        $project = Project::first();

        // Check if sample data exists, otherwise provide defaults
        if (!$client) {
            $client = (object)['name' => 'John Doe'];
        }
        if (!$project) {
            $project = (object)['name' => 'Sample Project'];
        }

        // --- Load reusable branding information from the config file ---
        $brandingConfig = config('branding');
        if (is_null($brandingConfig)) {
            // Log an error if the branding config file is missing
            Log::error('Branding config file not found.');
            return response('Branding configuration missing.', 500);
        }

        // --- Hardcoded sample data for placeholder replacement logic ---
        // These are specific to the template and are not part of the reusable branding config
        $senderName = 'Jane Smith';
        $senderRole = 'Project Manager';
        $actionButtonUrl = 'https://example.com/action';
        $actionButtonText = 'View Deliverables';


        // --- Dynamic Placeholder Replacement Logic ---
        $bodyContent = $template->body_html;
        $subject = $template->subject;

        // Fetch all defined placeholders to get their metadata
        $definitions = PlaceholderDefinition::all()->keyBy('name');

        // Find all placeholders in the subject and body
        preg_match_all('/\{\{(\s*[\w\s]+\s*)\}\}/', $bodyContent, $bodyMatches);
        preg_match_all('/\{\{(\s*[\w\s]+\s*)\}\}/', $subject, $subjectMatches);

        $allMatches = array_merge($bodyMatches[1], $subjectMatches[1]);
        $allMatches = array_unique(array_map('trim', $allMatches));

        $replacements = [];

        foreach ($allMatches as $placeholderName) {
            $value = 'N/A'; // Default value if no definition is found

            // Check if a definition exists for this placeholder
            if ($definitions->has($placeholderName)) {
                $definition = $definitions->get($placeholderName);

                // Populate the value based on the definition
                if ($definition->is_dynamic) {
                    // Use sample data for dynamic variables
                    switch ($placeholderName) {
                        case 'magic_link':
                            $value = 'https://example.com/magic-link-for-testing';
                            break;
                        case 'due_date':
                            $value = now()->addDays(7)->toFormattedDateString();
                            break;
                        case 'report_month':
                            $value = now()->subMonth()->format('F Y');
                            break;
                        case 'invoice_number':
                            $value = 'INV-12345';
                            break;
                        case 'total_amount':
                            $value = '$500.00';
                            break;
                        case 'invoice_link':
                            $value = 'https://example.com/invoice/1';
                            break;
                        case 'review_link':
                            $value = 'https://example.com/review';
                            break;
                        case 'sender_name':
                            $value = $senderName;
                            break;
                    }
                } else {
                    // Pull from the specified model and attribute
                    try {
                        $model = app($definition->source_model);
                        $attribute = $definition->source_attribute;

                        // Simple logic to find an instance, can be more complex
                        $instance = $model->first();

                        if ($instance && isset($instance->$attribute)) {
                            $value = $instance->$attribute;
                        }
                    } catch (\Exception $e) {
                        // Log the error but don't break the preview
                        Log::error("Failed to get data for placeholder '{$placeholderName}': " . $e->getMessage());
                    }
                }
            } else {
                // For placeholders without a definition, replace with a clear indicator
                $value = "[MISSING DEFINITION: {$placeholderName}]";
            }

            $replacements["{{ {$placeholderName} }}"] = $value;
        }

        // Apply all replacements to the subject and body
        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $bodyContent = str_replace(array_keys($replacements), array_values($replacements), $bodyContent);

        // Render the main blade view with all the necessary data
        return View::make('emails.email_template', [
            'emailData' => ['subject' => $subject],
            'bodyContent' => $bodyContent,
            'clientName' => $client->name,
            'projectName' => $project->name,
            'senderName' => $senderName,
            'senderRole' => $senderRole,
            'actionButtonUrl' => $actionButtonUrl,
            'actionButtonText' => $actionButtonText,

            // --- Use reusable information from the branding config ---
            'senderPhone' => $brandingConfig['company']['phone'],
            'senderWebsite' => $brandingConfig['company']['website'],
            'companyLogoUrl' => asset($brandingConfig['company']['logo_url']),
            'brandPrimaryColor' => $brandingConfig['branding']['brand_primary_color'],
            'brandSecondaryColor' => $brandingConfig['branding']['brand_secondary_color'],
            'textColorPrimary' => $brandingConfig['branding']['text_color_primary'],
            'textColorSecondary' => $brandingConfig['branding']['text_color_secondary'],
            'backgroundColor' => $brandingConfig['branding']['background_color'],
            'borderColor' => $brandingConfig['branding']['border_color'],
            'socialIcons' => $brandingConfig['social_icons'],
            'signatureTagline' => $brandingConfig['signature']['tagline'],
            'reviewLink' => 'https://example.com/review', // This can also be added to config if desired
        ]);
    }
}
