<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLeadOutreachJob;
use App\Models\Lead;
use Illuminate\Console\Command;

class ProcessNewLeadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:process-new';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finds new leads in active campaigns and dispatches jobs to generate the initial outreach email.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Searching for new leads in active campaigns...');

        // Find leads with status 'new' that are part of an active campaign.
        $newLeads = Lead::query()
            ->where('status', Lead::STATUS_NEW)
            ->whereNotNull('email')
            ->whereHas('campaign', function ($query) {
                $query->where('is_active', true);
            })
            ->with('campaign') // Eager load the campaign relationship
            ->get();

        if ($newLeads->isEmpty()) {
            $this->info('No new leads to process.');

            return;
        }

        $this->info("Found {$newLeads->count()} new leads to process.");

        foreach ($newLeads as $lead) {
            $this->info("Dispatching outreach job for Lead ID: {$lead->id} in Campaign '{$lead->campaign->name}'");

            // Mark the lead as 'processing' to prevent it from being picked up again
            $lead->update(['status' => Lead::STATUS_PROCESSING]);

            GenerateLeadOutreachJob::dispatch($lead, $lead->campaign);
        }

        $this->info('All new lead processing jobs have been dispatched.');
    }
}
