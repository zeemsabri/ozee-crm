<script setup>
import { ref, onMounted, defineExpose, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';

// Upcoming meetings state
const upcomingMeetings = ref([]);
const loadingMeetings = ref(true);
const meetingsError = ref('');
const expandMeetings = ref(false);

const meetingsRef = ref(null);

// Fetches all meetings the user is invited to
const fetchUpcomingMeetings = async () => {
    loadingMeetings.value = true;
    meetingsError.value = '';
    try {
        const response = await axios.get('/api/user/meetings');
        upcomingMeetings.value = response.data;
    } catch (err) {
        meetingsError.value = 'Failed to load upcoming meetings';
        console.error('Error fetching upcoming meetings:', err);
    } finally {
        loadingMeetings.value = false;
    }
};

// Toggles the visibility of the meetings section
const toggleMeetings = () => {
    expandMeetings.value = !expandMeetings.value;
};

// Expose the method to be called from the parent component
const showMeetingsAndScroll = () => {
    expandMeetings.value = true;
    fetchUpcomingMeetings();
    nextTick(() => {
        meetingsRef.value.scrollIntoView({ behavior: 'smooth' });
    });
};
defineExpose({ showMeetingsAndScroll });


// Fetch data on component mount
onMounted(() => {
    fetchUpcomingMeetings();
});
</script>

<template>
    <div ref="meetingsRef" class="md:col-span-3 bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-all duration-300 hover:shadow-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <h3 class="text-xl font-semibold text-gray-900 mb-2 sm:mb-0">Your Upcoming Meetings</h3>
            <button
                @click="toggleMeetings"
                class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition ease-in-out duration-150 w-48"
            >
                {{ expandMeetings ? 'Collapse Meetings' : 'View Meetings (' + upcomingMeetings.length + ')' }}
            </button>
        </div>

        <div v-if="expandMeetings" class="mt-4">
            <div v-if="meetingsError" class="text-center text-sm text-red-500 py-6">{{ meetingsError }}</div>
            <div v-else-if="upcomingMeetings.length === 0 && !loadingMeetings" class="text-center text-sm text-gray-500 py-6">
                No upcoming meetings found. You'll see meetings here when you're invited to one.
            </div>
            <div v-else class="mt-3 overflow-x-auto rounded-lg border border-gray-200 shadow-sm relative">
                <!-- Loading overlay for meetings -->
                <div v-if="loadingMeetings" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg z-10">
                    <svg class="animate-spin h-8 w-8 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="ml-3 text-purple-700">Loading meetings...</span>
                </div>

                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meeting</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organizer</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="meeting in upcomingMeetings" :key="meeting.id" class="hover:bg-gray-50 transition-colors duration-100">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ meeting.summary }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ meeting.project?.name || 'N/A' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ new Date(meeting.start_time).toLocaleString() }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                            {{ meeting.creator?.name || 'N/A' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                            <div class="flex space-x-2">
                                <a v-if="meeting.google_meet_link" :href="meeting.google_meet_link" target="_blank"
                                   class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:border-green-700 focus:ring active:bg-green-700 transition ease-in-out duration-150">
                                    Join Meeting
                                </a>
                                <Link v-if="meeting.project_id" :href="`/projects/${meeting.project_id}`"
                                      class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring active:bg-indigo-700 transition ease-in-out duration-150">
                                    View Project
                                </Link>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
