# MultiSelectWithRoles Remove Button Permissions

## Issue Description

The MultiSelectWithRoles component is a reusable component used for selecting multiple items with roles. It's currently used in the ProjectForm component for both clients and users selection. The component has a "Remove" button that was always visible, regardless of the user's permissions.

The requirement was to make the Remove button conditionally visible based on specific permissions:
- 'manage_project_clients' permission for client selection
- 'manage_project_users' permission for user selection

## Solution

### 1. Added showRemoveButton prop to MultiSelectWithRoles.vue

Added a new prop to control the visibility of the Remove button:

```javascript
showRemoveButton: {
    type: Boolean,
    default: true
}
```

### 2. Updated the template to conditionally render the Remove button

Modified the Remove button in the template to only show when the showRemoveButton prop is true:

```html
<button
    v-if="showRemoveButton"
    type="button"
    class="ml-2 text-red-600"
    @click="() => removeItem(itemId)"
>
    Remove
</button>
```

### 3. Updated ProjectForm.vue to pass permission values

Updated both instances of MultiSelectWithRoles in ProjectForm.vue to pass the appropriate permission values:

For clients:
```html
<MultiSelectWithRoles
    label="Clients"
    :items="clients"
    v-model:selectedItems="projectForm.client_ids"
    :roleOptions="clientRoleOptionsComputed"
    roleType="client"
    :error="errors.client_ids ? errors.client_ids[0] : ''"
    placeholder="Select a client to add"
    :disabled="!canManageProjectClients"
    :readonly="!canManageProjectClients && canViewProjectClients"
    :showRemoveButton="canManageProjectClients.value"
/>
```

For users:
```html
<MultiSelectWithRoles
    label="Assign Users"
    :items="users"
    v-model:selectedItems="projectForm.user_ids"
    :roleOptions="userRoleOptionsComputed"
    roleType="project"
    :defaultRoleId="2"
    :error="errors.user_ids ? errors.user_ids[0] : ''"
    placeholder="Select a user to add"
    :disabled="!canManageProjectUsers"
    :readonly="!canManageProjectUsers && canViewProjectUsers"
    :showRemoveButton="canManageProjectUsers.value"
/>
```

## How It Works

1. The MultiSelectWithRoles component now accepts a showRemoveButton prop that defaults to true.
2. The Remove button in the component is only rendered when showRemoveButton is true.
3. In ProjectForm.vue, the showRemoveButton prop is bound to the value of the appropriate permission check:
   - For clients: canManageProjectClients.value
   - For users: canManageProjectUsers.value
4. When a user has the required permission, the Remove button is visible and they can remove items.
5. When a user doesn't have the required permission, the Remove button is hidden and they cannot remove items.

## Testing

A test script (test-multi-select-component.js) has been created to verify that the changes work correctly. The script includes:

1. Test cases for different permission scenarios:
   - User has manage_project_clients permission (Remove button should be visible)
   - User doesn't have manage_project_clients permission (Remove button should be hidden)
   - User has manage_project_users permission (Remove button should be visible)
   - User doesn't have manage_project_users permission (Remove button should be hidden)

2. Instructions for manual testing with different user roles:
   - Super Admin (should see Remove buttons for both clients and users)
   - User with manage_project_clients permission (should see Remove buttons for clients only)
   - User with manage_project_users permission (should see Remove buttons for users only)
   - User without either permission (should not see any Remove buttons)

## Benefits

1. **Improved Security**: Users can only remove items if they have the appropriate permissions.
2. **Better User Experience**: The UI only shows actions that the user is allowed to perform.
3. **Reusable Component**: The MultiSelectWithRoles component remains reusable and can be used in other parts of the application with different permission checks.
4. **Consistent Permissions**: The permission checks are consistent with the rest of the application.

## Files Changed

1. `/Users/zeeshansabri/laravel/email-approval-app/resources/js/Components/MultiSelectWithRoles.vue`
   - Added showRemoveButton prop
   - Updated template to conditionally render the Remove button

2. `/Users/zeeshansabri/laravel/email-approval-app/resources/js/Components/ProjectForm.vue`
   - Updated both MultiSelectWithRoles instances to pass the appropriate permission values

## Future Improvements

1. **Add Animation**: Consider adding a smooth animation when the Remove button appears or disappears.
2. **Tooltip**: Add a tooltip explaining why the Remove button is not available when the user doesn't have the required permission.
3. **Accessibility**: Ensure the component remains accessible when the Remove button is hidden.
4. **Testing**: Add unit tests for the MultiSelectWithRoles component to verify that the showRemoveButton prop works correctly.
