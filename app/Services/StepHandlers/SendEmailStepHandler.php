<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailStepHandler implements StepHandlerContract
{
    public function handle(array $context, WorkflowStep $step, ExecutionLog|null $execLog = null): array
    {
        $cfg = $step->step_config ?? [];
        $to = $this->applyTemplate((string)($cfg['to'] ?? ''), $context);
        $subject = $this->applyTemplate((string)($cfg['subject'] ?? ''), $context);
        $body = $this->applyTemplate((string)($cfg['body'] ?? ''), $context);

        if (!$to) {
            throw new \InvalidArgumentException('to is required for SEND_EMAIL');
        }

        try {
            // Try using Laravel Mail; if not configured, log
            if (config('mail.mailers')) {
                Mail::raw($body, function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                });
            } else {
                Log::info('SendEmailStepHandler.mail', compact('to', 'subject', 'body'));
            }
        } catch (\Throwable $e) {
            // Fallback to logging
            Log::info('SendEmailStepHandler.mail_fallback', compact('to', 'subject', 'body'));
        }

        return [
            'parsed' => [
                'to' => $to,
                'subject' => $subject,
            ],
        ];
    }

    protected function applyTemplate(string $value, array $ctx): string
    {
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($m) use ($ctx) {
            $path = trim($m[1]);
            $parts = preg_split('/\.|\:/', $path);
            $val = $ctx;
            foreach ($parts as $p) {
                if (is_array($val) && array_key_exists($p, $val)) {
                    $val = $val[$p];
                } else {
                    return '';
                }
            }
            return is_scalar($val) ? (string) $val : json_encode($val);
        }, $value);
    }
}
