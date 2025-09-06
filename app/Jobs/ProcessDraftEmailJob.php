<?php

namespace App\Jobs;

use App\Models\Email;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDraftEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Email $email)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\EmailProcessingService $emailService): void
    {
        $emailService->processDraftEmail($this->email);
    }
}
