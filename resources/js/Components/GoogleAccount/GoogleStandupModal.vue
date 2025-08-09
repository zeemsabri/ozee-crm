<script setup>
import { reactive, ref, watch, computed } from 'vue';
import axios from 'axios';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { showSuccessNotification, showErrorNotification } from '@/Utils/notification';

const props = defineProps({
    show: Boolean,
    projectId: Number,
    spaceName: String,
});

const emit = defineEmits(['close', 'standupAdded']);

// Form state for adding standup
const standupForm = reactive({
    space_name: '',
    yesterday: '',
    today: '',
    blockers: '',
});

// Watch for changes in projectId and spaceName props
watch(() => props.projectId, (newProjectId) => {
    if (newProjectId) {
        standupForm.project_id = newProjectId;
    }
}, { immediate: true });

watch(() => props.spaceName, (newSpaceName) => {
    if (newSpaceName) {
        standupForm.space_name = newSpaceName;
    }
}, { immediate: true });

// Reset form when modal is closed
watch(() => props.show, (isVisible) => {
    if (isVisible) {
        standupForm.project_id = props.projectId;
        standupForm.space_name = props.spaceName;
        standupForm.yesterday = '';
        standupForm.today = '';
        standupForm.blockers = '';
    }
});

// Loading state
const isSubmitting = ref(false);

// Submit the standup using the Google Chat API
const submitStandup = async () => {
    if (!standupForm.yesterday || !standupForm.today) {
        showErrorNotification('Please fill in the required fields');
        return;
    }

    try {
        isSubmitting.value = true;
        const response = await axios.post('/api/user/google-chat/standups', {
            space_name: standupForm.space_name,
            yesterday: standupForm.yesterday,
            today: standupForm.today,
            blockers: standupForm.blockers || '',
        });

        if (response.data.success) {
            showSuccessNotification('Standup added successfully!');
            emit('standupAdded');
            emit('close');
        } else {
            showErrorNotification(response.data.message || 'Failed to add standup');
        }
    } catch (error) {
        console.error('Error adding standup:', error);
        if (error.response && error.response.data.message) {
            showErrorNotification(error.response.data.message);
        } else {
            showErrorNotification('Failed to add standup. Please try again.');
        }

        // If the error is related to Google credentials, we might want to redirect
        if (error.response && error.response.data.redirect_url) {
            window.location.href = error.response.data.redirect_url;
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50">
        <div class="fixed inset-0 transform transition-all" @click="$emit('close')">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-lg mx-auto">
            <div class="px-6 py-4">
                <div class="text-lg font-medium text-gray-900">
                    Daily Standup
                </div>

                <div class="mt-4">
                    <div class="mb-4">
                        <InputLabel for="yesterday" value="What did you work on yesterday?" />
                        <TextInput
                            id="yesterday"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="standupForm.yesterday"
                            required
                        />
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
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-100 text-right">
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150 mr-2"
                    @click="$emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    @click="submitStandup"
                    :disabled="isSubmitting"
                >
                    <span v-if="isSubmitting">Submitting...</span>
                    <span v-else>Submit Standup</span>
                </button>
            </div>
        </div>
    </div>
</template>
