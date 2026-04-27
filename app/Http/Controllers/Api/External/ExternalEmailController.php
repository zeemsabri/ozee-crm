<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Jobs\SendExternalEmailJob;
use App\Models\EmailApp;
use App\Models\ExternalEmailLog;
use App\Models\MagicLink;
use Illuminate\Http\Request;

/**
 * @group External API
 *
 * APIs for external systems to send transactional email through a linked email app.
 */
class ExternalEmailController extends Controller
{
    /**
     * Send Email
     *
     * Send an email through the email app linked to the current external magic token.
     *
     * The provided `app_id` must match the email app linked to the token in the
     * `X-Magic-Token` header. Only SMTP delivery mode is supported in this phase.
     *
     * ### Example Payload
     * ```json
     * {
     *   "app_id": 12,
    *   "to": ["recipient@example.com", "recipient2@example.com"],
     *   "cc": ["manager@example.com"],
     *   "bcc": ["audit@example.com"],
     *   "subject": "Welcome to the portal",
     *   "body_html": "<p>Hello <strong>there</strong></p>",
     *   "body_text": "Hello there",
     *   "from_address": "noreply@example.com",
     *   "from_name": "Example App",
     *   "reply_to": "support@example.com",
     *   "metadata": {
     *     "external_message_id": "msg_12345"
     *   }
     * }
     * ```
     *
     * @authenticated
     *
     * @bodyParam app_id integer required The internal ID of the email app linked to the token. Example: 12
    * @bodyParam to string|array required A single recipient email or a list of recipients. Example: ["recipient@example.com", "recipient2@example.com"]
    * @bodyParam to.* string Email address to send to when `to` is an array. Example: recipient@example.com
     * @bodyParam cc array Optional list of CC recipient email addresses. Example: ["manager@example.com"]
     * @bodyParam cc.* string Email address to CC. Example: manager@example.com
     * @bodyParam bcc array Optional list of BCC recipient email addresses. Example: ["audit@example.com"]
     * @bodyParam bcc.* string Email address to BCC. Example: audit@example.com
     * @bodyParam subject string required Email subject line. Maximum 255 characters. Example: Welcome to the portal
     * @bodyParam body_html string Optional HTML email body. Required when `body_text` is not provided. Example: <p>Hello <strong>there</strong></p>
     * @bodyParam body_text string Optional plain-text email body. Required when `body_html` is not provided. Example: Hello there
     * @bodyParam from_address string Optional sender email override. Falls back to the email app default sender address. Example: noreply@example.com
     * @bodyParam from_name string Optional sender name override. Falls back to the email app default sender name. Example: Example App
     * @bodyParam reply_to string Optional reply-to email override. Falls back to the email app default reply-to address. Example: support@example.com
     * @bodyParam metadata object Optional metadata stored with the email log entry. Example: {"external_message_id": "msg_12345"}
     *
     * @response 200 {
     *   "success": true,
    *   "message": "Emails queued successfully.",
     *   "data": {
    *     "queued_count": 2,
    *     "hourly_send_limit": 100,
    *     "log_ids": [25, 26]
     *   }
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "Token does not belong to the provided app_id."
     * }
     *
     * @response 422 {
     *   "success": false,
     *   "message": "API provider mode is configured but not supported in phase 1.",
     *   "code": "external_email_api_mode_not_supported"
     * }
     *
     * @response 429 {
     *   "success": false,
     *   "message": "Recipient count exceeds the per-request safety limit.",
     *   "max_recipients_per_request": 200
     * }
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'app_id' => ['required', 'integer', 'exists:email_apps,id'],
            'to' => ['required'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['email'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string'],
            'body_text' => ['nullable', 'string'],
            'from_address' => ['nullable', 'email'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (empty($validated['body_html']) && empty($validated['body_text'])) {
            return response()->json([
                'success' => false,
                'message' => 'At least one of body_html or body_text is required.',
            ], 422);
        }

        $recipients = $this->normalizeRecipients($validated['to']);
        if (empty($recipients)) {
            return response()->json([
                'success' => false,
                'message' => 'The to field must be a valid email or array of valid emails.',
            ], 422);
        }

        $maxRecipientsPerRequest = 200;
        if (count($recipients) > $maxRecipientsPerRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient count exceeds the per-request safety limit.',
                'max_recipients_per_request' => $maxRecipientsPerRequest,
            ], 429);
        }

        /** @var MagicLink|null $magicLink */
        $magicLink = $request->attributes->get('magic_link');
        $emailApp = EmailApp::findOrFail($validated['app_id']);

        if (! $magicLink || $magicLink->type !== 'external') {
            $this->createLog($magicLink, $emailApp, $validated, 'rejected', 'invalid_token_type');

            return response()->json([
                'success' => false,
                'message' => 'Only external tokens can use this endpoint.',
            ], 403);
        }

        if (! $magicLink->email_app_id) {
            $this->createLog($magicLink, $emailApp, $validated, 'rejected', 'token_not_linked');

            return response()->json([
                'success' => false,
                'message' => 'Token is not linked to an email app.',
            ], 403);
        }

        if ((int) $magicLink->email_app_id !== (int) $emailApp->id) {
            $this->createLog($magicLink, $emailApp, $validated, 'rejected', 'token_app_mismatch');

            return response()->json([
                'success' => false,
                'message' => 'Token does not belong to the provided app_id.',
            ], 403);
        }

        if (! $emailApp->is_active) {
            $this->createLog($magicLink, $emailApp, $validated, 'rejected', 'email_app_inactive');

            return response()->json([
                'success' => false,
                'message' => 'Email app is inactive.',
            ], 403);
        }

