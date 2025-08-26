# Weekly Availability Feature Documentation

## Overview

The Weekly Availability feature allows users to submit their availability for each day of the week. The system prompts users every Thursday to fill in their availability for the next week. This information is displayed in a calendar view, making it easy to see when team members are available for meetings and work.

## Database Schema

### UserAvailability Model

The feature uses a `user_availabilities` table with the following schema:

| Column      | Type      | Description                                      |
|-------------|-----------|--------------------------------------------------|
| id          | bigint    | Primary key                                      |
| user_id     | bigint    | Foreign key to users table                       |
| date        | date      | The date for which availability is set           |
| is_available| boolean   | Whether the user is available on this date       |
| reason      | text      | Reason for unavailability (nullable)             |
| time_slots  | json      | Array of time slots when available (nullable)    |
| created_at  | timestamp | Creation timestamp                               |
| updated_at  | timestamp | Update timestamp                                 |

The `time_slots` column stores an array of objects with `start_time` and `end_time` properties, e.g.:
```json
[
  {"start_time": "09:00", "end_time": "12:00"},
  {"start_time": "13:00", "end_time": "17:00"}
]
```

## API Endpoints

The feature provides the following API endpoints:

| Method | Endpoint                   | Description                                           |
|--------|----------------------------|-------------------------------------------------------|
| GET    | /api/availabilities        | Get availabilities for the current user or all users  |
| POST   | /api/availabilities        | Create a new availability record                      |
| GET    | /api/availabilities/{id}   | Get a specific availability record                    |
| PUT    | /api/availabilities/{id}   | Update an existing availability record                |
| DELETE | /api/availabilities/{id}   | Delete an availability record                         |
| GET    | /api/weekly-availabilities | Get weekly availabilities for all users (admin only)  |
| GET    | /api/availability-prompt   | Check if the Thursday reminder should be shown        |

### Request and Response Examples

#### GET /api/availabilities

Query parameters:
- `start_date` (optional): Start date for the date range (default: current week start)
- `end_date` (optional): End date for the date range (default: current week end)
- `user_id` (optional, admin only): Filter by user ID

Response:
```json
{
  "availabilities": [
    {
      "id": 1,
      "user_id": 1,
      "date": "2025-07-28",
      "is_available": true,
      "reason": null,
      "time_slots": [
        {"start_time": "09:00", "end_time": "12:00"},
        {"start_time": "13:00", "end_time": "17:00"}
      ],
      "created_at": "2025-07-24T11:01:00.000000Z",
      "updated_at": "2025-07-24T11:01:00.000000Z"
    }
  ],
  "start_date": "2025-07-28",
  "end_date": "2025-08-03"
}
```

#### POST /api/availabilities

Request:
```json
{
  "date": "2025-07-28",
  "is_available": true,
  "time_slots": [
    {"start_time": "09:00", "end_time": "12:00"},
    {"start_time": "13:00", "end_time": "17:00"}
  ]
}
```

Response (201 Created):
```json
{
  "message": "Availability saved successfully",
  "availability": {
    "id": 1,
    "user_id": 1,
    "date": "2025-07-28",
    "is_available": true,
    "reason": null,
    "time_slots": [
      {"start_time": "09:00", "end_time": "12:00"},
      {"start_time": "13:00", "end_time": "17:00"}
    ],
    "created_at": "2025-07-24T11:01:00.000000Z",
    "updated_at": "2025-07-24T11:01:00.000000Z"
  }
}
```

#### GET /api/availability-prompt

Response:
```json
{
  "should_show_prompt": true,
  "next_week_start": "2025-07-28",
  "next_week_end": "2025-08-03"
}
```

## Frontend Components

The feature includes the following Vue components:

### AvailabilityModal

A modal dialog for submitting availability for a specific date. Users can mark themselves as available (with time slots) or not available (with a reason).

Props:
- `show` (Boolean): Whether the modal is visible
- `date` (String): The date for which to set availability
- `nextWeekDates` (Array, optional): Array of dates for the next week

Events:
- `close`: Emitted when the modal is closed
- `availability-saved`: Emitted when availability is saved successfully

### AvailabilityCalendar

A calendar view showing availabilities for the current week. Admins can filter by user.

Props:
- `userId` (Number): The ID of the current user
- `isAdmin` (Boolean): Whether the current user is an admin

### AvailabilityPrompt

A prompt shown on the dashboard every Thursday to remind users to fill in their availability for the next week.

## Pages

### Availability Index Page

A dedicated page for viewing and managing weekly availabilities. The page is accessible at `/availability` and is linked from the main navigation menu.

## Usage

### Submitting Availability

1. Users receive a prompt on the dashboard every Thursday to fill in their availability for the next week.
2. Click the "Submit Availability" button in the prompt to open the availability modal.
3. Select a date from the dropdown.
4. If available on that date:
   - Keep the "I am available on this day" checkbox checked.
   - Add one or more time slots by specifying start and end times.
   - Click "Add Another Time Slot" to add more slots if needed.
5. If not available on that date:
   - Uncheck the "I am available on this day" checkbox.
   - Enter a reason for unavailability.
6. Click "Save Availability" to submit.
7. Repeat for each day of the week.

### Viewing Availabilities

1. Click on "Weekly Availability" in the main navigation menu.
2. The calendar view shows availabilities for the current week.
3. Use the navigation buttons to move between weeks.
4. Admins can use the user filter to view availabilities for specific users.

## Implementation Details

### Thursday Reminder Logic

The system checks if today is Thursday and if the user has already submitted availability for the next week. If it's Thursday and the user hasn't submitted availability yet, the prompt is shown on the dashboard.

### Authorization

- Regular users can only view and manage their own availabilities.
- Admins (super-admin and manager roles) can view availabilities for all users.

## Testing

The feature includes the following test scripts:

- `test-availability-api.php`: Tests the API endpoints
- `test-availability-frontend.js`: Tests the frontend components
- `test-availability-reminder.php`: Tests the weekly reminder functionality

To run the API and reminder tests:
```bash
php test-availability-api.php
php test-availability-reminder.php
```

The frontend tests are written as pseudo-code and would need to be implemented with a proper testing framework like Jest with Vue Test Utils in a real project.

## Troubleshooting

### Common Issues

1. **Prompt not showing on Thursday**: Check if you've already submitted availability for next week.
2. **Cannot save availability**: Ensure all time slots have both start and end times, and that start time is before end time.
3. **Calendar not showing availabilities**: Check that the date range is correct and that you have permission to view the availabilities.

### Debugging

- Check the browser console for JavaScript errors.
- Check the Laravel logs for backend errors.
- Use the test scripts to verify that the API endpoints are working correctly.

## Future Enhancements

Potential future enhancements for the availability feature:

1. Email notifications for users who haven't submitted their availability by Friday.
2. Integration with calendar systems (Google Calendar, Outlook) to automatically block unavailable times.
3. Team view to see availabilities for specific teams or departments.
4. Recurring availability patterns to avoid submitting the same availability every week.
5. Mobile app notifications for the Thursday reminder.
