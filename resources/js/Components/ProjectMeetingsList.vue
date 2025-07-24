<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    projectId: {
        type: Number,
        required: true
    }
});

// State
const meetings = ref([]);
const loadingMeetings = ref(false);
const userTimezone = ref(''); // To store user's detected timezone

// Function to detect user's timezone
const detectUserTimezone = () => {
    try {
        userTimezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (error) {
        console.error('Error detecting user timezone:', error);
        userTimezone.value = 'UTC'; // Fallback if detection fails
    }
};

// Format meeting time with a specific timezone or user's local
const formatMeetingTime = (timeString, targetTimezone = null, isUtc = false) => {
    if (!timeString) return '';

    try {
        // If the time is in UTC format (new approach) or has 'Z' suffix
        if (isUtc || timeString.endsWith('Z')) {
            // If the meeting's timezone matches the user's timezone, no conversion needed
            if (targetTimezone && targetTimezone === userTimezone.value) {
                // Just format the time in the user's local timezone without explicit conversion
                const options = {
                    dateStyle: 'medium',
                    timeStyle: 'short'
                };
                return new Date(timeString).toLocaleString(undefined, options);
            }

            // Otherwise, perform the conversion to the specified timezone
            const options = {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: targetTimezone || userTimezone.value // Use targetTimezone if provided, else user's timezone
            };
            // The timeString from backend is UTC.
            // new Date() correctly interprets UTC time.
            // .toLocaleString() then converts this UTC time to the specified timeZone.
            return new Date(timeString).toLocaleString(undefined, options);
        }
        // Legacy format (not UTC)
        else {
            // If the meeting's timezone matches the user's timezone, no conversion needed
            if (targetTimezone && targetTimezone === userTimezone.value) {
                // Just format the time in the user's local timezone without explicit conversion
                const options = {
                    dateStyle: 'medium',
                    timeStyle: 'short'
                };
                return new Date(timeString).toLocaleString(undefined, options);
            }

            // Otherwise, perform the conversion to the specified timezone
            const options = {
                dateStyle: 'medium',
                timeStyle: 'short',
                timeZone: targetTimezone || userTimezone.value
            };
            return new Date(timeString).toLocaleString(undefined, options);
        }
    } catch (error) {
        console.error('Error formatting meeting time:', error);
        return new Date(timeString).toLocaleString(); // Fallback to default (user's local)
    }
};

// Format meeting time (time only) with a specific timezone or user's local
const formatMeetingTimeOnly = (timeString, targetTimezone = null, isUtc = false) => {
    if (!timeString) return '';

    try {
        // If the time is in UTC format (new approach) or has 'Z' suffix
        if (isUtc || timeString.endsWith('Z')) {
            // If the meeting's timezone matches the user's timezone, no conversion needed
            if (targetTimezone && targetTimezone === userTimezone.value) {
                // Just format the time in the user's local timezone without explicit conversion
                const options = {
                    timeStyle: 'short'
                };
                return new Date(timeString).toLocaleTimeString(undefined, options);
            }

            // Otherwise, perform the conversion to the specified timezone
            const options = {
                timeStyle: 'short',
                timeZone: targetTimezone || userTimezone.value // Use targetTimezone if provided, else user's timezone
            };
            // The timeString from backend is UTC.
            // new Date() correctly interprets UTC time.
            // .toLocaleTimeString() then converts this UTC time to the specified timeZone.
            return new Date(timeString).toLocaleTimeString(undefined, options);
        }
        // Legacy format (not UTC)
        else {
            // If the meeting's timezone matches the user's timezone, no conversion needed
            if (targetTimezone && targetTimezone === userTimezone.value) {
                // Just format the time in the user's local timezone without explicit conversion
                const options = {
                    timeStyle: 'short'
                };
                return new Date(timeString).toLocaleTimeString(undefined, options);
            }

            // Otherwise, perform the conversion to the specified timezone
            const options = {
                timeStyle: 'short',
                timeZone: targetTimezone || userTimezone.value
            };
            return new Date(timeString).toLocaleTimeString(undefined, options);
        }
    } catch (error) {
        console.error('Error formatting meeting time (time only):', error);
        return new Date(timeString).toLocaleTimeString(); // Fallback to default (user's local)
    }
};

// Fetch meetings for the project
const fetchMeetings = async () => {
    loadingMeetings.value = true;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/meetings`);
        meetings.value = response.data;
    } catch (error) {
        console.error('Error fetching project meetings:', error);
    } finally {
        loadingMeetings.value = false;
    }
};

// Delete a meeting
const deleteMeeting = async (googleEventId) => {
    if (!confirm('Are you sure you want to delete this meeting?')) {
        return;
    }

    try {
        await window.axios.delete(`/api/projects/${props.projectId}/meetings/${googleEventId}`);
        await fetchMeetings(); // Refresh meetings list
    } catch (error) {
        console.error('Error deleting meeting:', error);
        alert('Failed to delete meeting. Please try again.');
    }
};

// Expose methods to parent component
defineExpose({
    fetchMeetings
});

// Fetch meetings and detect user timezone when component is mounted
onMounted(() => {
    detectUserTimezone(); // Detect user's timezone first
    fetchMeetings();
});
</script>

<template>
    <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow mb-6">
        <h4 class="text-md font-semibold text-gray-900 mb-2">Upcoming Meetings</h4>
        <div v-if="loadingMeetings" class="text-sm text-gray-500">
            Loading meetings...
        </div>
        <div v-else-if="meetings.length === 0" class="text-sm text-gray-500">
            No upcoming meetings scheduled.
        </div>
        <div v-else class="space-y-3">
            <div v-for="meeting in meetings" :key="meeting.id"
                 class="p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">
                <div class="flex justify-between items-start">
                    <div>
                        <h5 class="font-medium text-gray-900">{{ meeting.summary }}</h5>
                        <p class="text-sm text-gray-600">
                            {{ formatMeetingTime(meeting.start_time, meeting.timezone, meeting.is_utc) }} - {{ formatMeetingTimeOnly(meeting.end_time, meeting.timezone, meeting.is_utc) }}
                        </p>
<!--                        <p v-if="meeting.timezone" class="text-xs text-gray-500 mt-1">-->
<!--                            <span class="font-medium">Timezone:</span> {{ meeting.timezone }}-->
<!--                        </p>-->
                        <p v-if="meeting.location" class="text-sm text-gray-600 mt-1">
                            <span class="font-medium">Location:</span> {{ meeting.location }}
                        </p>
                    </div>
                    <div class="flex space-x-2">
                        <a :href="meeting.google_event_link" target="_blank"
                           class="text-blue-600 hover:text-blue-800" title="View in Google Calendar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </a>
                        <a v-if="meeting.google_meet_link" :href="meeting.google_meet_link" target="_blank"
                           class="text-green-600 hover:text-green-800" title="Join Google Meet">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </a>
                        <button @click="deleteMeeting(meeting.google_event_id)"
                                class="text-red-600 hover:text-red-800" title="Delete Meeting">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
