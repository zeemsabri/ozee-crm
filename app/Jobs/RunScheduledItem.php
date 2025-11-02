<?php

namespace App\Jobs;

use App\Contracts\SchedulableAction;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\Workflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunScheduledItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $scheduleId) {}

    public function handle(): void
    {
        /** @var Schedule|null $schedule */
        $schedule = Schedule::query()->with('scheduledItem')->find($this->scheduleId);
        if (! $schedule) {
            return;
        }

        $target = $schedule->scheduledItem;
        if (! $target) {
            return;
        }

        try {
            // Prefer explicit contract
            if ($target instanceof SchedulableAction) {
                $target->runScheduled($schedule);
            } elseif (method_exists($target, 'runScheduled')) {
                $target->runScheduled($schedule);
            } elseif ($target instanceof Workflow) {
                // Reuse existing workflow job
                \App\Jobs\RunWorkflowJob::dispatch($target->id);
            } elseif ($target instanceof Task) {
                // Create a child task (subtask) using the parent task as a template
                $target->spawnChildFromTemplate();

            } elseif (method_exists($target, 'run')) {
                $target->run();
            } elseif (method_exists($target, 'execute')) {
                $target->execute();
            } else {
                Log::warning('Scheduled item has no executable method', [
                    'schedule_id' => $schedule->id,
                    'type' => get_class($target),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Error executing scheduled item', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Post-execution bookkeeping
        $schedule->last_run_at = now();
        if ($schedule->is_onetime) {
            $schedule->is_active = false;
        }
        $schedule->save();
    }
}