        if ($emailApp->delivery_mode === 'api') {
            $this->createLog($magicLink, $emailApp, $validated, 'not_supported', 'api_mode_not_supported_in_phase_1', [
                'delivery_mode' => 'api',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'API provider mode is configured but not supported in phase 1.',
                'code' => 'external_email_api_mode_not_supported',
            ], 422);
        }

        $missingSmtp = $this->missingSmtpFields($emailApp);
        if (! empty($missingSmtp)) {
            $this->createLog($magicLink, $emailApp, $validated, 'failed', 'missing_smtp_configuration', [
                'missing' => $missingSmtp,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Email app SMTP configuration is incomplete.',
                'missing' => $missingSmtp,
            ], 422);
        }

        $fromAddress = $validated['from_address'] ?? $emailApp->smtp_from_address;
        $fromName = $validated['from_name'] ?? $emailApp->smtp_from_name;
        $replyTo = $validated['reply_to'] ?? $emailApp->smtp_reply_to;

        $hourlyLimit = max(1, (int) ($emailApp->hourly_send_limit ?: 100));
        $alreadyQueuedInLastHour = ExternalEmailLog::query()
            ->where('email_app_id', $emailApp->id)
            ->where('status', 'queued')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $alreadyAttemptedInLastHour = ExternalEmailLog::query()
            ->where('email_app_id', $emailApp->id)
            ->whereNotNull('attempted_at')
            ->where('attempted_at', '>=', now()->subHour())
            ->count();

        $positionBase = $alreadyQueuedInLastHour + $alreadyAttemptedInLastHour;
        $logIds = [];

        foreach ($recipients as $index => $recipient) {
            $position = $positionBase + $index;
            $delaySeconds = (int) floor(($position * 3600) / $hourlyLimit);
            $scheduledFor = now()->addSeconds($delaySeconds);

            $perRecipientPayload = $validated;
            $perRecipientPayload['to'] = $recipient;
            $perRecipientPayload['body_html'] = $validated['body_html'] ?? null;
            $perRecipientPayload['body_text'] = $validated['body_text'] ?? null;
            $perRecipientPayload['from_address'] = $fromAddress;
            $perRecipientPayload['from_name'] = $fromName;
            $perRecipientPayload['reply_to'] = $replyTo;

            $log = $this->createLog(
                $magicLink,
                $emailApp,
                $perRecipientPayload,
                'queued',
                null,
                [
                    'delivery_mode' => 'smtp',
                    'queued_for' => $scheduledFor->toDateTimeString(),
                    'processed_by_queue' => true,
                ],
                null,
                null,
            );

            SendExternalEmailJob::dispatch($log->id, $emailApp->id)
                ->delay($scheduledFor)
                ->onQueue('emails');

            $logIds[] = $log->id;
        }

        return response()->json([
            'success' => true,
            'message' => 'Emails queued successfully.',
            'data' => [
                'queued_count' => count($recipients),
                'hourly_send_limit' => $hourlyLimit,
                'log_ids' => $logIds,
            ],
        ]);
    }

    private function createLog(
        ?MagicLink $magicLink,
        ?EmailApp $emailApp,
        array $validated,
        string $status,
        ?string $errorMessage = null,
        ?array $responsePayload = null,
        $sentAt = null,
        $attemptedAt = null,
    ): ExternalEmailLog {
        return ExternalEmailLog::create([
            'magic_link_id' => $magicLink?->id,
            'email_app_id' => $emailApp?->id,
            'project_id' => $magicLink?->project_id,
            'status' => $status,
            'provider' => $emailApp?->delivery_mode === 'api' ? ($emailApp->api_provider ?: 'api') : 'smtp',
            'to_email' => is_array($validated['to'] ?? null)
                ? (string) ($validated['to'][0] ?? 'unknown')
                : (string) ($validated['to'] ?? 'unknown'),
            'subject' => $validated['subject'] ?? null,
            'error_message' => $errorMessage,
            'request_payload' => [
                'app_id' => $validated['app_id'] ?? null,
                'to' => $validated['to'] ?? null,
                'cc' => $validated['cc'] ?? [],
                'bcc' => $validated['bcc'] ?? [],
                'subject' => $validated['subject'] ?? null,
                'body_html' => $validated['body_html'] ?? null,
                'body_text' => $validated['body_text'] ?? null,
                'from_address' => $validated['from_address'] ?? null,
                'from_name' => $validated['from_name'] ?? null,
                'reply_to' => $validated['reply_to'] ?? null,
                'has_body_html' => ! empty($validated['body_html']),
                'has_body_text' => ! empty($validated['body_text']),
                'metadata' => $validated['metadata'] ?? [],
            ],
            'response_payload' => $responsePayload,
            'attempted_at' => $attemptedAt ?? ($status === 'queued' ? null : now()),
            'sent_at' => $sentAt,
        ]);
    }

    private function normalizeRecipients(mixed $to): array
    {
        $emails = [];

        if (is_string($to)) {
            $emails = [trim($to)];
        }

        if (is_array($to)) {
            $emails = array_values(array_filter(array_map(function ($email) {
                return is_string($email) ? trim($email) : null;
            }, $to)));
        }

        $emails = array_values(array_unique($emails));

        return array_values(array_filter($emails, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }));
    }

    private function missingSmtpFields(EmailApp $emailApp): array
    {
        $required = [
            'smtp_host' => $emailApp->smtp_host,
            'smtp_port' => $emailApp->smtp_port,
            'smtp_username' => $emailApp->smtp_username,
            'smtp_password' => $emailApp->smtp_password,
            'smtp_from_address' => $emailApp->smtp_from_address,
        ];

        $missing = [];
        foreach ($required as $field => $value) {
            if ($value === null || $value === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}
