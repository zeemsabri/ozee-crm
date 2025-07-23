<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    projectId: {
        type: Number,
        required: true,
    },
    projectUsers: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'saved']);

// Form data
const form = ref({
    summary: '',
    description: '',
    start_datetime: '',
    end_datetime: '',
    attendee_user_ids: [],
    location: '',
    with_google_meet: true,
});

// Form errors
const errors = ref({});
const processing = ref(false);
const generalError = ref('');

// Reset form when modal is closed
watch(() => props.show, (value) => {
    if (!value) {
        resetForm();
    }
});

// Format date for datetime-local input
const formatDateForInput = (date) => {
    const d = new Date(date);
    // Format as YYYY-MM-DDThh:mm
    return d.toISOString().slice(0, 16);
};

// Set default start and end times (now + 1 hour, now + 2 hours)
const setDefaultTimes = () => {
    const now = new Date();
    const startTime = new Date(now.getTime() + 60 * 60 * 1000); // Now + 1 hour
    const endTime = new Date(now.getTime() + 2 * 60 * 60 * 1000); // Now + 2 hours

    form.value.start_datetime = formatDateForInput(startTime);
    form.value.end_datetime = formatDateForInput(endTime);
};

// Reset form to default values
const resetForm = () => {
    form.value = {
        summary: '',
        description: '',
        start_datetime: '',
        end_datetime: '',
        attendee_user_ids: [],
        location: '',
        with_google_meet: true,
    };
    setDefaultTimes();
    errors.value = {};
    generalError.value = '';
    processing.value = false;
};

// Initialize form with default values
setDefaultTimes();

// Format date from ISO format (YYYY-MM-DDThh:mm) to MySQL format (Y-m-d H:i:s)
const formatDateForBackend = (isoDate) => {
    if (!isoDate) return '';
    // Replace 'T' with space and add seconds
    return isoDate.replace('T', ' ') + ':00';
};

// Submit form
const submit = async () => {
    processing.value = true;
    errors.value = {};
    generalError.value = '';

    try {
        // Create a copy of the form data to avoid modifying the original
        const formData = { ...form.value };

        // Format dates for backend
        formData.start_datetime = formatDateForBackend(formData.start_datetime);
        formData.end_datetime = formatDateForBackend(formData.end_datetime);

        const response = await window.axios.post(
            `/api/projects/${props.projectId}/meetings`,
            formData
        );

        emit('saved', response.data);
        emit('close');
    } catch (error) {
        processing.value = false;

        if (error.response) {
            if (error.response.status === 422) {
                errors.value = error.response.data.errors;
            } else {
                generalError.value = error.response.data.message || 'An error occurred while creating the meeting.';
            }
        } else {
            generalError.value = 'An unexpected error occurred. Please try again.';
            console.error('Error creating meeting:', error);
        }
    }
};

// Close modal
const close = () => {
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="close" max-width="2xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Schedule a Meeting
            </h2>

            <div class="mt-6 space-y-6">
                <div>
                    <InputLabel for="summary" value="Meeting Title" />
                    <TextInput
                        id="summary"
                        v-model="form.summary"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Project Status Meeting"
                        required
                    />
                    <InputError :message="errors.summary ? errors.summary[0] : ''" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="description" value="Description" />
                    <textarea
                        id="description"
                        v-model="form.description"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows="3"
                        placeholder="Meeting agenda and details..."
                    ></textarea>
                    <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <InputLabel for="start_datetime" value="Start Date & Time" />
                        <TextInput
                            id="start_datetime"
                            v-model="form.start_datetime"
                            type="datetime-local"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="errors.start_datetime ? errors.start_datetime[0] : ''" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="end_datetime" value="End Date & Time" />
                        <TextInput
                            id="end_datetime"
                            v-model="form.end_datetime"
                            type="datetime-local"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError :message="errors.end_datetime ? errors.end_datetime[0] : ''" class="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel for="attendees" value="Attendees" />
                    <Multiselect
                        id="attendees"
                        v-model="form.attendee_user_ids"
                        :options="props.projectUsers.map(user => user.id)"
                        :custom-label="(id) => {
                            const user = props.projectUsers.find(u => u.id === id);
                            return user ? `${user.name} (${user.email})` : '';
                        }"
                        placeholder="Select attendees"
                        :multiple="true"
                        :close-on-select="false"
                        :clear-on-select="false"
                        :preserve-search="true"
                        :show-labels="false"
                        class="mt-1"
                    />
                    <p class="mt-1 text-sm text-gray-500">
                        Select project members to invite to this meeting
                    </p>
                    <InputError :message="errors['attendee_user_ids'] ? errors['attendee_user_ids'][0] : ''" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="location" value="Location (Optional)" />
                    <TextInput
                        id="location"
                        v-model="form.location"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="Office, Conference Room, etc."
                    />
                    <InputError :message="errors.location ? errors.location[0] : ''" class="mt-2" />
                </div>

                <div class="flex items-center">
                    <input
                        id="with_google_meet"
                        v-model="form.with_google_meet"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label for="with_google_meet" class="ml-2 block text-sm text-gray-900">
                        Create Google Meet video conference
                    </label>
                </div>

                <div v-if="generalError" class="text-sm text-red-600">
                    {{ generalError }}
                </div>

                <div class="flex justify-end mt-6 space-x-3">
                    <SecondaryButton @click="close">
                        Cancel
                    </SecondaryButton>

                    <PrimaryButton
                        @click="submit"
                        :class="{ 'opacity-25': processing }"
                        :disabled="processing"
                    >
                        Schedule Meeting
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>
</template>
