<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\Task;
use App\Models\Workflow;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ScheduleController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $userTz = $this->getUserTimezone();
        $query = Schedule::query()->with('scheduledItem')->orderByDesc('id');

        if ($type = $request->string('type')->toString()) {
            $query->where('scheduled_item_type', $this->normalizeType($type));
        }
        if ($id = $request->integer('id')) {
            $query->where('scheduled_item_id', $id);
        }

        $schedules = $query->paginate(20)->through(function (Schedule $s) use ($userTz) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'start_at' => $this->toUserString($s->start_at, $userTz),
                'end_at' => $this->toUserString($s->end_at, $userTz),
                'recurrence_summary' => $this->recurrenceSummaryForUser($s, $userTz),
                'recurrence_pattern' => $s->recurrence_pattern,
                'is_active' => $s->is_active,
                'is_onetime' => $s->is_onetime,
                'last_run_at' => $this->toUserString($s->last_run_at, $userTz),
                'next_run_at' => $this->toUserString($s->next_run_at, $userTz),
                'scheduled_item' => $s->scheduledItem ? [
                    'type' => class_basename($s->scheduled_item_type),
                    'id' => $s->scheduled_item_id,
                    'name' => $this->getSchedulableName($s->scheduledItem),
                ] : null,
            ];
        });

        return Inertia::render('Schedules/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $prefill = [
            'scheduled_item_type' => $request->string('type')->toString(),
            'scheduled_item_id' => $request->integer('id') ?: null,
        ];

        return Inertia::render('Schedules/Create', [
            'prefill' => $prefill,
            'schedulableTypes' => [
                ['label' => 'Task', 'value' => 'task'],
                ['label' => 'Workflow', 'value' => 'workflow'],
            ],
            'tasks' => Task::query()->select('id', 'name')->orderByDesc('id')->limit(50)->get(),
            'workflows' => Workflow::query()->select('id', 'name')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function edit(Request $request, Schedule $schedule): InertiaResponse
    {
        $userTz = $this->getUserTimezone();

        return Inertia::render('Schedules/Edit', [
            'schedule' => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'description' => $schedule->description,
                'scheduled_item_type' => class_basename($schedule->scheduled_item_type),
                'scheduled_item_id' => $schedule->scheduled_item_id,
                'start_at' => $this->toUserInputValue($schedule->start_at, $userTz),
                'end_at' => $this->toUserInputValue($schedule->end_at, $userTz),
                'recurrence_pattern' => $schedule->recurrence_pattern,
                'is_active' => $schedule->is_active,
                'is_onetime' => $schedule->is_onetime,
            ],
            'schedulableTypes' => [
                ['label' => 'Task', 'value' => 'task'],
                ['label' => 'Workflow', 'value' => 'workflow'],
            ],
            'tasks' => Task::query()->select('id', 'name')->orderByDesc('id')->limit(50)->get(),
            'workflows' => Workflow::query()->select('id', 'name')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_item_type' => ['required', 'string'], // 'task' | 'workflow' | FQCN
            'scheduled_item_id' => ['required', 'integer'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            // recurrence builder fields
            'mode' => ['required', 'in:once,daily,weekly,monthly,yearly,cron'],
            'time' => ['nullable', 'string'], // HH:MM
            'days_of_week' => ['array'],
            'days_of_week.*' => ['integer', 'between:0,6'], // 0=Sun..6=Sat
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'nth' => ['nullable', 'integer', 'between:1,5'], // 1-5 (5=last)
            'dow_for_monthly' => ['nullable', 'integer', 'between:0,6'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'cron' => ['nullable', 'string'],
        ]);

        $userTz = $this->getUserTimezone();

        // Server-side validation: prevent selecting a past date/time (in user's timezone)
        try {
            $startAtUser = Carbon::parse($data['start_at'], $userTz);
            $nowUser = now($userTz);
            if ($startAtUser->lt($nowUser)) {
                return back()->withErrors([
                    'start_at' => 'Start at must be in the future based on your timezone ('.$userTz.').',
                ])->withInput();
            }
        } catch (\Throwable $e) {
            // If parsing fails for some reason, let the standard validator message stand
        }

        $fqcn = $this->normalizeType($data['scheduled_item_type']);
        // Convert input datetimes from user tz to app tz for storage
        $startAtApp = $this->toAppCarbon($data['start_at'], $userTz);
        $endAtApp = ! empty($data['end_at']) ? $this->toAppCarbon($data['end_at'], $userTz) : null;

        $recurrence = $this->buildCronFromData($data, $userTz, $startAtApp);
        $isOnetime = ($data['mode'] === 'once');

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
        $schedule->scheduled_item_id = $data['scheduled_item_id'];
        $schedule->save();

        return redirect()->route('schedules.index')
            ->with('success', 'Schedule created successfully');
    }

    public function update(Request $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_item_type' => ['required', 'string'],
            'scheduled_item_id' => ['required', 'integer'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'mode' => ['required', 'in:once,daily,weekly,monthly,yearly,cron'],
            'time' => ['nullable', 'string'],
            'days_of_week' => ['array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'nth' => ['nullable', 'integer', 'between:1,5'],
            'dow_for_monthly' => ['nullable', 'integer', 'between:0,6'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'cron' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $userTz = $this->getUserTimezone();

        // Server-side validation: prevent selecting a past date/time (in user's timezone)
        try {
            $startAtUser = Carbon::parse($data['start_at'], $userTz);
            $nowUser = now($userTz);
            if ($startAtUser->lt($nowUser)) {
                return back()->withErrors([
                    'start_at' => 'Start at must be in the future based on your timezone ('.$userTz.').',
                ])->withInput();
            }
        } catch (\Throwable $e) {
            // If parsing fails for some reason, let the standard validator message stand
        }

        $fqcn = $this->normalizeType($data['scheduled_item_type']);
        $startAtApp = $this->toAppCarbon($data['start_at'], $userTz);
        $endAtApp = ! empty($data['end_at']) ? $this->toAppCarbon($data['end_at'], $userTz) : null;

        $recurrence = $this->buildCronFromData($data, $userTz, $startAtApp);
        $isOnetime = ($data['mode'] === 'once');

        $schedule->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_at' => $startAtApp,
            'end_at' => $endAtApp,
            'recurrence_pattern' => $recurrence,
            'is_onetime' => $isOnetime,
        ]);
        $schedule->scheduled_item_type = $fqcn;
        $schedule->scheduled_item_id = $data['scheduled_item_id'];
        if (array_key_exists('is_active', $data)) {
            $schedule->is_active = (bool) $data['is_active'];
        }
        $schedule->save();

        return redirect()->route('schedules.index')->with('success', 'Schedule updated successfully');
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('schedules.index')->with('success', 'Schedule deleted');
    }

    public function toggle(Schedule $schedule): RedirectResponse
    {
        $schedule->is_active = ! $schedule->is_active;
        $schedule->save();

        return redirect()->back()->with('success', 'Schedule '.($schedule->is_active ? 'activated' : 'deactivated'));
    }

    private function normalizeType(string $type): string
    {
        $t = strtolower($type);

        return match ($t) {
            'task', 'app\\models\\task' => Task::class,
            'workflow', 'app\\models\\workflow' => Workflow::class,
            default => $type, // assume FQCN already
        };
    }

    private function getSchedulableName($model): string
    {
        if ($model instanceof Task) {
            return (string) ($model->title ?? ("Task #{$model->id}"));
        }
        if ($model instanceof Workflow) {
            return (string) ($model->name ?? ("Workflow #{$model->id}"));
        }

        return method_exists($model, 'getName') ? (string) $model->getName() : class_basename($model).' #'.$model->id;
    }

    private function buildCronFromData(array $data, string $userTz, ?Carbon $startAtApp = null): string
    {
        $appTz = config('app.timezone');
        // Determine reference date for timezone conversion (DST-safe)
        $refDate = $startAtApp?->copy()->setTimezone($userTz)->toDateString() ?? now($userTz)->toDateString();

        $time = $data['time'] ?? '00:00';
        if (($data['mode'] ?? '') === 'once') {
            // For once, take time from start_at (already converted to app tz via $startAtApp)
            $hour = $startAtApp ? (int) $startAtApp->copy()->setTimezone($appTz)->format('H') : 0;
            $minute = $startAtApp ? (int) $startAtApp->copy()->setTimezone($appTz)->format('i') : 0;
        } else {
            // Build a datetime in user's tz and convert to app tz to extract H/M
            [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);
            $userDt = Carbon::parse($refDate.' '.sprintf('%02d:%02d', $h, $m), $userTz);
            $appDt = $userDt->clone()->setTimezone($appTz);
            $hour = (int) $appDt->format('H');
            $minute = (int) $appDt->format('i');
        }

        switch ($data['mode']) {
            case 'once':
                return sprintf('%d %d * * *', $minute, $hour);
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
                if (! empty($data['day_of_month'])) {
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
                return trim($data['cron'] ?? '* * * * *');
            default:
                return '* * * * *';
        }
    }

    private function getUserTimezone(): string
    {
        $user = Auth::user();

        return $user && ! empty($user->timezone) ? $user->timezone : config('app.timezone');
    }

    private function toAppCarbon(string $dateTimeInput, string $userTz): Carbon
    {
        // datetime-local input has no timezone; treat as user timezone then convert to app timezone
        $dt = Carbon::parse($dateTimeInput, $userTz);

        return $dt->clone()->setTimezone(config('app.timezone'));
    }

    private function toUserString($carbonOrNull, string $userTz): ?string
    {
        if (! $carbonOrNull) {
            return null;
        }

        return Carbon::parse($carbonOrNull)->setTimezone($userTz)->toDateTimeString();
    }

    private function toUserInputValue($carbonOrNull, string $userTz): ?string
    {
        // Format for datetime-local input: YYYY-MM-DDTHH:MM
        if (! $carbonOrNull) {
            return null;
        }

        return Carbon::parse($carbonOrNull)->setTimezone($userTz)->format('Y-m-d\TH:i');
    }

    private function recurrenceSummaryForUser(Schedule $s, string $userTz): string
    {
        // For one-time schedules, prefer the actual start_at in user's timezone
        if ($s->is_onetime) {
            $at = $this->toUserString($s->start_at, $userTz);

            return $at ? ('Once at '.$at) : 'Once';
        }

        $expr = trim((string) $s->recurrence_pattern);
        $parts = preg_split('/\s+/', $expr);
        if (! $parts || count($parts) < 5) {
            return 'Custom schedule';
        }
        [$min, $hour, $dom, $mon, $dow] = array_pad($parts, 5, '*');

        // Convert the cron time (stored in app tz) into user's timezone for display
        $time = null;
        if (ctype_digit($hour) && ctype_digit($min)) {
            $appTz = config('app.timezone');
            $refDate = $s->start_at ? Carbon::parse($s->start_at)->setTimezone($userTz)->toDateString() : now($userTz)->toDateString();
            $appDt = Carbon::parse($refDate.' '.sprintf('%02d:%02d:00', (int) $hour, (int) $min), $appTz);
            $userDt = $appDt->copy()->setTimezone($userTz);
            $time = $userDt->format('H:i');
        }

        // Yearly: m h DOM MON *
        if (ctype_digit($dom) && ctype_digit($mon) && ($dow === '*' || $dow === '?')) {
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthIdx = max(1, min(12, (int) $mon));

            return sprintf('Yearly on %s %d%s', $monthNames[$monthIdx - 1], (int) $dom, $time ? ' at '.$time : '');
        }

        // Monthly: m h DOM * *
        if (ctype_digit($dom) && ($mon === '*')) {
            return sprintf('Monthly on day %d%s', (int) $dom, $time ? ' at '.$time : '');
        }

        // Weekly: m h * * DOW[,DOW]
        if ($dow !== '*' && $dom === '*' && $mon === '*') {
            $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $days = array_map('intval', explode(',', $dow));
            $days = array_values(array_filter($days, fn ($d) => $d >= 0 && $d <= 6));
            $label = $days ? implode(', ', array_map(fn ($d) => $names[$d], $days)) : '—';

            return sprintf('Weekly on %s%s', $label, $time ? ' at '.$time : '');
        }

        // Daily: m h * * *
        if ($dom === '*' && $mon === '*' && $dow === '*') {
            return $time ? ('Daily at '.$time) : 'Daily';
        }

        return 'Custom cron: '.$expr;
    }
}
