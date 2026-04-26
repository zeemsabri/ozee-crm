<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Mail\ExternalApiEmail;
use App\Models\EmailApp;
use App\Models\ExternalEmailLog;
use App\Models\MagicLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     *   "to": "recipient@example.com",
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
     * @bodyParam to string required The primary recipient email address. Example: recipient@example.com
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
     *   "message": "Email sent successfully.",
     *   "data": {
     *     "log_id": 25,
     *     "status": "sent"
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
     * @response 500 {
     *   "success": false,
     *   "message": "Failed to send email.",
     *   "data": {
     *     "log_id": 25,
     *     "status": "failed"
     *   }
     * }
     */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'app_id' => ['required', 'integer', 'exists:email_apps,id'],
            'to' => ['required', 'email'],
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

        $mailerName = 'external_email_app_'.(string) $emailApp->id;

        config([
            "mail.mailers.{$mailerName}" => [
                'transport' => 'smtp',
                'host' => $emailApp->smtp_host,
                'port' => (int) $emailApp->smtp_port,
                'username' => $emailApp->smtp_username,
                'password' => $emailApp->smtp_password,
                'encryption' => $emailApp->smtp_encryption,
                'timeout' => null,
            ],
        ]);

        $fromAddress = $validated['from_address'] ?? $emailApp->smtp_from_address;
        $fromName = $validated['from_name'] ?? $emailApp->smtp_from_name;
        $replyTo = $validated['reply_to'] ?? $emailApp->smtp_reply_to;

        try {
            $pending = Mail::mailer($mailerName)->to($validated['to']);

            if (! empty($validated['cc'])) {
                $pending->cc($validated['cc']);
            }

            if (! empty($validated['bcc'])) {
                $pending->bcc($validated['bcc']);
            }

            $pending->send(new ExternalApiEmail(
                subjectLine: $validated['subject'],
                htmlBody: $validated['body_html'] ?? null,
                textBody: $validated['body_text'] ?? null,
                fromAddress: $fromAddress,
                fromName: $fromName,
                replyToAddress: $replyTo,
            ));

            $log = $this->createLog($magicLink, $emailApp, $validated, 'sent', null, [
                'delivery_mode' => 'smtp',
            ], now());

            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully.',
                'data' => [
                    'log_id' => $log->id,
                    'status' => $log->status,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('External email send failed.', [
                'email_app_id' => $emailApp->id,
                'magic_link_id' => $magicLink->id,
                'error' => $e->getMessage(),
            ]);

            $log = $this->createLog($magicLink, $emailApp, $validated, 'failed', $e->getMessage(), [
                'delivery_mode' => 'smtp',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email.',
                'data' => [
                    'log_id' => $log->id,
                    'status' => $log->status,
                ],
            ], 500);
        }
    }

    private function createLog(
        ?MagicLink $magicLink,
        ?EmailApp $emailApp,
        array $validated,
        string $status,
        ?string $errorMessage = null,
        ?array $responsePayload = null,
        $sentAt = null,
    ): ExternalEmailLog {
        return ExternalEmailLog::create([
            'magic_link_id' => $magicLink?->id,
            'email_app_id' => $emailApp?->id,
            'project_id' => $magicLink?->project_id,
            'status' => $status,
            'provider' => $emailApp?->delivery_mode === 'api' ? ($emailApp->api_provider ?: 'api') : 'smtp',
            'to_email' => $validated['to'] ?? 'unknown',
            'subject' => $validated['subject'] ?? null,
            'error_message' => $errorMessage,
            'request_payload' => [
                'app_id' => $validated['app_id'] ?? null,
                'to' => $validated['to'] ?? null,
                'cc' => $validated['cc'] ?? [],
                'bcc' => $validated['bcc'] ?? [],
                'subject' => $validated['subject'] ?? null,
                'has_body_html' => ! empty($validated['body_html']),
                'has_body_text' => ! empty($validated['body_text']),
                'metadata' => $validated['metadata'] ?? [],
            ],
            'response_payload' => $responsePayload,
            'attempted_at' => now(),
            'sent_at' => $sentAt,
        ]);
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
