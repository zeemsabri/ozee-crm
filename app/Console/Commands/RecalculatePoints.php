<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Models\Kudo;
use App\Models\Milestone;
use App\Models\PointsLedger;
use App\Models\ProjectNote;
use App\Models\Task;
use App\Services\PointsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculatePoints extends Command
{
    protected $signature = 'points:recalculate {--from=} {--to=} {--dry-run}';
    protected $description = 'Recalculates points for all pointable models, creating or updating ledger entries as needed.';

    /**
     * @var PointsService
     */
    protected $pointsService;

    public function __construct(PointsService $pointsService)
    {
        parent::__construct();
        $this->pointsService = $pointsService;
    }

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $fromInput = $this->option('from');
        $toInput = $this->option('to');

        $startDate = $fromInput ? Carbon::parse($fromInput)->startOfDay() : null;
        $endDate = $toInput ? Carbon::parse($toInput)->endOfDay() : Carbon::now();

        $this->info("Recalculating points from " . ($startDate ? $startDate->toDateString() : 'the beginning') . " to {$endDate->toDateString()}" . ($dryRun ? ' (dry run)' : ''));

        $modelsToProcess = [
            ProjectNote::class => 'Standups',
            Task::class => 'Tasks',
            Milestone::class => 'Milestones',
            Kudo::class => 'Kudos',
            Email::class => 'Emails',
        ];

        foreach ($modelsToProcess as $modelClass => $modelName) {
            $this->info("Processing {$modelName}...");

            $query = $modelClass::query();
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate->toDateString());
            }
            $query->whereDate('created_at', '<=', $endDate->toDateString())
                ->chunkById(100, function ($items) use ($modelName, $dryRun) {
                    foreach ($items as $item) {
                        $this->line(" - Processing {$modelName} #{$item->id}");
                        if (!$dryRun) {
                            $this->pointsService->recalculateAndAwardPoints($item);
                        }
                    }
                });
        }

        $this->info('Recalculation complete.');
        return self::SUCCESS;
    }
}
