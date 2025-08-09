<script setup>
import { ref, reactive } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['close', 'submitted']);

// Form data
const formData = reactive({
    name: '',
    email: '',
});

// API endpoint for testing
const apiEndpoint = '/api/test-form';

// Before submit hook
const beforeSubmit = async () => {
    console.log('Before submit hook called');
    // You could perform validation or data preparation here
    return true; // Return true to continue with submission
};

// Format data for API
const formatDataForApi = (data) => {
    console.log('Format data for API called');
    // You could transform the data here before sending to API
    return { ...data, formatted: true };
};

// Handle submission success
const handleSubmitted = (responseData) => {
    console.log('Form submitted successfully:', responseData);
    emit('submitted', responseData);
};

// Handle submission error
const handleError = (error) => {
    console.error('Form submission error:', error);
};

// Close modal
const closeModal = () => {
    emit('close');
};
</script>

<template>
    <BaseFormModal
        :show="show"
        title="Test Form"
        :api-endpoint="apiEndpoint"
        http-method="post"
        :form-data="formData"
        submit-button-text="Submit Test"
        success-message="Test form submitted successfully!"
        :before-submit="beforeSubmit"
        :format-data-for-api="formatDataForApi"
        @close="closeModal"
        @submitted="handleSubmitted"
        @error="handleError"
    >
        <template #default="{ errors }">
            <div class="mb-4">
                <InputLabel for="name" value="Name" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="formData.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
            </div>

            <div class="mb-4">
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="formData.email"
                    required
                />
                <InputError :message="errors.email ? errors.email[0] : ''" class="mt-2" />
            </div>
        </template>
    </BaseFormModal>
</template>
