# Email Approval Notifications Implementation

## Overview
This document describes the implementation of email approval notifications in the email-approval-app. The implementation sends notifications to users with appropriate permissions when emails require approval. The notification logic is now implemented in an observer to ensure consistent behavior regardless of where emails are created from.

## Requirements
1. When an email with status "pending_approval" and type "sent" is created, send notifications to users with "approve_emails" permission
2. When an email with status "pending_approval_received" and type "received" is created, send notifications to users with "approve_received_emails" permission
3. Notifications should be sent to users with global permissions and project-specific permissions
4. Notifications should use the same structure as TaskAssigned notifications
5. Notifications should be sent via broadcast and database channels only
6. Notification handling should be moved from controllers to the Email model or observer

## Implementation Details

### 1. Created EmailApprovalRequired Notification Class
Created a new notification class based on the TaskAssigned structure:
- Uses broadcast and database channels
- Maintains the same payload structure
- Customizes the message based on email type (sent or received)

### 2. Created PermissionHelper Class
Created a helper class to find users with specific permissions:
- `getUsersWithGlobalPermission`: Finds users with a global permission
- `getUsersWithProjectPermission`: Finds users with a project-specific permission
- `getAllUsersWithPermission`: Finds all users with a permission (global or project-specific)

### 3. Created EmailObserver
Created an observer for the Email model to handle notifications:
- Implements the `created` method to handle notifications when a new email is created
- Checks email status and type to determine which notifications to send
- Uses PermissionHelper to find users with appropriate permissions
- Sends notifications to users with the correct permissions

### 4. Registered Observer in AppServiceProvider
Added the observer registration in the AppServiceProvider's boot method:
```php
Email::observe(EmailObserver::class);
```

### 5. Removed Notification Logic from Controllers
Removed notification logic from:
- EmailController's store method
- EmailController's storeTemplatedEmail method
- EmailTestController's receiveTestEmails method

This ensures that notifications are consistently sent regardless of where emails are created from.

## Testing
The implementation can be verified by creating emails through any of the application's interfaces. The observer will automatically handle sending notifications based on the email's status and type.

## Conclusion
The implementation meets all the requirements specified in the issue description. Notifications are now handled by an observer, ensuring consistent behavior regardless of where emails are created from. This approach follows the principle of separation of concerns and makes the code more maintainable.
