<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectService;
use App\Models\TransactionType;
use App\Services\ExistingClientEnquiryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateProjectServicesFromJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:migrate-service-details {--dry-run : Preview changes without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfills project_services from projects.service_details JSON in a single transaction.';

    /**
     * Execute the console command.
     */
    public function handle(ExistingClientEnquiryService $enquiryService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $projects = Project::withTrashed()
            ->whereNotNull('service_details')
            ->with('transactions:id,project_id,transaction_type_id')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No projects with service_details JSON found.');

            return self::SUCCESS;
        }

        $summary = [
            'projects' => 0,
            'rows' => 0,
            'missing_enquiry_id' => 0,
            'failed_rows' => 0,
        ];

        DB::beginTransaction();

        try {
            foreach ($projects as $project) {
                $normalized = $enquiryService->normalizeServiceDetails($project->service_details, $project->currency ?? null);

                if ($normalized === []) {
                    continue;
                }

                $summary['projects']++;
                $defaultXeroAccountCode = $this->resolveDefaultXeroAccountCode($project);

                $originalDetails = $project->service_details ?? [];
                $projectNeedsUpdate = false;

                foreach ($normalized as $index => &$detail) {
                    $originalEnquiryId = (string) ($originalDetails[$index]['enquiry_id'] ?? '');
                    $enquiryId = (string) $detail['enquiry_id'];

                    if ($originalEnquiryId === '') {
                        $summary['missing_enquiry_id']++;
                        $projectNeedsUpdate = true;
                        $this->warn("Project {$project->id} had a service row with missing enquiry_id. Generated: {$enquiryId}");
                    }

                    $payload = [
                        'project_id' => $project->id,
                        'enquiry_id' => $enquiryId,
                        'service_id' => (string) $detail['service_id'],
                        'description' => $detail['description'] ?? null,
                        'amount' => is_numeric($detail['amount'] ?? null) ? (float) $detail['amount'] : 0,
                        'currency' => $detail['currency'] ?? $project->currency,
                        'frequency' => $detail['frequency'] ?? 'one_off',
                        'start_date' => $detail['start_date'] ?? null,
                        'payment_breakdown' => $detail['payment_breakdown'] ?? null,
                        'status' => 'active',
                        'service_tracking_type' => $detail['service_tracking_type'] ?? ExistingClientEnquiryService::TRACKING_OPERATIONAL,
                        'show_on_leads_board' => (bool) ($detail['show_on_leads_board'] ?? false),
                        'enquiry_status' => $detail['enquiry_status'] ?? null,
                        'enquiry_created_at' => $detail['enquiry_created_at'] ?? null,
                        'enquiry_updated_at' => $detail['enquiry_updated_at'] ?? null,
                        'enquiry_meta' => is_array($detail['enquiry_meta'] ?? null) ? $detail['enquiry_meta'] : [],
                        'xero_account_code' => $detail['xero_account_code'] ?? $defaultXeroAccountCode,
                    ];

                    if (! $dryRun) {
                        ProjectService::query()->updateOrCreate(
                            [
                                'project_id' => $project->id,
                                'enquiry_id' => $enquiryId,
                            ],
                            $payload
                        );
                    }

                    $summary['rows']++;
                }

                if ($projectNeedsUpdate && ! $dryRun) {
                    $project->update(['service_details' => $normalized]);
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error('Migration failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $mode = $dryRun ? 'Dry run summary' : 'Migration summary';
        $this->info($mode);
        $this->line('Projects scanned: '.$summary['projects']);
        $this->line('Service rows processed: '.$summary['rows']);
        $this->line('Rows with missing enquiry_id: '.$summary['missing_enquiry_id']);
        $this->line('Failed rows: '.$summary['failed_rows']);

        return self::SUCCESS;
    }

    protected function resolveDefaultXeroAccountCode(Project $project): ?string
    {
        $primaryTransactionTypeId = $project->transactions()
            ->whereNotNull('transaction_type_id')
            ->select('transaction_type_id', DB::raw('COUNT(*) as total'))
            ->groupBy('transaction_type_id')
            ->orderByDesc('total')
            ->value('transaction_type_id');

        if (! $primaryTransactionTypeId) {
            return null;
        }

        return TransactionType::query()
            ->whereKey($primaryTransactionTypeId)
            ->value('xero_account_code');
    }
}
