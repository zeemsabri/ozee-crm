<?php

namespace App\Services\StepHandlers;

use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailStepHandler implements StepHandlerContract
{
    public function __construct(
        protected ?WorkflowEngineService $engine = null
    ) {}

    public function handle(array $context, WorkflowStep $step, ?ExecutionLog $execLog = null): array
    {
        $cfg = $step->step_config ?? [];

        // Use WorkflowEngineService's template resolution if available (supports nested paths and .parsed fallback)
        if ($this->engine) {
            $to = (string) $this->engine->getTemplatedValue((string) ($cfg['to'] ?? ''), $context);
            $subject = (string) $this->engine->getTemplatedValue((string) ($cfg['subject'] ?? ''), $context);
            $body = (string) $this->engine->getTemplatedValue((string) ($cfg['body'] ?? ''), $context);
        } else {
            // Fallback to local template method
            $to = $this->applyTemplate((string) ($cfg['to'] ?? ''), $context);
            $subject = $this->applyTemplate((string) ($cfg['subject'] ?? ''), $context);
            $body = $this->applyTemplate((string) ($cfg['body'] ?? ''), $context);
        }

        if (! $to) {
            throw new \InvalidArgumentException('to is required for SEND_EMAIL');
        }

        try {
            // Try using Laravel Mail; if not configured, log
            if (config('mail.mailers')) {
                Mail::raw($body, function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                });
            }
        } catch (\Throwable $e) {

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
