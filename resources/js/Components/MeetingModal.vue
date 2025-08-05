<script setup>
import { ref, watch, onMounted, computed, reactive, nextTick } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import CustomMultiSelect from '@/Components/CustomMultiSelect.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

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
    projectName: {
        type: String,
        default: 'Project'
    }
});

const emit = defineEmits(['close', 'saved']);

// Get user's timezone
const getUserTimezone = () => {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
};

// Raw list of timezone strings
const rawTimezoneStrings = [
    'Africa/Cairo', 'Africa/Johannesburg', 'Africa/Lagos',
    'America/Anchorage', 'America/Bogota', 'America/Chicago', 'America/Denver',
    'America/Los_Angeles', 'America/Mexico_City', 'America/New_York', 'America/Phoenix',
    'America/Sao_Paulo', 'America/Toronto', 'America/Vancouver',
    'Asia/Dubai', 'Asia/Hong_Kong', 'Asia/Jerusalem', 'Asia/Kolkata', 'Asia/Seoul',
    'Asia/Shanghai', 'Asia/Singapore', 'Asia/Tokyo',
    'Australia/Melbourne', 'Australia/Perth', 'Australia/Sydney',
    'Europe/Amsterdam', 'Europe/Berlin', 'Europe/Dublin', 'Europe/Istanbul',
    'Europe/London', 'Europe/Madrid', 'Europe/Moscow', 'Europe/Paris', 'Europe/Rome',
    'Pacific/Auckland', 'Pacific/Honolulu',
    'UTC', 'GMT', // Common abbreviations
    // UK specific
    'Europe/Belfast',
    // USA Specific (more common zones beyond just NY/LA/Chicago/Denver/Phoenix)
    'America/Adak', 'America/Boise', 'America/Detroit', 'America/Indianapolis', 'America/Juneau',
    'America/Louisville', 'America/Menominee', 'America/Nome', 'America/Sitka', 'America/Yakutat',
    // Pakistan
    'Asia/Karachi',
    // India
    'Asia/Kolkata',
    // New Zealand
    'Pacific/Auckland', 'Pacific/Chatham', // New Zealand and Chatham Islands
    // More Europe
    'Europe/Athens', 'Europe/Brussels', 'Europe/Budapest', 'Europe/Copenhagen',
    'Europe/Helsinki', 'Europe/Lisbon', 'Europe/Oslo', 'Europe/Stockholm', 'Europe/Zurich',
    'Europe/Vienna', 'Europe/Warsaw', 'Europe/Zurich', 'Europe/Sofia', 'Europe/Prague',
    'Europe/Malta', 'Europe/Luxembourg', 'Europe/Kiev', 'Europe/Rome',
].sort(); // Sort the timezones alphabetically

// Formatted list of timezones for SelectDropdown (value/label objects)
const allTimezones = computed(() => {
    return rawTimezoneStrings.map(tz => ({ value: tz, label: tz }));
});

// User's detected timezone, ensuring it's in our list or defaults to UTC
const userTimezone = computed(() => {
    const detectedTz = getUserTimezone();
    const foundInList = rawTimezoneStrings.includes(detectedTz);
    console.log('DEBUG: Is detected timezone in list?', foundInList, 'Detected:', detectedTz);

    if (foundInList) {
        return detectedTz;
    }
    console.log('DEBUG: Falling back to UTC for timezone as detected timezone not in list.');
    return 'UTC';
});

// Form data (reactive for BaseFormModal)
const form = reactive({
    summary: '',
    description: '',
    start_datetime: '',
    end_datetime: '',
    attendee_user_ids: [], // This will hold an array of user IDs
    location: '',
    with_google_meet: true,
    timezone: userTimezone.value, // Default to user's timezone string
    enable_recording: false,
});

// Computed property for the full title with the project name appended
const fullTitle = computed({
    get() {
        return form.summary ? `${form.summary} - ${props.projectName}` : '';
    },
    set(value) {
        // This setter is needed for v-model. It's a bit of a hack but it's the
        // best way to handle this without a separate field.
        const projectSuffix = ` - ${props.projectName}`;
        if (value.endsWith(projectSuffix)) {
            form.summary = value.slice(0, -projectSuffix.length);
        } else {
            form.summary = value;
        }
    }
});


