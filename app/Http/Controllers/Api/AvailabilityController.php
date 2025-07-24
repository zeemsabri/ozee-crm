<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAvailability;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AvailabilityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Check if a specific week is requested
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // If no dates provided, default to current week
        if (!$startDate) {
            $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
            $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
        } elseif (!$endDate) {
            // If only start date is provided, set end date to 7 days later
            $endDate = Carbon::parse($startDate)->addDays(6)->format('Y-m-d');
        }

        // Base query
        $query = UserAvailability::with('user:id,name')->whereBetween('date', [$startDate, $endDate]);

        // If user_id is provided and user is admin/manager, filter by that user
        if ($request->has('user_id') && ($user->isSuperAdmin() || $user->isManager())) {
            $query->where('user_id', $request->input('user_id'));
        } else {
            // Regular users can only see their own availabilities
            if (!$user->isSuperAdmin() && !$user->isManager()) {
                $query->where('user_id', $user->id);
            }
        }

        $availabilities = $query->orderBy('date')->get();

        return response()->json([
            'availabilities' => $availabilities,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Validate request data
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'is_available' => 'required|boolean',
            'reason' => 'required_if:is_available,false|nullable|string',
            'time_slots' => 'required_if:is_available,true|nullable|array',
            'time_slots.*.start_time' => 'required_with:time_slots|string|date_format:H:i',
            'time_slots.*.end_time' => 'required_with:time_slots|string|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if availability already exists for this date
        $existingAvailability = UserAvailability::where('user_id', $user->id)
            ->where('date', $request->date)
            ->first();

        if ($existingAvailability) {
            return response()->json([
                'message' => 'Availability already exists for this date. Please update the existing record.',
                'availability' => $existingAvailability
            ], 409);
        }

        // Create new availability
        $availability = new UserAvailability();
        $availability->user_id = $user->id;
        $availability->date = $request->date;
        $availability->is_available = $request->is_available;
        $availability->reason = $request->is_available ? null : $request->reason;
        $availability->time_slots = $request->is_available ? $request->time_slots : null;
        $availability->save();

        return response()->json([
            'message' => 'Availability saved successfully',
            'availability' => $availability
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $availability = UserAvailability::findOrFail($id);

        // Check if user is authorized to view this availability
        if ($availability->user_id !== $user->id && !$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['availability' => $availability]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        $availability = UserAvailability::findOrFail($id);

        // Check if user is authorized to update this availability
        if ($availability->user_id !== $user->id && !$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate request data
        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|date',
            'is_available' => 'sometimes|boolean',
            'reason' => 'required_if:is_available,false|nullable|string',
            'time_slots' => 'required_if:is_available,true|nullable|array',
            'time_slots.*.start_time' => 'required_with:time_slots|string|date_format:H:i',
            'time_slots.*.end_time' => 'required_with:time_slots|string|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update availability
        if ($request->has('date')) {
            $availability->date = $request->date;
        }

        if ($request->has('is_available')) {
            $availability->is_available = $request->is_available;
            $availability->reason = $request->is_available ? null : $request->reason;
            $availability->time_slots = $request->is_available ? $request->time_slots : null;
        } else {
            // If is_available is not provided but other fields are
            if ($availability->is_available && $request->has('time_slots')) {
                $availability->time_slots = $request->time_slots;
            } elseif (!$availability->is_available && $request->has('reason')) {
                $availability->reason = $request->reason;
            }
        }

        $availability->save();

        return response()->json([
            'message' => 'Availability updated successfully',
            'availability' => $availability
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        $availability = UserAvailability::findOrFail($id);

        // Check if user is authorized to delete this availability
        if ($availability->user_id !== $user->id && !$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $availability->delete();

        return response()->json(['message' => 'Availability deleted successfully']);
    }

    /**
     * Get weekly availabilities for all users or a specific user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWeeklyAvailabilities(Request $request)
    {
        $user = Auth::user();

        // Only admin/manager can see all users' availabilities
        if (!$user->isSuperAdmin() && !$user->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get week start and end dates
        $startDate = $request->input('start_date', Carbon::now()->startOfWeek()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfWeek()->format('Y-m-d'));

        // Get all users or filter by user_id if provided
        $usersQuery = User::query();
        if ($request->has('user_id')) {
            $usersQuery->where('id', $request->input('user_id'));
        }

        $users = $usersQuery->get(['id', 'name', 'email']);

        // Get availabilities for the specified week
        $availabilities = UserAvailability::whereBetween('date', [$startDate, $endDate])
            ->when($request->has('user_id'), function ($query) use ($request) {
                return $query->where('user_id', $request->input('user_id'));
            })
            ->orderBy('date')
            ->get();

        // Group availabilities by user
        $groupedAvailabilities = [];
        foreach ($users as $user) {
            $userAvailabilities = $availabilities->where('user_id', $user->id);
            $groupedAvailabilities[] = [
                'user' => $user,
                'availabilities' => $userAvailabilities
            ];
        }

        return response()->json([
            'weekly_availabilities' => $groupedAvailabilities,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    /**
     * Check if the current day is Thursday to show the availability prompt.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function shouldShowPrompt()
    {
        $user = Auth::user();
        $today = Carbon::now();
        $nextWeekStart = Carbon::now()->addWeek()->startOfWeek();
        $nextWeekEnd = Carbon::now()->addWeek()->endOfWeek();

        // Check if today is between Thursday and Saturday (inclusive)
        $isThursday = $today->dayOfWeek === Carbon::THURSDAY;
        $isFriday = $today->dayOfWeek === Carbon::FRIDAY;
        $isSaturday = $today->dayOfWeek === Carbon::SATURDAY;
        $isThursdayToSaturday = $isThursday || $isFriday || $isSaturday;

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

        // Determine if the user should be blocked from other features
        // Block if it's after Thursday and they haven't submitted availability for all weekdays
        $isAfterThursday = $today->dayOfWeek > Carbon::THURSDAY ||
                          ($today->dayOfWeek === Carbon::THURSDAY && $today->hour >= 23 && $today->minute >= 59);
        $shouldBlockUser = $isAfterThursday && !$allWeekdaysCovered;

        // Always show prompt between Thursday and Saturday, regardless of submission status
        $shouldShowPrompt = $isThursdayToSaturday;

        return response()->json([
            'should_show_prompt' => $shouldShowPrompt,
            'should_block_user' => true,
            'next_week_start' => $nextWeekStart->format('Y-m-d'),
            'next_week_end' => $nextWeekEnd->format('Y-m-d'),
            'weekdays_covered' => $uniqueWeekdaysWithAvailability,
            'all_weekdays_covered' => $allWeekdaysCovered,
            'current_day' => $today->dayOfWeek,
            'is_thursday_to_saturday' => $isThursdayToSaturday
        ]);
    }

    /**
     * Store multiple availability records in a batch.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batch(Request $request)
    {
        $user = Auth::user();

        // Validate request data
        $validator = Validator::make($request->all(), [
            'availabilities' => 'required|array',
            'availabilities.*.date' => 'required|date',
            'availabilities.*.is_available' => 'required|boolean',
            'availabilities.*.reason' => 'required_if:availabilities.*.is_available,false|nullable|string',
            'availabilities.*.time_slots' => 'required_if:availabilities.*.is_available,true|nullable|array',
            'availabilities.*.time_slots.*.start_time' => 'required_with:availabilities.*.time_slots|string|date_format:H:i',
            'availabilities.*.time_slots.*.end_time' => 'required_with:availabilities.*.time_slots|string|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $savedAvailabilities = [];
        $errors = [];

        // Process each availability record
        foreach ($request->availabilities as $index => $availabilityData) {
            try {
                // Check if availability already exists for this date
                $existingAvailability = UserAvailability::where('user_id', $user->id)
                    ->where('date', $availabilityData['date'])
                    ->first();

                if ($existingAvailability) {
                    // Update existing availability
                    $existingAvailability->is_available = $availabilityData['is_available'];
                    $existingAvailability->reason = $availabilityData['is_available'] ? null : $availabilityData['reason'];
                    $existingAvailability->time_slots = $availabilityData['is_available'] ? $availabilityData['time_slots'] : null;
                    $existingAvailability->save();

                    $savedAvailabilities[] = $existingAvailability;
                } else {
                    // Create new availability
                    $availability = new UserAvailability();
                    $availability->user_id = $user->id;
                    $availability->date = $availabilityData['date'];
                    $availability->is_available = $availabilityData['is_available'];
                    $availability->reason = $availabilityData['is_available'] ? null : $availabilityData['reason'];
                    $availability->time_slots = $availabilityData['is_available'] ? $availabilityData['time_slots'] : null;
                    $availability->save();

                    $savedAvailabilities[] = $availability;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'date' => $availabilityData['date'],
                    'message' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => count($savedAvailabilities) . ' availability records processed successfully',
            'availabilities' => $savedAvailabilities,
            'errors' => $errors
        ], 201);
    }
}
