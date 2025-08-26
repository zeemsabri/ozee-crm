# Project Show Page Permission-Based Visibility

This document outlines the permission-based visibility implemented in the Projects/Show.vue component. The component now conditionally renders different cards based on the user's permissions, ensuring that sensitive information is only visible to users with the appropriate permissions.

## Permissions Used

The following permissions are used to control visibility of different cards in the Projects/Show.vue component:

| Permission | Description | Used For |
|------------|-------------|----------|
| view_project_financial | View project financial information | Financial Information Card |
| view_project_transactions | View project transactions | Financial Information Card (transactions section) |
| view_client_contacts | View client contact details | Clients Card |
| view_client_financial | View client financial information | Financial Information Card (client-related financial info) |
| view_users | View user list and details | Assigned Team Card |
| view_emails | View emails | Email Communication Section |
| compose_emails | Compose new emails | Compose Email Button |

## Card Visibility by Role

The table below shows which cards are visible to each role based on their permissions:

| Card | Super Admin | Manager | Employee | Contractor |
|------|-------------|---------|----------|------------|
| Financial Information | Yes | Yes | No | No |
| Clients Card | Yes | Yes | No | No |
| Assigned Team Card | Yes | Yes | No | No |
| Email Communication | Yes | Yes | Yes | Yes |
| Compose Email Button | Yes | Yes | Yes | Yes |

## Implementation Details

The permission checks are implemented using computed properties in the Projects/Show.vue component:

```javascript
// Permission checks for card visibility
const canViewProjectFinancial = computed(() => {
    return authUser.value && authUser.value.hasPermission('view_project_financial');
});

const canViewProjectTransactions = computed(() => {
    return authUser.value && authUser.value.hasPermission('view_project_transactions');
});

const canViewClientContacts = computed(() => {
    return authUser.value && authUser.value.hasPermission('view_client_contacts');
});

const canViewClientFinancial = computed(() => {
    return authUser.value && authUser.value.hasPermission('view_client_financial');
});

const canViewUsers = computed(() => {
    return authUser.value && authUser.value.hasPermission('view_users');
});

const canViewEmails = computed(() => {
    return authUser.value && authUser.value.hasPermission('view_emails');
});

const canComposeEmails = computed(() => {
    return authUser.value && authUser.value.hasPermission('compose_emails');
});
```

These computed properties are then used in the template to conditionally render the cards:

```html
<!-- Financial Information Card -->
<div v-if="canViewProjectFinancial" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
    <!-- Card content -->
</div>

<!-- Clients Card -->
<div v-if="canViewClientContacts" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
    <!-- Card content -->
</div>

<!-- Assigned Team Card -->
<div v-if="canViewUsers" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
    <!-- Card content -->
</div>

<!-- Email Communication Section -->
<div v-if="canViewEmails" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
    <div class="flex justify-between items-center mb-4">
        <h4 class="text-lg font-semibold text-gray-900">Email Communication</h4>
        <div v-if="canComposeEmails" class="flex gap-3">
            <PrimaryButton
                class="bg-indigo-600 hover:bg-indigo-700 transition-colors"
                @click="router.visit('/emails/compose')"
            >
                Compose Email
            </PrimaryButton>
        </div>
    </div>
    <!-- Section content -->
</div>
```

## Testing

A test script has been created to verify the permission-based visibility works correctly for different user roles. The script can be run with:

```bash
php test-project-show-permissions.php
```

The test script checks the permissions for each role and provides a summary of which cards will be visible to each role based on their permissions.

## Future Considerations

1. Additional permission checks could be added for other sections of the page as needed.
2. More granular permissions could be implemented for specific actions within each card.
3. The permission system could be extended to include project-specific permissions, allowing users to have different permissions for different projects.
