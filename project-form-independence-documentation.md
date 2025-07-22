# Project Form Independence Documentation

## Overview

This document describes the implementation of independent sections in the ProjectForm component, allowing users to update different parts of a project separately based on their permissions.

## Background

Previously, the ProjectForm component used a single "Update Project" button that would update all sections of the project at once. This approach had several limitations:

1. Users needed permissions for all sections to update any part of the project
2. All data was sent in a single request, even if only one section was modified
3. It was difficult to implement section-specific permissions
4. The UI didn't clearly indicate which sections a user could modify

To address these issues, we've refactored the ProjectForm component to use separate API endpoints for each section of the project, with dedicated save buttons for each tab.

## Implementation Details

### 1. Backend Changes

#### 1.1 ProjectSectionController

We created a new `ProjectSectionController` that provides endpoints for fetching and updating specific sections of a project:

- `GET /api/projects/{project}/sections/basic` - Get basic project information
- `PUT /api/projects/{project}/sections/basic` - Update basic project information
- `GET /api/projects/{project}/sections/clients-users` - Get project clients and users
- `GET /api/projects/{project}/sections/services-payment` - Get project services and payment information
- `PUT /api/projects/{project}/sections/services-payment` - Update project services and payment information
- `GET /api/projects/{project}/sections/transactions` - Get project transactions
- `PUT /api/projects/{project}/sections/transactions` - Update project transactions
- `GET /api/projects/{project}/sections/documents` - Get project documents
- `GET /api/projects/{project}/sections/notes` - Get project notes
- `PUT /api/projects/{project}/sections/notes` - Update project notes

Each endpoint enforces appropriate permissions, ensuring that users can only access and modify sections they have permission for.

#### 1.2 API Routes

We added routes for the new endpoints in `routes/api.php`:

```php
// Project Section Routes (for permission-based data fetching)
Route::get('projects/{project}/sections/basic', [ProjectSectionController::class, 'getBasicInfo']);
Route::get('projects/{project}/sections/clients-users', [ProjectSectionController::class, 'getClientsAndUsers']);
Route::get('projects/{project}/sections/services-payment', [ProjectSectionController::class, 'getServicesAndPayment']);
Route::get('projects/{project}/sections/transactions', [ProjectSectionController::class, 'getTransactions']);
Route::get('projects/{project}/sections/documents', [ProjectSectionController::class, 'getDocuments']);
Route::get('projects/{project}/sections/notes', [ProjectSectionController::class, 'getNotes']);

// Project Section Update Routes
Route::put('projects/{project}/sections/basic', [ProjectSectionController::class, 'updateBasicInfo']);
Route::put('projects/{project}/sections/services-payment', [ProjectSectionController::class, 'updateServicesAndPayment']);
Route::put('projects/{project}/sections/transactions', [ProjectSectionController::class, 'updateTransactions']);
Route::put('projects/{project}/sections/notes', [ProjectSectionController::class, 'updateNotes']);
```

### 2. Frontend Changes

#### 2.1 Tab-Specific Data Loading

We modified the ProjectForm component to load data for each tab separately:

```javascript
// Function to switch tabs safely
const switchTab = (tabName) => {
    // Set the active tab
    activeTab.value = tabName;

    // If we have a project ID, fetch data for the selected tab
    if (projectForm.id) {
        // Show loading indicator
        loading.value = true;

        // Fetch data based on the selected tab
        switch (tabName) {
            case 'basic':
                fetchBasicData(projectForm.id).finally(() => {
                    loading.value = false;
                });
                break;
            case 'client':
                if (canViewProjectClients.value || canManageProjectClients.value ||
                    canViewProjectUsers.value || canManageProjectUsers.value) {
                    fetchClientsAndUsersData(projectForm.id).finally(() => {
                        loading.value = false;
                    });
                } else {
                    loading.value = false;
                }
                break;
            // ... other tabs
        }
    }
};
```

#### 2.2 Section-Specific Update Functions

We created separate update functions for each section of the project:

```javascript
// Function to create a new project
const createProject = async () => {
    // ... implementation
};

// Function to update basic information
const updateBasicInfo = async () => {
    // ... implementation
};

// Function to update services and payment
const updateServicesAndPayment = async () => {
    // ... implementation
};

// Function to update transactions
const updateTransactions = async () => {
    // ... implementation
};

// Function to update notes
const updateNotes = async () => {
    // ... implementation
};
```

#### 2.3 Tab-Specific Save Buttons

We added separate save buttons for each tab, conditionally rendered based on the user's permissions:

