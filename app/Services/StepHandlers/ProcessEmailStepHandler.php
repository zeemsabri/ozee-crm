<?php

namespace App\Services\StepHandlers;

use App\Jobs\ProcessDraftEmailJob;
use App\Models\Email;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\Log;

class ProcessEmailStepHandler implements StepHandlerContract
{
    /**
     * Handle the PROCESS_EMAIL action step.
     *
     * Expected configuration on the step (step_config):
     * - email_id_path (string, optional): context path to the email id. Defaults to 'trigger.id'.
     * - on_queue (string, optional): queue name to dispatch the job to.
     */
    public function handle(array $context, WorkflowStep $step): array
    {
        $cfg = $step->step_config ?? [];
        $idPath = $cfg['email_id_path'] ?? 'trigger.id';

        $emailId = $this->getFromContextPath($context, $idPath);
        if (!$emailId) {
            throw new \RuntimeException("PROCESS_EMAIL: Unable to resolve email id from path '{$idPath}'.");
        }

        $email = Email::find($emailId);
        if (!$email) {
            throw new \RuntimeException("PROCESS_EMAIL: Email not found for id {$emailId}.");
        }

        // Dispatch the existing job which preserves all current processing logic/templates
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
     * Minimal context path resolver (dot notation), mirroring engine behavior.
     */
    protected function getFromContextPath(array $context, string $path)
    {
        if ($path === '') return null;
        $parts = preg_split('/\.|\:/', $path);
        $val = $context;
        foreach ($parts as $p) {
            if (is_array($val) && array_key_exists($p, $val)) {
                $val = $val[$p];
            } else {
                return null;
            }
        }
        return $val;
    }
}
