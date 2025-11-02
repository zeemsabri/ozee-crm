<?php

namespace App\Models\Traits;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Trait HasUserTimezone
 *
 * This trait provides a suite of helper functions and query scopes to
 * handle date and time operations sensitive to the currently authenticated
 * user's timezone.
 *
 * All database timestamps are assumed to be in UTC.
 */
trait HasUserTimezone
{
    /**
     * Converts a model's timestamp to the authenticated user's timezone.
     *
     * @param  string  $column  The timestamp column to convert (e.g., 'created_at', 'updated_at').
     * @return CarbonImmutable|null A CarbonImmutable instance in the user's timezone, or null if the timestamp is not set.
     */
    public function convertToUserTimezone(string $column = 'created_at'): ?CarbonImmutable
    {
        // Get the timestamp from the model attribute.
        $timestamp = $this->getAttribute($column);

        if (! $timestamp) {
            return null;
        }

        // Return a new CarbonImmutable instance with the user's timezone.
        return CarbonImmutable::parse($timestamp)->setTimezone($this->getUserTimezone());
    }

    /**
     * Checks if a model's timestamp occurred before a specific time of day in the user's timezone.
     *
     * @param  string  $timeOfDay  A time string, e.g., '17:00:00'.
     * @param  string  $column  The timestamp column to check.
     * @return bool True if the timestamp is strictly before the specified time.
     */
    public function isBeforeUserTime(string $timeOfDay, string $column = 'created_at'): bool
    {
        // Get the model's timestamp converted to the user's timezone.
        $modelTimestamp = $this->convertToUserTimezone($column);

        if (! $modelTimestamp) {
            return false;
        }

        // Create a target Carbon instance for the same date as the model's timestamp,
        // but with the specified time of day. Use CarbonImmutable for safety.
        $targetTime = CarbonImmutable::parse($modelTimestamp->toDateString().' '.$timeOfDay, $this->getUserTimezone());

        // Compare the model's timestamp with the target time.
        return $modelTimestamp->lt($targetTime);
    }

    /**
     * Checks if a model's timestamp falls on the same calendar day as a given date,
     * according to the user's timezone.
     *
     * @param  Carbon  $comparisonDate  The date to compare against.
     * @param  string  $column  The timestamp column to check.
     * @return bool True if the timestamp is on the same day.
     */
    public function isOnSameUserDateAs(Carbon $comparisonDate, string $column = 'created_at'): bool
    {
        // Get the model's timestamp converted to the user's timezone.
        $modelTimestamp = $this->convertToUserTimezone($column);

        if (! $modelTimestamp) {
            return false;
        }

        // Use Carbon's built-in comparison to check for the same day.
        return $modelTimestamp->isSameDay($comparisonDate);
    }

    /**
     * Scope a query to retrieve records that occurred on a specific date,
     * as defined by the user's timezone.
     *
     * @param  Builder  $query  The Eloquent query builder instance.
     * @param  Carbon  $date  The date in the user's timezone.
     * @param  string  $column  The timestamp column to query.
     */
    public function scopeWhereOnUserDate(Builder $query, Carbon $date, string $column = 'created_at'): Builder
    {
        // Get the user's timezone.
        $userTimezone = $this->getUserTimezone();

        // Tell Carbon to parse the date string using the user's timezone as context.
        $startOfDay = CarbonImmutable::parse($date, $userTimezone)->startOfDay();
        $endOfDay = CarbonImmutable::parse($date, $userTimezone)->endOfDay();

        // Convert these two boundaries back to UTC for the database query.
        $startUtc = $startOfDay->setTimezone('UTC');
        $endUtc = $endOfDay->setTimezone('UTC');

        // Use whereBetween() to find all records within this UTC range.
        return $query->whereBetween($column, [$startUtc, $endUtc]);
    }

    /**
     * Scope a query to retrieve records that occurred before a specific timestamp,
     * as defined by the user's timezone.
     *
     * @param  Builder  $query  The Eloquent query builder instance.
     * @param  Carbon  $userTimestamp  The timestamp in the user's timezone.
     * @param  string  $column  The timestamp column to query.
     */
    public function scopeWhereBeforeUserTimestamp(Builder $query, Carbon $userTimestamp, string $column = 'created_at'): Builder
    {
        // Take the user's timestamp and convert it to its UTC equivalent.
        $utcTimestamp = $userTimestamp->setTimezone('UTC');

        // Perform the simple where clause using the UTC timestamp.
        return $query->where($column, '<', $utcTimestamp);
    }

    /**
     * Safely retrieves the authenticated user's timezone, with a fallback.
     *
     * @return string The user's timezone or the application's default.
     */
    private function getUserTimezone(): string
    {
        $user = Auth::user();

        return $user && ! empty($user->timezone)
            ? $user->timezone
            : config('app.timezone');
    }
}
