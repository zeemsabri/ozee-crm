<?php

namespace App\Services;

use App\Http\Controllers\Api\Concerns\HandlesTemplatedEmails;
use App\Http\Controllers\Api\Concerns\HasProjectPermissions;
use App\Models\Email;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailProcessingService
{
    // We can use the trait here to access its rendering methods without being a controller.
    use HandlesTemplatedEmails, HasProjectPermissions;

    public function __construct(
        protected EmailAiAnalysisService $aiAnalysisService,
        protected GmailService $gmailService,
        protected MagicLinkService $magicLinkService
    ) {
    }

    /**
     * Process a draft email: analyze it, create context, and decide whether to
     * send it or move it to pending approval.
     */
    public function processDraftEmail(Email $email): void
    {
        try {

            $body = json_decode($email->body);
            $isAiGenerated = is_null($email->template_id) && $body && ($body->greeting && isset($body->paragraphs));

            if ($isAiGenerated) {
                $this->processEmailOutReach($email);
                return;
            }
            // 1. Render the template to get the final subject and body
            // We set isFinalSend to `true` to populate all placeholders correctly.
            $renderedContent = $this->renderEmailContent($email, false);
            $subject = $renderedContent['subject'];
            $bodyHtml = $renderedContent['body'];

            // 2. Prepare plain text version for the AI
            // This is crucial for cost-effectiveness and accuracy.
            $plainTextForAI = $this->prepareTextForAI($subject, $bodyHtml);

            // 3. Get AI analysis and context
            $aiResponse = $this->aiAnalysisService->analyzeAndSummarize($plainTextForAI);

            if (!$aiResponse) {
                // If AI fails, move to pending approval for safety
                $email->update(['status' => Email::STATUS_PENDING_APPROVAL_SENT]);
                return;
            }

            // 4. Create the context from the AI's response
            $this->createContextForEmail($email, $aiResponse);

            // 5. Decide the next step based on AI feedback
            if ($aiResponse['approval_required']) {
                $email->update(['status' => Email::STATUS_PENDING_APPROVAL_SENT]);
                Log::info('Email moved to pending approval by AI.', ['email_id' => $email->id, 'reason' => $aiResponse['reason']]);
            } else {
                // Auto-approved! Send the email.
                $this->sendApprovedEmail($email, $subject, $bodyHtml);
                Log::info('Email auto-approved and sent by AI.', ['email_id' => $email->id]);
            }
        } catch (Throwable $e) {
            // If any part of the process fails, ensure it goes to manual approval.
            $email->update(['status' => Email::STATUS_PENDING_APPROVAL]);
            Log::error('Error in EmailProcessingService.', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function processEmailOutReach(Email $email): void
    {
        $renderedContent = $this->renderEmailContent($email, false);
        $subject = $renderedContent['subject'];
        $bodyHtml = $renderedContent['body'];
        $this->sendApprovedEmail($email, $subject, $bodyHtml, 'ai_lead_outreach_template');
    }

    /**
     * Sends an approved email using the Gmail service.
     */
    protected function sendApprovedEmail(Email $email, string $subject, string $renderedBody, $template = 'email_template'): void
    {
        // This logic is adapted from your `editAndApprove` method.
        $senderDetails = $this->getSenderDetails($email);
        $data = $this->getData($subject, $renderedBody, $senderDetails, $email, true);
        $finalRenderedBody = $this->renderHtmlTemplate($data, $template);

        $recipient = $email->conversation->conversable;

        if ($recipient && !empty($recipient->email)) {
            $this->gmailService->sendEmail(
                $recipient->email,
                $subject,
                $finalRenderedBody
            );

            // Update email status after sending
            $email->update([
                'status' => Email::STATUS_SENT,
                // We can use a dedicated system user ID or null for 'approved_by'
                'approved_by' => User::where('email', 'info@ozeeweb.com.au')->first()->id ?? null,
                'sent_at' => now()
            ]);
        } else {
            // If no recipient, mark as failed instead of sending
            $email->update(['status' => 'failed']);
            Log::warning('Email could not be sent due to missing recipient.', ['email_id' => $email->id]);
        }
    }

    /**
     * Creates the context record for the email.
     */
    public function createContextForEmail(Email $email, array $aiResponse): void
    {

        if(isset($aiResponse['approval_required']) && $aiResponse['approval_required'] === false){
            $email->update(['status' => Email::STATUS_APPROVED]);
        }

        $context = new \App\Models\Context([
            'summary'   => $aiResponse['context_summary'],
            'project_id'    => $email->conversation?->project_id ?? null,
            'user_id'   => $email->sender_id, // The user who created the draft
            'meta_data' => [
                'approval_required' => $aiResponse['approval_required'],
                'reason'            => $aiResponse['reason'],
                'source'            => 'ai_analysis_v1'
            ],
        ]);

        // Link the context to the SOURCE (the email)
        $context->referencable()->associate($email);

        // Link the context to the SUBJECT (the conversable entity - Lead or Client)
        $context->linkable()->associate($email->conversation->conversable);

        $context->save();
    }

    /**
     * Prepares a clean, plain-text version of the email for the AI.
     */
    protected function prepareTextForAI(string $subject, string $bodyHtml): string
    {
        // Strip HTML tags to get plain text
        $plainBody = strip_tags($bodyHtml);
        // Decode HTML entities like &amp; into &
        $plainBody = html_entity_decode($plainBody);
        // Remove extra whitespace
        $plainBody = preg_replace('/\s+/', ' ', $plainBody);

        return "Subject: " . $subject . "\n\n" . trim($plainBody);
    }
}
