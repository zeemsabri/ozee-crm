# Availability Prompt Improvement Documentation

## Issue Description

The original implementation of the `shouldShowPrompt` method in the `AvailabilityController` only checked if the user had submitted any availability for the next week. However, the requirement is to ensure that users have submitted availability for all weekdays (Monday to Friday) or at least one entry per day from Monday to Friday. If they haven't, the prompt should still be shown.

## Original Implementation

The original implementation of the `shouldShowPrompt` method was:

```php
public function shouldShowPrompt()
{
    $user = Auth::user();
    $today = Carbon::now();
    $nextWeekStart = Carbon::now()->addWeek()->startOfWeek();
    $nextWeekEnd = Carbon::now()->addWeek()->endOfWeek();

    // Check if today is Thursday
    $isThursday = $today->dayOfWeek === Carbon::THURSDAY;

    // Check if user has already submitted availability for next week
    $hasSubmittedForNextWeek = UserAvailability::where('user_id', $user->id)
        ->whereBetween('date', [$nextWeekStart->format('Y-m-d'), $nextWeekEnd->format('Y-m-d')])
        ->exists();

    return response()->json([
        'should_show_prompt' => $isThursday && !$hasSubmittedForNextWeek,
        'next_week_start' => $nextWeekStart->format('Y-m-d'),
        'next_week_end' => $nextWeekEnd->format('Y-m-d')
    ]);
}
```

This implementation only checked if any availability existed for the next week, but it didn't check if the user had submitted availability for all weekdays (Monday to Friday).

## Improved Implementation

The improved implementation of the `shouldShowPrompt` method is:

```php
public function shouldShowPrompt()
{
    $user = Auth::user();
    $today = Carbon::now();
    $nextWeekStart = Carbon::now()->addWeek()->startOfWeek();
    $nextWeekEnd = Carbon::now()->addWeek()->endOfWeek();

    // Check if today is Thursday
    $isThursday = $today->dayOfWeek === Carbon::THURSDAY;

    // Get all availability entries for next week
    $nextWeekAvailabilities = UserAvailability::where('user_id', $user->id)
        ->whereBetween('date', [$nextWeekStart->format('Y-m-d'), $nextWeekEnd->format('Y-m-d')])
        ->get();

    // Extract the weekdays (1-5 for Monday-Friday) for which the user has submitted availability
    $weekdaysWithAvailability = [];
    foreach ($nextWeekAvailabilities as $availability) {
        $weekday = Carbon::parse($availability->date)->dayOfWeek;
        // Only consider weekdays (Monday to Friday, which are 1-5 in Carbon)
        if ($weekday >= 1 && $weekday <= 5) {
            $weekdaysWithAvailability[] = $weekday;
        }
    }
    
    // Count unique weekdays with availability
    $uniqueWeekdaysWithAvailability = array_unique($weekdaysWithAvailability);
    
    // Check if all weekdays (Monday to Friday) have at least one availability entry
    $allWeekdaysCovered = count($uniqueWeekdaysWithAvailability) >= 5;

    return response()->json([
        'should_show_prompt' => $isThursday && !$allWeekdaysCovered,
        'next_week_start' => $nextWeekStart->format('Y-m-d'),
        'next_week_end' => $nextWeekEnd->format('Y-m-d'),
        'weekdays_covered' => $uniqueWeekdaysWithAvailability,
        'all_weekdays_covered' => $allWeekdaysCovered
    ]);
}
```

This implementation:

1. Retrieves all availability entries for the next week
2. Extracts the weekday for each entry using Carbon's `dayOfWeek` method
3. Only considers weekdays (Monday to Friday, which are 1-5 in Carbon)
4. Counts the number of unique weekdays for which the user has submitted availability
5. Checks if all weekdays (Monday to Friday) are covered by comparing the count of unique weekdays with 5 (the number of weekdays)
6. Returns additional information in the response to help with debugging and testing

## Changes Made

The key changes made to the `shouldShowPrompt` method are:

1. Instead of using `exists()` to check if any availability exists, we now retrieve all availability entries for the next week using `get()`.
2. We extract the weekday for each entry and only consider weekdays (Monday to Friday).
3. We count the number of unique weekdays for which the user has submitted availability.
4. We check if all weekdays (Monday to Friday) are covered by comparing the count of unique weekdays with 5.
5. We've added additional information to the response to help with debugging and testing.

## Testing

A test script has been created to verify that the improved `shouldShowPrompt` method works correctly: `test-availability-prompt-improvement.php`

The script tests the following scenarios:

1. User has no availability entries for next week
2. User has availability entries for some weekdays but not all
3. User has availability entries for all weekdays (Monday to Friday)

To run the test script:

```bash
php test-availability-prompt-improvement.php
```

## Benefits

The improved implementation of the `shouldShowPrompt` method ensures that users are prompted to submit availability for all weekdays (Monday to Friday) in the next week. This helps in planning meetings and work schedules more effectively, as it ensures that there's at least one availability entry for each weekday.

## Additional Notes

- The `shouldShowPrompt` method is called from the frontend to determine if the availability prompt should be shown on the dashboard.
- The prompt is only shown on Thursdays, as that's when users are expected to submit their availability for the next week.
- The improved implementation adds additional information to the response, which can be useful for debugging and testing.
- The implementation uses Carbon's `dayOfWeek` method, which returns a number from 0 to 6 (0 for Sunday, 1 for Monday, etc.).
- The implementation only considers weekdays (Monday to Friday, which are 1-5 in Carbon) when checking if all weekdays are covered.
