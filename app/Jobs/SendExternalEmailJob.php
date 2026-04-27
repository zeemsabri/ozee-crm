<?php

namespace App\Jobs;

use App\Mail\ExternalApiEmail;
use App\Models\EmailApp;
use App\Models\ExternalEmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendExternalEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $externalEmailLogId,
        public int $emailAppId,
    ) {
    }

    public function handle(): void
    {
        /** @var ExternalEmailLog|null $log */
        $log = ExternalEmailLog::find($this->externalEmailLogId);
        /** @var EmailApp|null $emailApp */
        $emailApp = EmailApp::find($this->emailAppId);

        if (! $log || ! $emailApp) {
            return;
        }

        if ($log->status === 'sent') {
            return;
        }

        if (! $emailApp->is_active) {
            $this->markFailed($log, 'email_app_inactive');

            return;
        }

        $missingSmtp = $this->missingSmtpFields($emailApp);
        if (! empty($missingSmtp)) {
            $this->markFailed($log, 'missing_smtp_configuration', ['missing' => $missingSmtp]);

            return;
        }

        $requestPayload = is_array($log->request_payload) ? $log->request_payload : [];
        $to = $log->to_email;

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

        try {
            $pending = Mail::mailer($mailerName)->to($to);

            if (! empty($requestPayload['cc']) && is_array($requestPayload['cc'])) {
                $pending->cc($requestPayload['cc']);
            }

            if (! empty($requestPayload['bcc']) && is_array($requestPayload['bcc'])) {
                $pending->bcc($requestPayload['bcc']);
            }

            $pending->send(new ExternalApiEmail(
                subjectLine: (string) ($log->subject ?? ''),
                htmlBody: $requestPayload['body_html'] ?? null,
                textBody: $requestPayload['body_text'] ?? null,
                fromAddress: $requestPayload['from_address'] ?? $emailApp->smtp_from_address,
                fromName: $requestPayload['from_name'] ?? $emailApp->smtp_from_name,
                replyToAddress: $requestPayload['reply_to'] ?? $emailApp->smtp_reply_to,
            ));

            $log->update([
                'status' => 'sent',
                'error_message' => null,
                'response_payload' => array_merge(
                    is_array($log->response_payload) ? $log->response_payload : [],
                    [
                        'delivery_mode' => 'smtp',
                        'processed_by_queue' => true,
                    ]
                ),
                'attempted_at' => now(),
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Queued external email send failed.', [
                'email_app_id' => $emailApp->id,
                'external_email_log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($log, $e->getMessage(), [
                'delivery_mode' => 'smtp',
                'processed_by_queue' => true,
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $log = ExternalEmailLog::find($this->externalEmailLogId);

        if (! $log || $log->status === 'sent') {
            return;
        }

        $this->markFailed($log, $exception->getMessage(), [
            'delivery_mode' => 'smtp',
            'processed_by_queue' => true,
            'failed_callback' => true,
        ]);
    }

    private function markFailed(ExternalEmailLog $log, string $error, array $responsePayload = []): void
    {
        $log->update([
            'status' => 'failed',
            'error_message' => $error,
            'response_payload' => array_merge(
                is_array($log->response_payload) ? $log->response_payload : [],
                $responsePayload
            ),
            'attempted_at' => now(),
            'sent_at' => null,
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
