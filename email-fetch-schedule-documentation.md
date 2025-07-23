# Email Fetching Schedule Implementation

## Overview

This document describes the implementation of a scheduled task to fetch emails every 5 minutes using the `receiveTestEmails` method in the `EmailTestController`.

## Implementation Details

### 1. Created Artisan Command

We created a new Artisan command `emails:fetch` that calls the `receiveTestEmails` method in the `EmailTestController`. This command is defined in:

```
app/Console/Commands/FetchEmails.php
```

The command can be run manually with:

```bash
php artisan emails:fetch
```

### 2. Scheduled Task Configuration

We configured the command to run every 5 minutes in:

```
app/Console/Kernel.php
```

The schedule is set up with the following cron expression:

```php
$schedule->command('emails:fetch')->cron('*/5 * * * *');
```

## Setting Up the Cron Job

To ensure the scheduled task runs automatically, you need to set up a cron job on your server to run Laravel's scheduler every minute.

Add the following line to your server's crontab:

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/your/project` with the actual path to your Laravel project.

To edit your crontab, run:

```bash
crontab -e
```

## Verification

You can verify that the command is working correctly by running it manually:

```bash
php artisan emails:fetch
```

You can also check the Laravel log file for any errors or information about the email fetching process:

```bash
tail -f storage/logs/laravel.log
```

## Troubleshooting

If the scheduled task is not running as expected, check the following:

1. Make sure the cron job is set up correctly
2. Check the Laravel log file for any errors
3. Ensure the `receiveTestEmails` method in the `EmailTestController` is working correctly
4. Verify that the Gmail service is properly configured and authenticated

## Notes

- The command will fetch emails since the last received email, or from the last 30 days if no previous emails exist
- The command will skip emails that have already been processed
- The command will log information about the fetching process to the Laravel log file
