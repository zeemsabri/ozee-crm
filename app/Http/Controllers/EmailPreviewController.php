<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\Client;
use App\Models\Project;
use App\Models\PlaceholderDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;

class EmailPreviewController extends Controller
{
    /**
     * Renders a specific email template with sample data for preview.
     *
     * @param string $slug The slug of the email template to preview.
     * @return \Illuminate\Contracts\View\View
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

        $senderName = 'Jane Smith';
        $senderRole = 'Project Manager';
        $senderPhone = '123-456-7890';
        $senderWebsite = 'example.com';
        $companyLogoUrl = 'https://placehold.co/100x100/5d50c6/ffffff?text=LOGO';
        $brandPrimaryColor = '#5d50c6';
        $brandSecondaryColor = 'rgba(128, 90, 213, 0.1)';
        $textColorPrimary = '#333333';
        $textColorSecondary = '#555555';
        $backgroundColor = '#f4f4f4';
        $borderColor = '#e5e7eb';
        $reviewLink = 'https://example.com/review';
        $actionButtonUrl = 'https://example.com/action';
        $actionButtonText = 'View Deliverables';
        $socialIcons = [
            ['name' => 'Facebook', 'url' => 'https://facebook.com', 'iconUrl' => 'https://placehold.co/24x24/3b5998/ffffff?text=f'],
            ['name' => 'Twitter', 'url' => 'https://twitter.com', 'iconUrl' => 'https://placehold.co/24x24/1da1f2/ffffff?text=t'],
            ['name' => 'LinkedIn', 'url' => 'https://linkedin.com', 'iconUrl' => 'https://placehold.co/24x24/0077b5/ffffff?text=in'],
            ['name' => 'Instagram', 'url' => 'https://instagram.com', 'iconUrl' => 'https://placehold.co/24x24/833ab4/ffffff?text=ig'],
        ];

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
                        \Log::error("Failed to get data for placeholder '{$placeholderName}': " . $e->getMessage());
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

        // --- NEW: Convert newlines to HTML <br> tags
        $bodyContent = nl2br($bodyContent);

        // Render the main blade view with all the necessary data
        return View::make('emails.email_template', [
            'emailData' => ['subject' => $subject],
            'bodyContent' => $bodyContent,
            'clientName' => $client->name,
            'projectName' => $project->name,
            'senderName' => $senderName,
            'senderRole' => $senderRole,
            'senderPhone' => $senderPhone,
            'senderWebsite' => $senderWebsite,
            'companyLogoUrl' => $companyLogoUrl,
            'brandPrimaryColor' => $brandPrimaryColor,
            'brandSecondaryColor' => $brandSecondaryColor,
            'textColorPrimary' => $textColorPrimary,
            'textColorSecondary' => $textColorSecondary,
            'backgroundColor' => $backgroundColor,
            'borderColor' => $borderColor,
            'reviewLink' => $reviewLink,
            'actionButtonUrl' => $actionButtonUrl,
            'actionButtonText' => $actionButtonText,
            'socialIcons' => $socialIcons,
        ]);
    }
}
