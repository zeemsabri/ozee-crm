<?php

namespace App\Services;

use App\Enums\EmailStatus;
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
    ) {}

    /**
     * Process a draft email: analyze it, create context, and decide whether to
     * send it or move it to pending approval.
     */
    public function processDraftEmail(Email $email): void
    {
        try {
            // Refresh the email model to get the latest status
            $email->refresh();

            // Only process if status is auto_send or draft
            if (! in_array($email->status, [EmailStatus::AutoSend, EmailStatus::Draft])) {
                return;
            }

            $body = json_decode($email->body);
            $isAiGenerated = is_null($email->template_id) && $body && ($body->greeting && isset($body->paragraphs));

            if ($isAiGenerated) {
                $this->processEmailOutReach($email);

                return;
            }
            // 1. Render the template to get the final subject and body
            // We set isFinalSend to `true` to populate all placeholders correctly.
            $renderedContent = $this->renderEmailContent($email, true);
            $subject = $renderedContent['subject'];
            $bodyHtml = $renderedContent['body'];

            // Auto-approved! Send the email.
            $this->sendApprovedEmail($email, $subject, $bodyHtml);

        } catch (Throwable $e) {
            // If any part of the process fails, ensure it goes to manual approval.
            $email->update(['status' => EmailStatus::PendingApproval]);
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
        $recipient = $email->conversation?->conversable ?? null;

        $recipients = [];
        if (! empty($email->to)) {
            $recipients = is_array($email->to) ? $email->to : [$email->to];
        } elseif ($recipient && ! empty($recipient->email)) {
            $recipients = [$recipient->email];
        }

        /*
         * Threading for replies composed in the redesigned inbox. Identical to the hook in
         * Api\EmailController::editAndApprove and gated the same way — on
         * in_reply_to_email_id, which is null on every email that predates it, so the
         * auto-send and draft paths are unchanged for everything else.
         *
         * This path matters for a reply that was saved as a draft and later processed by
         * ProcessDraftEmailJob rather than approved by hand: without it, that reply would
         * go out unthreaded and unquoted while the approved one would not.
         */
        $threading = app(\App\Services\Inbox\ReplyThreading::class);
        $isReply = $threading->isReply($email);
        $threadHeaders = [];
        $outgoingMessageId = null;

        if ($isReply) {
            $threadHeaders = $threading->headersFor($email);
            $finalRenderedBody = $threading->withQuotedThread($email, $finalRenderedBody);
            $outgoingMessageId = $threading->newMessageId($email, config('mail.from.address'));
        }

        if (! empty($recipients)) {
            foreach ($recipients as $recipientEmail) {
                if (! empty($recipientEmail)) {
                    $sent = $this->gmailService->sendMessage(
                        $recipientEmail,
                        $subject,
                        $finalRenderedBody,
                        $threadHeaders,
                        $outgoingMessageId
                    );

                    if ($isReply && ! $email->rfc_message_id) {
                        $email->forceFill([
                            'rfc_message_id' => $outgoingMessageId,
                            'gmail_thread_id' => $sent['threadId'] ?? null,
                        ])->save();
                    }
                }
            }

            // Update email status after sending
            $email->update([
                'status' => EmailStatus::Sent,
                // We can use a dedicated system user ID or null for 'approved_by'
                'approved_by' => User::where('email', 'info@ozeeweb.com.au')->first()->id ?? null,
                'sent_at' => now(),
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

        if (isset($aiResponse['approval_required']) && $aiResponse['approval_required'] === false) {
            $email->update(['status' => EmailStatus::Sent]);
        }

        $context = new \App\Models\Context([
            'summary' => $aiResponse['context_summary'],
            'project_id' => $email->conversation?->project_id ?? null,
            'user_id' => $email->sender_id, // The user who created the draft
            'meta_data' => [
                'approval_required' => $aiResponse['approval_required'],
                'reason' => $aiResponse['reason'],
                'source' => 'ai_analysis_v1',
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

        return 'Subject: '.$subject."\n\n".trim($plainBody);
    }
}
