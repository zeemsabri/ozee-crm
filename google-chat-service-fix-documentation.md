# Google Chat Service Fix Documentation

## Issue Description

An error was occurring in the GoogleChatService class at line 180:

```
message: "array_merge(): Argument #2 must be of type array, string given"
```

This error was happening in the `removeMembersFromSpace` method when calling the Google Chat API to delete a member from a space.

## Investigation

Upon examining the code, I found that the issue was in how the `spaces_members->delete()` method was being called. The error message indicated that `array_merge()` was expecting an array as its second argument but was receiving a string instead.

Looking at the implementation of the `delete()` method in the Google API client library (`vendor/google/apiclient-services/src/HangoutsChat/Resource/SpacesMembers.php`), I found that it expects:

1. A string `$name` parameter as the first argument (the resource name to delete)
2. An optional array `$optParams` as the second argument

The method then uses `array_merge()` internally to combine these parameters:

```php
public function delete($name, $optParams = [])
{
  $params = ['name' => $name];
  $params = array_merge($params, $optParams);
  return $this->call('delete', [$params], Membership::class);
}
```

However, in the GoogleChatService class, we were calling this method incorrectly:

```php
$service->spaces_members->delete($spaceName, $memberResourceName);
```

Here, `$spaceName` was being passed as the first parameter (which is correct), but `$memberResourceName` was being passed as the second parameter, which should be an array of optional parameters, not a string.

## Changes Made

I modified the `removeMembersFromSpace` method in `GoogleChatService.php` to correctly call the `delete()` method:

```php
// Before:
$service->spaces_members->delete($spaceName, $memberResourceName);

// After:
$service->spaces_members->delete($spaceName . '/members/' . $memberResourceName, []);
```

The key changes were:

1. Constructing the full resource name as a single string for the first parameter
2. Passing an empty array as the second parameter for optional parameters

## Testing

I created a test script (`test-google-chat-service-fix.php`) that simulates the GoogleChatService's `removeMembersFromSpace` method with our fix. The test verifies that:

1. The `delete()` method is called with the correct parameters
2. The first parameter is a string (the full resource name)
3. The second parameter is an array (empty in this case)

The test passed successfully, confirming that our fix correctly calls the `delete()` method with the proper parameters.

## Impact

This fix resolves the "array_merge(): Argument #2 must be of type array, string given" error that was occurring when trying to remove members from a Google Chat space. Users can now successfully remove members from spaces without encountering this error.

## Date of Fix

2025-07-21
