<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Schedule;
use App\Models\Task;
use App\Models\Workflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait HandlesSchedules
{
    protected function normalizeSchedulableType(string $type): string
    {
        $t = strtolower($type);
        return match ($t) {
            'task', 'app\\models\\task' => Task::class,
            'workflow', 'app\\models\\workflow' => Workflow::class,
            default => $type,
        };
    }

    protected function getUserTimezoneForRequest(): string
    {
        $user = Auth::user();
        return $user && !empty($user->timezone) ? $user->timezone : config('app.timezone');
    }

    protected function toAppCarbonFromUser(string $dateTimeInput, string $userTz): Carbon
    {
        $dt = Carbon::parse($dateTimeInput, $userTz);
        return $dt->clone()->setTimezone(config('app.timezone'));
    }

    protected function buildCronFromData(array $data, string $userTz, ?Carbon $startAtApp = null): string
    {
        $appTz = config('app.timezone');
        $refDate = $startAtApp?->copy()->setTimezone($userTz)->toDateString() ?? now($userTz)->toDateString();

        $time = $data['time'] ?? '00:00';
        if (($data['mode'] ?? '') === 'once') {
            $hour = $startAtApp ? (int) $startAtApp->copy()->setTimezone($appTz)->format('H') : 0;
            $minute = $startAtApp ? (int) $startAtApp->copy()->setTimezone($appTz)->format('i') : 0;
        } else {
            [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);
            $userDt = Carbon::parse($refDate . ' ' . sprintf('%02d:%02d', $h, $m), $userTz);
            $appDt = $userDt->clone()->setTimezone($appTz);
            $hour = (int) $appDt->format('H');
            $minute = (int) $appDt->format('i');
        }

        switch ($data['mode'] ?? 'cron') {
            case 'once':
            case 'daily':
                return sprintf('%d %d * * *', $minute, $hour);
            case 'weekly':
                $dows = $data['days_of_week'] ?? [];
                if (empty($dows)) {
                    $dows = [1];
                }
                sort($dows);
                $dowStr = implode(',', $dows);
                return sprintf('%d %d * * %s', $minute, $hour, $dowStr);
            case 'monthly':
                if (!empty($data['day_of_month'])) {
                    $dom = (int) $data['day_of_month'];
                    return sprintf('%d %d %d * *', $minute, $hour, $dom);
                }
                $dow = (int) ($data['dow_for_monthly'] ?? 1);
                return sprintf('%d %d * * %d', $minute, $hour, $dow);
            case 'yearly':
                $month = (int) ($data['month'] ?? 1);
                $dom = (int) ($data['day_of_month'] ?? 1);
                return sprintf('%d %d %d %d *', $minute, $hour, $dom, $month);
            case 'cron':
            default:
                return trim($data['cron'] ?? '* * * * *');
        }
    }

    protected function persistScheduleFromArray(array $data): Schedule
    {
        $userTz = $this->getUserTimezoneForRequest();

        // Validate past date
        try {
            $startAtUser = Carbon::parse($data['start_at'], $userTz);
            $nowUser = now($userTz);
            if ($startAtUser->lt($nowUser)) {
                abort(422, 'Start at must be in the future based on your timezone (' . $userTz . ').');
            }
        } catch (\Throwable $e) {
            // ignore; validator will catch bad date
        }

        $fqcn = $this->normalizeSchedulableType($data['scheduled_item_type']);
        $startAtApp = $this->toAppCarbonFromUser($data['start_at'], $userTz);
        $endAtApp = !empty($data['end_at']) ? $this->toAppCarbonFromUser($data['end_at'], $userTz) : null;
        $recurrence = $this->buildCronFromData($data, $userTz, $startAtApp);
        $isOnetime = ($data['mode'] ?? '') === 'once';

        $schedule = new Schedule([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_at' => $startAtApp,
            'end_at' => $endAtApp,
            'recurrence_pattern' => $recurrence,
            'is_active' => true,
            'is_onetime' => $isOnetime,
        ]);
        $schedule->scheduled_item_type = $fqcn;
        $schedule->scheduled_item_id = (int) $data['scheduled_item_id'];
        $schedule->save();

        return $schedule;
    }
}