// Reset form to default values
const resetForm = () => {
    form.summary = '';
    form.description = '';
    form.attendee_user_ids = []; // Ensure this is reset to an empty array
    form.location = '';
    form.with_google_meet = true;
    form.timezone = userTimezone.value; // Reset to detected timezone string
    form.enable_recording = false;
    setDefaultTimes(); // Re-set datetimes
    console.log('DEBUG: Form reset. Current timezone in form:', form.timezone);
};

// Format date for datetime-local input
const formatDateForInput = (date) => {
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

// Set default start and end times (now + 1 hour, now + 2 hours)
const setDefaultTimes = () => {
    const now = new Date();
    const startTime = new Date(now.getTime() + 60 * 60 * 1000); // Now + 1 hour
    const endTime = new Date(now.getTime() + 2 * 60 * 60 * 1000); // Now + 2 hours

    form.start_datetime = formatDateForInput(startTime);
    form.end_datetime = formatDateForInput(endTime);
    console.log('DEBUG: Default times set. Start:', form.start_datetime, 'End:', form.end_datetime);
};

// Initialize form with default values on component setup
setDefaultTimes();

// Watch for the modal opening to reset form
watch(() => props.show, (value) => {
    if (value) {
        resetForm();
    }
}, { immediate: true }); // Use immediate to set initial timezone on first render

const formatDateForBackend = (isoDate) => {
    if (!isoDate) return '';
    console.log(isoDate);
    // Replace 'T' with space and add seconds
    return isoDate.replace('T', ' ') + ':00';
};

// Function to format form data before sending to API if needed
const formatDataForApi = (formData) => {
    const dataToSend = { ...formData }; // Create a copy

    // Use the computed fullTitle for the summary
    dataToSend.summary = fullTitle.value;

    dataToSend.start_datetime = formatDateForBackend(formData.start_datetime);
    dataToSend.end_datetime = formatDateForBackend(formData.end_datetime);

    console.log(dataToSend);
    return dataToSend;
};

// API endpoint for BaseFormModal
const apiEndpoint = computed(() => `/api/projects/${props.projectId}/meetings`);

// Handle successful submission from BaseFormModal
const handleSaved = (responseData) => {
    emit('saved', responseData); // Pass the response data up to parent
};

// Handle submission error from BaseFormModal (optional, BaseFormModal shows a generic error)
const handleSubmissionError = (error) => {
    console.error("DEBUG: MeetingModal submission error:", error);
    // You could add more specific error handling here if required, e.g., for Google Calendar API issues
};

// Preview data for the sidebar
const formattedDateRange = computed(() => {
    if (!form.start_datetime || !form.end_datetime) return '';
    const start = new Date(form.start_datetime);
    const end = new Date(form.end_datetime);
    const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const timeOptions = { hour: '2-digit', minute: '2-digit' };

    const startDate = start.toLocaleDateString(undefined, dateOptions);
    const startTime = start.toLocaleTimeString(undefined, timeOptions);
    const endTime = end.toLocaleTimeString(undefined, timeOptions);

    if (start.toDateString() === end.toDateString()) {
        return `${startDate}, ${startTime} - ${endTime}`;
    } else {
        return `${startDate} ${startTime} - ${end.toLocaleDateString(undefined, dateOptions)} ${endTime}`;
    }
});

const previewAttendees = computed(() => {
    return props.projectUsers
        .filter(user => form.attendee_user_ids.includes(user.id))
        .map(user => user.name);
});
</script>

<template>
    <BaseFormModal
        :show="show"
        title="Schedule a Meeting"
        :api-endpoint="apiEndpoint"
        http-method="post"
        :form-data="form"
        :format-data-for-api="formatDataForApi"
        submit-button-text="Schedule Meeting"
        success-message="Meeting scheduled successfully!"
        @close="$emit('close')"
        @submitted="handleSaved"
        @error="handleSubmissionError"
    >
        <template #default="{ errors }">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-6">
                <!-- Left Side: Form Fields -->
                <div class="space-y-6">
                    <div>
                        <InputLabel for="summary" value="Meeting Title" />
                        <p class="mt-1 text-sm text-gray-500 mb-2">
                            Enter a short, descriptive title for the meeting (e.g., Project Standup). The project name will be added automatically.
                        </p>
                        <TextInput
                            id="summary"
                            v-model="form.summary"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g., Project Standup"
                            required
                        />
                        <InputError :message="errors.summary ? errors.summary[0] : ''" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="description" value="Description" />
                        <p class="mt-1 text-sm text-gray-500 mb-2">
                            Enter a short, descriptive agenda of the meeting.
                        </p>
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
                        <CustomMultiSelect
                            id="attendees"
                            v-model="form.attendee_user_ids"
                            :options="props.projectUsers"
                            label-key="name"
                            track-by="id"
                            placeholder="Select attendees"
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

                    <div>
                        <InputLabel for="timezone" value="Timezone" />
                        <SelectDropdown
                            id="timezone"
                            v-model="form.timezone"
                            :options="allTimezones"
                            value-key="value"
                            label-key="label"
                            placeholder="Select timezone"
                            class="mt-1"
                        />
                        <p class="mt-1 text-sm text-gray-500">
                            Select your timezone for this meeting
                        </p>
                        <InputError :message="errors.timezone ? errors.timezone[0] : ''" class="mt-2" />
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

                    <div class="flex items-center">
                        <input
                            id="enable_recording"
                            v-model="form.enable_recording"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="enable_recording" class="ml-2 block text-sm text-gray-900">
                            Enable recording and transcript
                        </label>
                    </div>
                </div>

                <!-- Right Side: Live Preview -->
                <div class="space-y-6 bg-gray-50 p-6 rounded-lg shadow-inner border border-gray-200">
                    <h4 class="text-xl font-bold text-gray-800">Meeting Preview</h4>
                    <div class="space-y-4">
                        <div>
                            <h5 class="font-semibold text-sm text-gray-600 mb-1">Title</h5>
                            <p class="text-gray-900 font-medium break-words">{{ fullTitle || 'Untitled Meeting' }}</p>
                        </div>
                        <div>
                            <h5 class="font-semibold text-sm text-gray-600 mb-1">Date & Time</h5>
                            <p class="text-gray-900">{{ formattedDateRange || 'No date and time selected.' }}</p>
                            <p v-if="form.timezone" class="text-xs text-gray-500 mt-1">Timezone: {{ form.timezone }}</p>
                        </div>
                        <div v-if="form.description">
                            <h5 class="font-semibold text-sm text-gray-600 mb-1">Description</h5>
                            <p class="text-gray-900 whitespace-pre-wrap">{{ form.description }}</p>
                        </div>
                        <div v-if="previewAttendees.length > 0">
                            <h5 class="font-semibold text-sm text-gray-600 mb-1">Attendees</h5>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="attendeeName in previewAttendees"
                                    :key="attendeeName"
                                    class="px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800"
                                >
                                    {{ attendeeName }}
                                </span>
                            </div>
                        </div>
                        <div v-if="form.location">
                            <h5 class="font-semibold text-sm text-gray-600 mb-1">Location</h5>
                            <p class="text-gray-900">{{ form.location }}</p>
                        </div>
                        <div>
                            <h5 class="font-semibold text-sm text-gray-600 mb-1">Options</h5>
                            <ul class="list-disc list-inside space-y-1 text-gray-900">
                                <li v-if="form.with_google_meet">Google Meet enabled</li>
                                <li v-if="form.enable_recording">Recording and transcript enabled</li>
                                <li v-if="!form.with_google_meet && !form.enable_recording">No special options selected.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </BaseFormModal>
</template>

<style scoped>
/* No specific scoped styles needed here, as styling is handled by Tailwind and the custom components. */
</style>