```html
<!-- Basic Information Tab -->
<div v-if="canManageProjects" class="mt-6 flex justify-end">
    <PrimaryButton 
        @click="projectForm.id ? updateBasicInfo() : createProject()"
        :disabled="!canManageProjects"
    >
        {{ projectForm.id ? 'Update Basic Information' : 'Create Project' }}
    </PrimaryButton>
</div>

<!-- Services & Payment Tab -->
<div v-if="canManageProjectServicesAndPayments" class="mt-6 flex justify-end">
    <PrimaryButton 
        @click="updateServicesAndPayment"
        :disabled="!projectForm.id || !canManageProjectServicesAndPayments"
    >
        Update Services & Payment
    </PrimaryButton>
</div>

<!-- Transactions Tab -->
<div v-if="canManageProjectExpenses || canManageProjectIncome" class="mt-6 flex justify-end">
    <PrimaryButton 
        @click="updateTransactions"
        :disabled="!projectForm.id || (!canManageProjectExpenses && !canManageProjectIncome)"
    >
        Update Transactions
    </PrimaryButton>
</div>

<!-- Notes Tab -->
<div v-if="canAddProjectNotes" class="mt-6 flex justify-end">
    <PrimaryButton 
        @click="updateNotes"
        :disabled="!projectForm.id || !canAddProjectNotes"
    >
        Update Notes
    </PrimaryButton>
</div>
```

#### 2.4 Removed Global Update Button

We removed the global "Update Project" button that was previously at the bottom of the form, keeping only the Cancel button:

```html
<div class="mt-6 flex justify-end">
    <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
</div>
```

### 3. Permission Handling

We use the permissions utility to check if a user has permission to view or modify each section of the project:

```javascript
// Permission-based checks using the permission utilities
const canManageProjects = canDo('manage_projects', userProjectRole);
const canViewProjectDocuments = canView('project_documents', userProjectRole);
const canUploadProjectDocuments = canDo('upload_documents', userProjectRole);
const canManageProjectExpenses = canManage('project_expenses', userProjectRole);
const canManageProjectIncome = canManage('project_income', userProjectRole);
const canManageProjectServicesAndPayments = canManage('project_services_and_payments', userProjectRole);
const canViewProjectServicesAndPayments = canView('project_services_and_payments', userProjectRole);
const canAddProjectNotes = canDo('add_project_notes', userProjectRole);
const canViewProjectNotes = canView('project_notes', userProjectRole);
const canManageProjectUsers = canManage('project_users', userProjectRole);
const canViewProjectUsers = canView('project_users', userProjectRole);
const canManageProjectClients = canManage('project_clients', userProjectRole);
const canViewProjectClients = canView('project_clients', userProjectRole);
```

These permission checks are used to:
1. Determine which tabs to show in the UI
2. Enable/disable form fields based on permissions
3. Show/hide save buttons for each section
4. Prevent unauthorized API calls

## Benefits

This implementation provides several benefits:

1. **Granular Permissions**: Users can update only the sections they have permission for
2. **Improved Performance**: Only the data for the active tab is loaded, reducing initial load time
3. **Better UX**: The UI clearly indicates which sections a user can modify
4. **Reduced Data Transfer**: Only the modified section is sent to the server, reducing bandwidth usage
5. **Simplified Error Handling**: Errors are isolated to the specific section being updated

## Testing

A test script (`test-project-form-independence.php`) has been created to verify that the implementation works correctly with different user roles and permissions. The script tests:

1. Super Admin can update all sections
2. Manager can update all sections
3. Employee has limited access based on permissions
4. Contractor with project-specific role has access based on their project role

## Usage

### For Users

1. Navigate to the project form by clicking "Edit Project" on a project
2. Use the tabs at the top of the form to navigate between different sections
3. Make changes to the section you want to update
4. Click the save button at the bottom of the section to save your changes
5. You will only see tabs and save buttons for sections you have permission to access

### For Developers

When adding new sections to the project form:

1. Add a new tab to the UI with appropriate permission checks
2. Create a new endpoint in the ProjectSectionController for fetching and updating the section
3. Add a new update function in the ProjectForm component
4. Add a new save button for the section with appropriate permission checks
5. Update the permission utility to include the new permissions

## Conclusion

The implementation of independent sections in the ProjectForm component provides a more flexible and user-friendly way to manage projects. Users can now update only the sections they have permission for, and the UI clearly indicates which sections they can modify. This approach also improves performance by loading only the data for the active tab and reduces bandwidth usage by sending only the modified section to the server.
