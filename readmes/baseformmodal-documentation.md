# BaseFormModal Component Documentation

## Overview

The `BaseFormModal` component is a reusable Vue component that handles form submissions in modal dialogs. It uses axios for API requests instead of Inertia.js, making it suitable for AJAX-style form submissions that don't require a full page reload.

## Key Features

- Uses axios for API requests instead of Inertia.js
- Supports multiple HTTP methods (POST, PUT, PATCH, DELETE)
- Provides hooks for data formatting and pre-submission validation
- Handles validation errors and displays them in the form
- Emits events for form submission success and errors
- Supports custom success messages

## Props

| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| show | Boolean | No | false | Controls the visibility of the modal |
| title | String | No | 'Modal Title' | The title displayed in the modal header |
| apiEndpoint | String | No | - | The API endpoint to submit the form to |
| httpMethod | String | No | 'post' | The HTTP method to use (post, put, patch, delete) |
| formData | Object | Yes | - | The form data to submit |
| submitButtonText | String | No | 'Save' | The text displayed on the submit button |
| successMessage | String | No | 'Operation successful!' | The message displayed on successful submission |
| formatDataForApi | Function | No | (data) => data | Function to format data before API call |
| beforeSubmit | Function | No | () => true | Function to run before submission (can be async) |
| showFooter | Boolean | No | true | Whether to show the modal footer with action buttons |

## Events

| Event | Payload | Description |
|-------|---------|-------------|
| close | - | Emitted when the modal is closed |
| submitted | response.data | Emitted when the form is successfully submitted |
| error | error | Emitted when an error occurs during submission |

## Usage Example

```vue
<template>
  <BaseFormModal
    :show="showModal"
    title="Create User"
    api-endpoint="/api/users"
    http-method="post"
    :form-data="userData"
    submit-button-text="Create User"
    success-message="User created successfully!"
    :before-submit="validateForm"
    :format-data-for-api="formatUserData"
    @close="closeModal"
    @submitted="handleUserCreated"
    @error="handleError"
  >
    <template #default="{ errors }">
      <!-- Form fields go here -->
      <div class="mb-4">
        <InputLabel for="name" value="Name" />
        <TextInput
          id="name"
          type="text"
          class="mt-1 block w-full"
          v-model="userData.name"
          required
        />
        <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
      </div>
      
      <!-- More form fields... -->
    </template>
  </BaseFormModal>
</template>

<script setup>
import { ref, reactive } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const showModal = ref(false);
const userData = reactive({
  name: '',
  email: '',
  // Other user data...
});

const validateForm = async () => {
  // Perform client-side validation
  if (!userData.name) {
    alert('Name is required');
    return false;
  }
  return true;
};

const formatUserData = (data) => {
  // Format data before sending to API
  return {
    ...data,
    name: data.name.trim(),
  };
};

const closeModal = () => {
  showModal.value = false;
};

const handleUserCreated = (data) => {
  console.log('User created:', data);
  // Do something with the created user data
};

const handleError = (error) => {
  console.error('Error creating user:', error);
  // Handle the error
};
</script>
```

## Implementation Notes

1. The component uses axios for API requests, which is globally available as `window.axios`.
2. Validation errors from the server (422 responses) are automatically parsed and made available to the form fields.
3. The `beforeSubmit` hook can be used to perform client-side validation or data preparation before submission.
4. The `formatDataForApi` hook can be used to transform the data before sending it to the API.
5. The component automatically closes on successful submission.

## Testing

A test page is available at `/test/form-modal` to demonstrate the component in action. The test page uses a simple form with name and email fields to show how the component handles form submissions and validation errors.
