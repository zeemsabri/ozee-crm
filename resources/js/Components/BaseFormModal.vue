<script setup>
import { ref, watch, reactive } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { success, error } from '@/Utils/notification'; // Assuming you have these utilities

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Modal Title',
    },
    apiEndpoint: {
        type: String,
        required: false,
    },
    httpMethod: {
        type: String,
        default: 'post', // 'post', 'put', 'patch'
        validator: (value) => ['post', 'put', 'patch', 'delete'].includes(value.toLowerCase()),
    },
    formData: {
        type: Object,
        required: true,
    },
    submitButtonText: {
        type: String,
        default: 'Save',
    },
    successMessage: {
        type: String,
        default: 'Operation successful!',
    },
    // New prop to allow custom data formatting before API call
    formatDataForApi: {
        type: Function,
        default: (data) => data, // Default to a function that returns data as-is
    },
    showFooter: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['close', 'submitted', 'error']);

const isSubmitting = ref(false);
const validationErrors = ref({});
const generalError = ref('');

// Watch for the modal's show prop to reset state when opened
watch(() => props.show, (newValue) => {
    if (newValue) {
        // Reset errors and submission state when modal opens
        validationErrors.value = {};
        generalError.value = '';
        isSubmitting.value = false;
    }
});

const handleSubmit = async () => {
    isSubmitting.value = true;
    validationErrors.value = {};
    generalError.value = '';

    try {
        // Apply custom formatting if a function is provided
        const dataToSend = props.formatDataForApi(props.formData);
        console.log('BaseFormModal: Data being sent to API:', dataToSend); // Debugging

        let response;
        const lowerHttpMethod = props.httpMethod.toLowerCase();

        // Use window.axios or your equivalent HTTP client
        if (lowerHttpMethod === 'post') {
            response = await window.axios.post(props.apiEndpoint, dataToSend);
        } else if (lowerHttpMethod === 'put') {
            response = await window.axios.put(props.apiEndpoint, dataToSend);
        } else if (lowerHttpMethod === 'patch') {
            response = await window.axios.patch(props.apiEndpoint, dataToSend);
        } else if (lowerHttpMethod === 'delete') {
            response = await window.axios.delete(props.apiEndpoint, { data: dataToSend }); // DELETE with body
        } else {
            throw new Error(`Unsupported HTTP method: ${props.httpMethod}`);
        }

        success(props.successMessage);
        emit('submitted', response.data);
        emit('close'); // Close modal on success
    } catch (err) {
        console.error('BaseFormModal: API submission error:', err); // Debugging
        if (err.response) {
            if (err.response.status === 422) {
                // Validation errors
                validationErrors.value = err.response.data.errors;
                generalError.value = 'Please correct the errors in the form.';
            } else {
                // Other API errors
                generalError.value = err.response.data.message || 'An unexpected error occurred.';
            }
        } else {
            // Network errors or other unexpected issues
            generalError.value = 'A network error occurred or the server is unreachable.';
        }
        error(generalError.value);
        emit('error', err); // Emit the full error object
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="close">
        <div class="p-6">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-gray-900">{{ title }}</h2>
                <button @click="close" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- General Error Message (if any) -->
            <div v-if="generalError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ generalError }}</span>
            </div>

            <!-- Form Content Slot -->
            <!-- The default slot passes the validationErrors object to the consuming component -->
            <form @submit.prevent="handleSubmit">
                <slot :errors="validationErrors"></slot>

                <!-- Footer / Action Buttons -->
                <div v-if="showFooter" class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="close" type="button" :disabled="isSubmitting">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        :disabled="isSubmitting"
                        :class="{ 'opacity-75 cursor-not-allowed': isSubmitting }"
                    >
                        {{ isSubmitting ? 'Submitting...' : submitButtonText }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>

<style scoped>
/* Any base styling for the modal itself can go here, though Tailwind handles much of it. */
</style>
