<script setup>
import { reactive, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { success, error } from '@/Utils/notification';

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

const errors = ref({});
const generalError = ref('');
const isSubmitting = ref(false);

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
        errors.value = {};
        generalError.value = '';
        isSubmitting.value = false;
    }
});

const submitStandup = async () => {
    errors.value = {};
    generalError.value = '';
    isSubmitting.value = true;

    try {
        // Format the standup content
        const formattedContent = `
**Daily Standup**

**Yesterday:** ${standupForm.yesterday}

**Today:** ${standupForm.today}

**Blockers:** ${standupForm.blockers || 'None'}
`.trim();

        // Send the standup data to the API
        await window.axios.post(`/api/projects/${standupForm.project_id}/standup`, {
            content: formattedContent,
            yesterday: standupForm.yesterday,
            today: standupForm.today,
            blockers: standupForm.blockers
        });

        success('Standup submitted successfully!');
        emit('standupAdded');
        emit('close');
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
            error(error.response.data.message);
        } else {
            generalError.value = 'Failed to submit standup.';
            error('Failed to submit standup.');
            console.error('Error submitting standup:', error);
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Modal :show="show" @close="$emit('close')">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Daily Standup</h2>
            <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
            <form @submit.prevent="submitStandup">
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

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="$emit('close')" :disabled="isSubmitting">Cancel</SecondaryButton>
                    <PrimaryButton
                        class="ms-3"
                        type="submit"
                        :disabled="isSubmitting"
                        :class="{ 'opacity-75 cursor-not-allowed': isSubmitting }"
                    >
                        {{ isSubmitting ? 'Submitting...' : 'Submit Standup' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
