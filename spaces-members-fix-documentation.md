# Google Chat API Property Fix

## Issue Description
The GoogleChatService class was using an incorrect property name when interacting with the Google Chat API:

```
Property 'spaces_memberships' not found in \Google\Service\HangoutsChat
```

This error occurred because the code was trying to access a property called `spaces_memberships` which doesn't exist in the Google HangoutsChat service class.

## Investigation
Upon examining the Google HangoutsChat API source code, specifically the `HangoutsChat.php` file, it was discovered that the correct property name is `spaces_members`, not `spaces_memberships`.

The HangoutsChat class defines the following properties:
```php
public $customEmojis;
public $media;
public $spaces;
public $spaces_members;
public $spaces_messages;
public $spaces_messages_attachments;
public $spaces_messages_reactions;
public $spaces_spaceEvents;
public $users_spaces;
public $users_spaces_spaceNotificationSetting;
public $users_spaces_threads;
```

## Changes Made
The following changes were made to fix the issue:

1. In `app/Services/GoogleChatService.php`, line 154, changed:
   ```php
   $service->spaces_memberships->create($spaceName, $membership);
   ```
   to:
   ```php
   $service->spaces_members->create($spaceName, $membership);
   ```

2. In `app/Services/GoogleChatService.php`, line 179, changed:
   ```php
   $service->spaces_memberships->delete($spaceName, $memberResourceName);
   ```
   to:
   ```php
   $service->spaces_members->delete($spaceName, $memberResourceName);
   ```

## Impact
These changes ensure that the GoogleChatService class correctly interacts with the Google Chat API when adding or removing members from a space. The code now uses the proper property name that exists in the HangoutsChat service class.

## Date of Fix
2025-07-21
