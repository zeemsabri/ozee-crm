<?php

namespace App\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'start_at',
        'end_at',
        'recurrence_pattern',
        'is_active',
        'is_onetime',
        'last_run_at',
        'scheduled_item_id',
        'scheduled_item_type',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'last_run_at' => 'datetime',
        'is_active' => 'boolean',
        'is_onetime' => 'boolean',
    ];

    protected $appends = [
        // Convenience for UI: human-friendly recurrence description
        'recurrence_summary',
    ];

    public function scheduledItem(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithinWindow(Builder $query, ?Carbon $asOf = null): Builder
    {
        $asOf = $asOf ?: now();
        return $query
            ->where('start_at', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $asOf);
            });
    }

    public function isDueAt(?Carbon $asOf = null): bool
    {
//        Log::info($asOf);
//        Log::info($this->start_at);
        $asOf = ($asOf ?: now())->copy()->startOfMinute();
        if (!$this->is_active) return false;
        if ($this->start_at && $this->start_at->gt($asOf)) return false;
        if ($this->end_at && $this->end_at->lt($asOf)) return false;

        // One-time schedules are due exactly at start_at (minute precision)
        if ($this->is_onetime) {
            return $this->start_at && $this->start_at->copy()->startOfMinute()->equalTo($asOf);
        }

        try {
            $cron = new CronExpression($this->recurrence_pattern);
            // CronExpression::isDue supports DateTimeInterface|string
            return $cron->isDue($asOf->toDateTimeString());
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getNextRunAtAttribute(): ?Carbon
    {
        try {
            // Handle one-time schedules more explicitly for clarity
            if ($this->is_onetime) {
                if (!$this->is_active) return null;
                if ($this->last_run_at) return null; // already executed
                if (!$this->start_at) return null;
                return $this->start_at->isFuture() ? $this->start_at->copy() : null;
            }

            $cron = new CronExpression($this->recurrence_pattern);

            // Choose a stable anchor to avoid the perception of "drifting" next run
            $now = now()->startOfMinute();
            $anchor = $now;
            if ($this->last_run_at && $this->last_run_at->greaterThan($anchor)) {
                $anchor = $this->last_run_at->copy();
            }
            if ($this->start_at && $this->start_at->greaterThan($anchor)) {
                $anchor = $this->start_at->copy();
            }

            $next = $cron->getNextRunDate($anchor);
            $carbon = Carbon::instance($next);
            if ($this->end_at && $carbon->gt($this->end_at)) {
                return null;
            }
            return $carbon;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getRecurrenceSummaryAttribute(): string
    {
        // One-time schedule: the most user-friendly
        if ($this->is_onetime) {
            $at = $this->start_at ? $this->start_at->toDateTimeString() : '';
            return $at ? ('Once at ' . $at) : 'Once';
        }

        $expr = trim((string) $this->recurrence_pattern);
        $parts = preg_split('/\s+/', $expr);
        if (!$parts || count($parts) < 5) {
            return 'Custom schedule';
        }
        [$min, $hour, $dom, $mon, $dow] = array_pad($parts, 5, '*');

        $time = null;
        if (ctype_digit($hour) && ctype_digit($min)) {
            $time = sprintf('%02d:%02d', (int) $hour, (int) $min);
        }

        // Yearly: m h DOM MON *
        if (ctype_digit($dom) && ctype_digit($mon) && ($dow === '*' || $dow === '?')) {
            $monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $monthIdx = max(1, min(12, (int) $mon));
            return sprintf('Yearly on %s %d%s', $monthNames[$monthIdx-1], (int) $dom, $time ? ' at ' . $time : '');
        }

        // Monthly: m h DOM * *
        if (ctype_digit($dom) && ($mon === '*')) {
            return sprintf('Monthly on day %d%s', (int) $dom, $time ? ' at ' . $time : '');
        }

        // Weekly: m h * * DOW[,DOW]
        if ($dow !== '*' && $dom === '*' && $mon === '*') {
            $names = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            $days = array_map('intval', explode(',', $dow));
            $days = array_values(array_filter($days, fn($d) => $d >= 0 && $d <= 6));
            $label = $days ? implode(', ', array_map(fn($d) => $names[$d], $days)) : '—';
            return sprintf('Weekly on %s%s', $label, $time ? ' at ' . $time : '');
        }

        // Daily: m h * * *
        if ($dom === '*' && $mon === '*' && $dow === '*') {
            return $time ? ('Daily at ' . $time) : 'Daily';
        }

        return 'Custom cron: ' . $expr;
    }
}
