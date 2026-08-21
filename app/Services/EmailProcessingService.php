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
            /*
             * Fall back to manual approval — but ONLY if the email has not already gone
             * out.
             *
             * The send happens in the middle of this try block, and plenty runs after it:
             * `$email->update(['status' => Sent])` fires the model's `updated` event, which
             * the automation subscriber listens to (config/automation.php allow-lists Email
             * for `updated`), and anything that throws downstream of the Gmail call landed
             * here. The old unconditional write then rewrote a DELIVERED email back to
             * pending_approval: the client had the message, the CRM said it was still
             * waiting, and the approver who then pressed "Approve & send" mailed them a
             * second copy.
             *
             * refresh() first, because the in-memory model can be stale — sendApprovedEmail
             * writes the status through a separate save().
             */
            $sent = false;

            try {
                $email->refresh();
                $status = $email->status instanceof EmailStatus
                    ? $email->status
                    : EmailStatus::tryFrom((string) $email->status);

                $sent = $status === EmailStatus::Sent || $email->sent_at !== null;
            } catch (Throwable $refreshFailed) {
                // Deleted mid-flight, or the DB is gone. Either way, do not write.
                $sent = true;
            }

            if (! $sent) {
                $email->update(['status' => EmailStatus::PendingApproval]);
            }

            Log::error('Error in EmailProcessingService.', [
                'email_id' => $email->id,
                'already_sent' => $sent,
                'demoted_to_pending_approval' => ! $sent,
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
        /*
         * Block-built emails: re-render the body from the stored blocks, and collect the
         * image parts that travel with it.
         *
         * This has to happen HERE — before getData()/renderHtmlTemplate() — for two
         * reasons. Renders after that point would either be thrown away by the branded
         * layout wrapper or, worse, replace it, so a block email would go out with no
         * header and no footer while every other email kept them. And the threading hook
         * further down appends the quoted history to whatever body it is handed, so a
         * re-render after that would silently drop the quote.
         *
         * Empty for every other email in the system — isBlockEmail() is false unless
         * `template_data['blocks']` is present, which no classic writer ever sets — so the
         * existing auto-send path is byte-for-byte what it was.
         *
         * Re-rendering rather than trusting the stored `emails.body` guarantees the `cid:`
         * references in the HTML and the parts attached beside them came from the same
         * read of `files`: if an image was pruned between submitting and sending, the
         * reference and the part disappear together instead of leaving a broken image in
         * the client's mailbox. See BlockComposition.
         */
        $blockComposition = app(\App\Services\Inbox\BlockComposition::class);
        $inlineImages = [];

        if ($blockComposition->isBlockEmail($email)) {
            $renderedBody = $blockComposition->renderForSend($email) ?? $renderedBody;
            $inlineImages = $blockComposition->inlinePartsFor($email);
        }

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

        /*
         * A Message-ID is minted for EVERY outbound send, not just replies — matching
         * Api\EmailController::editAndApprove.
         *
         * It is what lets the SENT-folder ingester recognise our own mail: IngestSentMail
         * matches on rfc_message_id to separate "the CRM sent this" from "somebody typed
         * this into Gmail". Without one on every send, every auto-sent email would come
         * back as a duplicate outbound row on its own thread.
         *
         * rescue(): getAuthorizedEmail() is declared `: string` but reads an untyped
         * property that is null until the Google client has authorised, so calling it
         * before the send loop can TypeError. The domain only affects the Message-ID's
         * right-hand side, so falling back is harmless.
         */
        $fromAddress = rescue(
            fn () => $this->gmailService->getAuthorizedEmail(),
            config('mail.from.address'),
            false
        );
        $outgoingMessageId = $threading->newMessageId($email, $fromAddress);

        if ($isReply) {
            $threadHeaders = $threading->headersFor($email);
            $finalRenderedBody = $threading->withQuotedThread($email, $finalRenderedBody);
        }

        if (! empty($recipients)) {
            $gmailThreadId = null;

            foreach ($recipients as $recipientEmail) {
                if (! empty($recipientEmail)) {
                    $sent = $this->gmailService->sendMessage(
                        $recipientEmail,
                        $subject,
                        $finalRenderedBody,
                        $threadHeaders,
                        $outgoingMessageId,
                        $inlineImages
                    );

                    $gmailThreadId ??= $sent['threadId'] ?? null;
                }
            }

            // After the whole loop, not inside it — see the matching note in
            // Api\EmailController::editAndApprove. Written for every send now, not just
            // replies, so the SENT ingester can recognise this message when it sees it.
            $email->forceFill([
                'rfc_message_id' => $outgoingMessageId,
                'gmail_thread_id' => $gmailThreadId,
            ])->save();

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
