<?php

namespace App\Models\Traits;

use Carbon\CarbonImmutable;

/**
 * Trait HasTimezoneCalculations
 *
 * This trait provides a pure, reusable method for timezone-aware comparisons.
 * It is not aware of the logged-in user and requires the timezone to be passed in.
 */
trait HasTimezoneCalculations
{
    /**
     * Checks if a model's timestamp occurred before a specific time of day,
     * in a given timezone.
     *
     * @param  string  $timeOfDay  A time string, e.g., '17:00:00'.
     * @param  string  $timezone  The timezone to use for the comparison.
     * @param  string  $column  The timestamp column to check (e.g., 'created_at', 'updated_at').
     * @return bool True if the timestamp is strictly before the specified time.
     */
    public function isTimestampBeforeTimeInTimezone(
        string $timeOfDay,
        string $timezone,
        string $column = 'created_at'
    ): bool {
        // Retrieve the UTC timestamp from the model's specified column.
        $timestamp = $this->getAttribute($column);

        if (! $timestamp) {
            return false;
        }

        // Convert the UTC timestamp to a CarbonImmutable object.
        $carbonTimestamp = CarbonImmutable::parse($timestamp);

        // Change the timezone of the object to the provided timezone,
        // which correctly adjusts the time value.
        $convertedTimestamp = $carbonTimestamp->setTimezone($timezone);

        // Create a comparison time for the same date as the timestamp,
        // but with the provided time of day, also in the given timezone.
        $comparisonTime = $convertedTimestamp->setHour(explode(':', $timeOfDay)[0])
            ->setMinute(explode(':', $timeOfDay)[1])
            ->setSecond(explode(':', $timeOfDay)[2]);

        // Compare the converted timestamp with the comparison time.
        return $convertedTimestamp->lessThan($comparisonTime);
    }
}
