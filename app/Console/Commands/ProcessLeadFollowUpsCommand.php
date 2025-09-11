<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLeadFollowUpJob;
use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessLeadFollowUpsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:process-follow-ups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finds leads due for a follow-up and dispatches jobs to generate the email.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Searching for leads due for a follow-up...');

        // Find leads that have been contacted, have a next_follow_up_date that is today or in the past,
        // and belong to an active campaign.
        $leadsToFollowUp = Lead::query()
            ->where('status', Lead::STATUS_OUTREACH_SENT) // Only follow up with contacted leads
            ->whereNotNull('next_follow_up_date')
            ->whereDate('next_follow_up_date', '<=', Carbon::today())
            ->whereHas('campaign', function ($query) {
                $query->where('is_active', true);
            })
            ->with('campaign') // Eager load relationships
            ->get();

        if ($leadsToFollowUp->isEmpty()) {
            $this->info('No leads are due for a follow-up today.');
            return;
        }

        $this->info("Found {$leadsToFollowUp->count()} leads due for a follow-up.");

        foreach ($leadsToFollowUp as $lead) {
            $this->info("Dispatching follow-up job for Lead ID: {$lead->id} in Campaign '{$lead->campaign->name}'");

            // Mark the lead as 'processing' to prevent it from being picked up again by this command
            // while the job is running. The job will be responsible for the final status update.
            $lead->update(['status' => Lead::STATUS_PROCESSING]);

            GenerateLeadFollowUpJob::dispatch($lead, $lead->campaign);
        }

        $this->info('All lead follow-up jobs have been dispatched.');
    }
}
