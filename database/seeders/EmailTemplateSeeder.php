<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the templates and their content
        $templates = [
            [
                'name' => 'New Deliverables for Approval',
                'slug' => 'deliverables-for-approval',
                'subject' => 'New blog posts are ready for your review: {{ project_name }}',
                'body_html' => '
                    <h1>Hello, {{ client_name }}!</h1>
                    <p>Great news! We have added new deliverables for your project <strong>{{ project_name }}</strong> that are ready for your review and approval on the client portal.</p>
                    <p>Please click the link below to access your client portal and view the new deliverables.</p>
                    <a href="{{ magic_link }}">View Deliverables</a>
                    <p>Thank you for your prompt attention!</p>
                ',
                'description' => 'Used to notify a client about new deliverables awaiting their approval.',
                'is_default' => true,
            ],
            [
                'name' => 'Reminder for Deliverable Approval',
                'slug' => 'deliverable-approval-reminder',
                'subject' => 'Reminder: Action required for {{ project_name }} deliverables',
                'body_html' => '
                    <h1>Hi {{ client_name }},</h1>
                    <p>This is a friendly reminder that several items for your project <strong>{{ project_name }}</strong> are still awaiting your review and approval on the client portal.</p>
                    <p>Please review these items by <strong>{{ due_date }}</strong>. If no action is taken by this date, the deliverables will be considered automatically approved.</p>
                    <a href="{{ magic_link }}">Review Deliverables</a>
                    <p>If you have any questions, please do not hesitate to contact us.</p>
                ',
                'description' => 'A reminder email sent to clients for deliverables that are pending approval.',
                'is_default' => true,
            ],
            [
                'name' => 'New Monthly SEO Report',
                'slug' => 'monthly-seo-report',
                'subject' => 'Your Monthly SEO Report for {{ report_month }} is Ready!',
                'body_html' => '
                    <h1>Hello {{ client_name }},</h1>
                    <p>Your monthly SEO report for <strong>{{ report_month }}</strong> is now available on your client dashboard. The report provides a comprehensive overview of your website\'s performance and key metrics for the past month.</p>
                    <a href="{{ magic_link }}">View Report</a>
                    <p>We look forward to discussing the results with you soon!</p>
                ',
                'description' => 'Notifies the client when a new monthly SEO report is available for viewing.',
                'is_default' => true,
            ],
            [
                'name' => 'Invoice Notification',
                'slug' => 'invoice-notification',
                'subject' => 'New Invoice for {{ project_name }} is ready: #{{ invoice_number }}',
                'body_html' => '
                    <h1>Hi {{ client_name }},</h1>
                    <p>A new invoice (<strong>#{{ invoice_number }}</strong>) for your project <strong>{{ project_name }}</strong> has been generated and is now available on your client portal.</p>
                    <p>The total amount due is <strong>{{ total_amount }}</strong>, with a due date of <strong>{{ due_date }}</strong>.</p>
                    <a href="{{ invoice_link }}">View and Pay Invoice</a>
                    <p>Thank you for your business!</p>
                ',
                'description' => 'Used to send a new invoice to a client.',
                'is_default' => true,
            ],
            [
                'name' => 'Review Request',
                'slug' => 'review-request',
                'subject' => 'How did we do? Leave us a review!',
                'body_html' => '
                    <h1>Hi {{ client_name }},</h1>
                    <p>Thank you for being a valued client! We hope you were satisfied with our recent work on your project <strong>{{ project_name }}</strong>.</p>
                    <p>If you have a moment, we would greatly appreciate it if you could leave us a review. Your feedback helps us improve and grow!</p>
                    <a href="{{ review_link }}">Leave a Review</a>
                    <p>Best regards,<br>{{ sender_name }}</p>
                ',
                'description' => 'A template to request a review from a client after a project is completed.',
                'is_default' => true,
            ],
        ];

        DB::beginTransaction();

        try {
            foreach ($templates as $templateData) {
                $template = EmailTemplate::updateOrCreate(
                    ['slug' => $templateData['slug']],
                    $templateData
                );

                // Extract placeholders from the subject and body
                $placeholders = $this->extractPlaceholders($template->subject . ' ' . $template->body_html);
                $this->syncPlaceholders($template, $placeholders);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error seeding email templates: ' . $e->getMessage());
        }
    }

    /**
     * Helper function to extract placeholders from a string.
     * @param string $content
     * @return array
     */
    private function extractPlaceholders(string $content): array
    {
        $placeholders = [];
        preg_match_all('/\{\{(\s*[\w\.]+\s*)\}\}/', $content, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $match) {
                $placeholders[] = trim($match);
            }
        }
        return array_unique($placeholders);
    }

    /**
     * Sync placeholders for a given template.
     * @param EmailTemplate $template
     * @param array $placeholders
     */
    private function syncPlaceholders(EmailTemplate $template, array $placeholders)
    {
        $existingPlaceholders = $template->placeholders->pluck('name')->toArray();

        // Placeholders to be added
        $placeholdersToAdd = array_diff($placeholders, $existingPlaceholders);
        foreach ($placeholdersToAdd as $name) {
            $template->placeholders()->create(['name' => $name, 'description' => null]);
        }

        // Placeholders to be removed
        $placeholdersToRemove = array_diff($existingPlaceholders, $placeholders);
        $template->placeholders()->whereIn('name', $placeholdersToRemove)->delete();
    }
}
