<?php

namespace App\Services\StepHandlers;

use App\Jobs\ProcessDraftEmailJob;
use App\Models\Email;
use App\Models\WorkflowStep;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ProcessEmailStepHandler implements StepHandlerContract
{
    /**
     * Handle the PROCESS_EMAIL action step.
     */
    public function handle(array $context, WorkflowStep $step): array
    {
        $cfg = $step->step_config ?? [];
        $emailId = $this->resolveEmailId($context, $cfg);

        if (!$emailId) {
            $configuredPath = $cfg['email_id_path'] ?? 'none';
            throw new \RuntimeException("PROCESS_EMAIL: Unable to resolve a valid email ID. No value found at configured path ('{$configuredPath}') or in default locations ('trigger.email.id', 'triggering_object_id').");
        }

        $email = Email::find($emailId);
        if (!$email) {
            throw new \RuntimeException("PROCESS_EMAIL: Email not found for id {$emailId}.");
        }

        $job = new ProcessDraftEmailJob($email);
        $dispatch = dispatch($job);
        if (!empty($cfg['on_queue'])) {
            $dispatch->onQueue($cfg['on_queue']);
        }

        Log::info('Workflow PROCESS_EMAIL dispatched ProcessDraftEmailJob', [
            'email_id' => $email->id,
            'workflow_step_id' => $step->id,
        ]);

        return [
            'parsed' => [
                'queued' => true,
                'email_id' => $email->id,
                'job' => ProcessDraftEmailJob::class,
            ],
        ];
    }

    /**
     * Intelligently resolves the email ID from the context.
     *
     * This method provides a more robust way to find the email ID by checking
     * an explicitly configured path first, then falling back to common default paths.
     */
    protected function resolveEmailId(array $context, array $config): ?int
    {
        // 1. Prioritize the explicitly configured path.
        if (!empty($config['email_id_path'])) {
            $emailId = Arr::get($context, $config['email_id_path']);
            if ($emailId) {
                return (int)$emailId;
            }
        }

        // 2. If no specific path is set, try common default locations.
        $defaultPaths = [
            'trigger.email.id',     // Standard for 'email.created' triggers
            'triggering_object_id', // A common top-level identifier
            'trigger.id',           // The original, less specific default
        ];

        foreach ($defaultPaths as $path) {
            $emailId = Arr::get($context, $path);
            if ($emailId) {
                return (int)$emailId;
            }
        }

        // 3. If not found anywhere, return null.
        return null;
    }
}
