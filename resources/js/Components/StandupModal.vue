<script setup>
import { reactive, ref, watch } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue'; // Import the new base modal
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    projectId: Number,
});

const emit = defineEmits(['close', 'standupAdded']);

// Form state for adding standup
const standupForm = reactive({
    project_id: null,
    yesterday: '',
    today: '',
    blockers: '',
});

// Watch for changes in projectId prop
watch(() => props.projectId, (newProjectId) => {
    if (newProjectId) {
        standupForm.project_id = newProjectId;
    }
}, { immediate: true });

// Reset form when modal is closed
watch(() => props.show, (isVisible) => {
    if (isVisible) {
        standupForm.project_id = props.projectId;
        standupForm.yesterday = '';
        standupForm.today = '';
        standupForm.blockers = '';
        // Note: BaseFormModal will handle clearing its own generalError and validationErrors
    }
});

// Handle the 'submitted' event from BaseFormModal
const handleStandupSubmitted = (responseData) => {
    // No need to show success notification here, BaseFormModal handles it
    emit('standupAdded'); // Notify parent that standup was added
    // BaseFormModal will automatically close if closeOnSuccess is true (default)
};

const handleSubmissionError = (error) => {
    // Optionally handle specific errors if needed, otherwise BaseFormModal displays a generic one
    console.error('Error in StandupModal submission:', error);
};

// Computed property for API endpoint
const apiEndpoint = ref('');
watch(() => props.projectId, (newProjectId) => {
    if (newProjectId) {
        apiEndpoint.value = `/api/projects/${newProjectId}/standup`;
    }
}, { immediate: true });
</script>

<template>
    <BaseFormModal
        :show="show"
        title="Daily Standup"
        :api-endpoint="apiEndpoint"
        http-method="post"
        :form-data="standupForm"
        submit-button-text="Submit Standup"
        success-message="Daily standup submitted successfully!"
        @close="$emit('close')"
        @submitted="handleStandupSubmitted"
        @error="handleSubmissionError"
    >
        <!-- Use a scoped slot to access validation errors from BaseFormModal -->
        <template #default="{ errors }">
            <div class="mb-4">
                <InputLabel for="yesterday" value="What did you work on yesterday?" />
                <TextInput
                    id="yesterday"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="standupForm.yesterday"
                    required
                />
                <InputError :message="errors.yesterday ? errors.yesterday[0] : ''" class="mt-2" />
            </div>

            <div class="mb-4">
                <InputLabel for="today" value="What will you work on today?" />
                <TextInput
                    id="today"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="standupForm.today"
                    required
                />
                <InputError :message="errors.today ? errors.today[0] : ''" class="mt-2" />
            </div>

            <div class="mb-4">
                <InputLabel for="blockers" value="Any blockers?" />
                <TextInput
                    id="blockers"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="standupForm.blockers"
                    placeholder="None"
                />
                <InputError :message="errors.blockers ? errors.blockers[0] : ''" class="mt-2" />
            </div>
        </template>
    </BaseFormModal>
</template>
