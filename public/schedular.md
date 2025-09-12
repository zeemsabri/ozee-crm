-- A comprehensive schema for the schedules table
CREATE TABLE schedules (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(255) NOT NULL COMMENT 'A user-friendly name for the schedule.',
description TEXT NULL COMMENT 'A brief description of what the schedule does.',
start_at DATETIME NOT NULL COMMENT 'The first time the schedule should run.',
end_at DATETIME NULL COMMENT 'The time after which the schedule should no longer run.',
recurrence_pattern VARCHAR(255) NOT NULL COMMENT 'A cron expression defining the recurrence.',
is_active BOOLEAN NOT NULL DEFAULT 1 COMMENT 'Flag to enable or disable the schedule.',
is_onetime BOOLEAN NOT NULL DEFAULT 0 COMMENT 'If true, the schedule runs once and is then deactivated.',
last_run_at DATETIME NULL COMMENT 'The timestamp of the last successful execution.',
scheduled_item_id BIGINT UNSIGNED NOT NULL COMMENT 'ID of the linked task, workflow, etc.',
scheduled_item_type VARCHAR(50) NOT NULL COMMENT 'The type of item being scheduled (e.g., App\Models\Task).',
created_at TIMESTAMP NULL,
updated_at TIMESTAMP NULL
) COMMENT='Stores all scheduled tasks and workflows.';


Technical Specification for the Schedule Module
1.0 Objectives
The primary objective is to develop a self-contained, flexible, and robust scheduling module within the existing Laravel CRM. This module will allow for the automation of a variety of actions, including running workflows, executing tasks, and sending emails. The solution must be highly configurable to handle diverse recurrence patterns and be simple for developers to integrate with new features.

2.0 Database Schema
A new table, schedules, will be added to your CRM's database. This design uses a polymorphic relationship to link to any scheduled item, ensuring maximum flexibility.

3.0 Backend Logic & Implementation
The scheduling will be managed by a single Laravel Artisan command, which is the only cron entry required on the server.

Artisan Command (App\Console\Commands\RunScheduler.php):

This command will run on a frequent interval (e.g., * * * * * for every minute) via the server's crontab.

It will query the schedules table for all active records.

For each active schedule, it will use a cron parser library (e.g., mtdowling/cron-expression) to check if the current time matches the recurrence_pattern.

It will also check if the current date is within the start_at and end_at range.

If a schedule is due, a new job will be dispatched to a queue for processing.

Queued Job (App\Jobs\RunScheduledItem.php):

This job is responsible for executing the logic of the scheduled item.

It accepts the scheduled_item_id and scheduled_item_type as parameters.

It uses the scheduled_item_type to find the correct model and call a designated method to execute its logic.

After execution, it updates the last_run_at field on the schedules table.

If the is_onetime flag is true, it sets is_active to false to prevent future runs.

Cron Expressions:

The recurrence_pattern will be a standard cron expression.

This allows for various scenarios, from 0 */4 * * * (every 4 hours) to 0 8 * * 1-5 (every weekday at 8 AM).

For one-time events, the UI will set the is_onetime flag to true, and the recurrence_pattern will be * * * * *. The backend logic will handle the one-time execution by checking start_at and then deactivating the schedule.

UI/UX Design for the Schedule Feature
1.0 User Experience Goal
The user should not need to understand cron expressions. The interface will be intuitive, using clear language and visual components to build the schedule. The primary goal is to provide a user experience that feels like using a calendar, not a technical tool.

2.0 User Flow: Creating a Schedule
The process will be a guided, three-step wizard to prevent user confusion.

Step 1: Basic Details
The user will access this form via a "Schedule" button on the Task and Workflow detail pages.

Schedule Name: A required text field. This will be the main identifier in the schedule list.

Description: An optional text area for notes.

Linked Item: A read-only field that automatically shows the name of the task or workflow.

Step 2: Recurrence Configuration
This is the core of the UX. The interface will use simple forms to generate the complex cron expression on the backend.

Run: A radio group or dropdown with the following options:

Once: A date and time picker appears. This option automatically sets the is_onetime flag to true.

Daily: A time picker appears.

Weekly: A time picker and a multi-select checkbox group for the days of the week (e.g., Mon, Tue, Wed).

Monthly:

A radio button to choose between:

"On day [1-31]" (e.g., "On day 15").

"On the [First, Second, Third, etc.] [day of the week]" (e.g., "On the third Friday").

A time picker.

Yearly: A month dropdown and a day-of-the-month dropdown, plus a time picker.

Recurrence Summary: A crucial UI element that provides instant, human-readable feedback.

As the user makes selections, this text will update.

Example: "This schedule will run every Monday, Wednesday, and Friday at 9:00 AM."

Start & End Dates: Date and time pickers for the start_at and end_at fields. The end_at field should be optional.

Step 3: Confirmation
A final screen summarizing all the details before the user saves the schedule.

3.0 Management & Dashboard
A dedicated "Schedules" section in the CRM's main navigation will provide a centralized view.

List View: A clean, sortable table with the following columns:

Name: The schedule's name.

Linked Item: The name of the task or workflow it is attached to.

Next Run: A calculated timestamp showing the very next time the schedule will run.

Last Run: The timestamp of the previous run.

Status: A visual indicator (e.g., a colored badge or icon) for "Active" or "Inactive."

Actions: An actions column with buttons to "Edit," "Deactivate," "Activate," and "Delete."
