<?php

namespace App\Services\StepHandlers;

use App\Jobs\ProcessDraftEmailJob;
use App\Models\Email;
use App\Models\ExecutionLog;
use App\Models\WorkflowStep;
use App\Services\WorkflowEngineService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ProcessEmailStepHandler implements StepHandlerContract
{
    public function __construct(
        protected ?WorkflowEngineService $engine = null
    ) {}

    /**
     * Handle the PROCESS_EMAIL action step.
     */
    public function handle(array $context, WorkflowStep $step, ExecutionLog|null $execLog = null): array
    {
        $cfg = $step->step_config ?? [];
        $emailId = $this->resolveEmailId($context, $cfg);

        if (!$emailId) {
            $configuredEmailId = $cfg['email_id'] ?? 'none';
            $configuredPath = $cfg['email_id_path'] ?? 'none';
            throw new \RuntimeException("PROCESS_EMAIL: Unable to resolve a valid email ID. Configured email_id: '{$configuredEmailId}', configured path: '{$configuredPath}'. Tried default locations: 'email.id', 'trigger.email.id', 'triggering_object_id'.");
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
     * direct configuration first, then template resolution, then fallback paths.
     */
    protected function resolveEmailId(array $context, array $config): ?int
    {
        // 1. Check for direct email_id configuration (with template support)
        if (!empty($config['email_id'])) {
            $emailIdValue = $config['email_id'];
            
            // If we have the engine, use template resolution
            if ($this->engine) {
                $resolvedValue = $this->engine->getTemplatedValue($emailIdValue, $context);
                if ($resolvedValue) {
                    return (int)$resolvedValue;
                }
            } else {
                // Fallback: if it's a simple integer, use it directly
                if (is_numeric($emailIdValue)) {
                    return (int)$emailIdValue;
                }
            }
        }

        // 2. Legacy support: explicitly configured path
        if (!empty($config['email_id_path'])) {
            $emailId = Arr::get($context, $config['email_id_path']);
            if ($emailId) {
                return (int)$emailId;
            }
        }

        // 3. Try common default locations in order of preference
        $defaultPaths = [
            'email.id',             // Most common: direct email context
            'trigger.email.id',     // Standard for 'email.created' triggers  
            'triggering_object_id', // Fallback identifier (can be wrong!)
        ];

        foreach ($defaultPaths as $path) {
            $emailId = Arr::get($context, $path);
            if ($emailId) {
                return (int)$emailId;
            }
        }

        // 4. If not found anywhere, return null
        return null;
    }
}
