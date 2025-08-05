<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

// Props
const props = defineProps({
    dueTodayTasksCount: {
        type: Number,
        default: 0
    },
    overdueTasksCount: {
        type: Number,
        default: 0
    }
});

// Emits
const emit = defineEmits(['view-tasks', 'view-meetings']);

// State
const meetingsToday = ref(0);
const loadingMeetings = ref(true);
const unreadEmails = ref(0); // Placeholder for future implementation
const standupCount = ref(0);
const loadingStandups = ref(true);

// Get current user
const authUser = computed(() => usePage().props.auth.user);

// Fetch meetings for today
const fetchMeetingsToday = async () => {
    loadingMeetings.value = true;
    try {
        const today = new Date().toISOString().split('T')[0]; // Format: YYYY-MM-DD
        const response = await axios.get('/api/user/meetings', {
            params: { date: today }
        });
        meetingsToday.value = response.data.length;
    } catch (err) {
        console.error('Error fetching meetings:', err);
    } finally {
        loadingMeetings.value = false;
    }
};

// Fetch standups added today
const fetchStandupsToday = async () => {
    loadingStandups.value = true;
    try {
        const today = new Date().toISOString().split('T')[0]; // Format: YYYY-MM-DD
        const response = await axios.get('/api/project-notes', {
            params: {
                type: 'standup',
                date: today,
                user_id: authUser.value.id
            }
        });
        standupCount.value = response.data.length;
    } catch (err) {
        console.error('Error fetching standups:', err);
        // If the API doesn't support these parameters, we'll just show 0
    } finally {
        loadingStandups.value = false;
    }
};

// Handle view tasks click
const handleViewTasks = () => {
    emit('view-tasks');
};

// Handle view meetings click
const handleViewMeetings = () => {
    emit('view-meetings');
};

// Fetch data on component mount
onMounted(() => {
    fetchMeetingsToday();
    fetchStandupsToday();
});
</script>

<template>
    <div class="md:col-span-3 bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-all duration-300 hover:shadow-2xl">
        <h3 class="text-xl font-semibold text-gray-900 mb-6">Today's Overview</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
            <!-- Tasks Due Today -->
            <div class="bg-amber-50 rounded-lg p-4 flex flex-col items-center justify-center border border-amber-200 hover:shadow-md transition-all cursor-pointer" @click="handleViewTasks">
                <div class="text-3xl font-bold text-amber-600 mb-2">{{ dueTodayTasksCount }}</div>
                <div class="text-sm text-gray-700 text-center">Tasks Due Today</div>
                <div class="mt-2 text-xs text-amber-600 hover:underline">View Tasks</div>
            </div>

            <!-- Overdue Tasks -->
            <div class="bg-red-50 rounded-lg p-4 flex flex-col items-center justify-center border border-red-200 hover:shadow-md transition-all cursor-pointer" @click="handleViewTasks">
                <div class="text-3xl font-bold text-red-600 mb-2">{{ overdueTasksCount }}</div>
                <div class="text-sm text-gray-700 text-center">Overdue Tasks</div>
                <div class="mt-2 text-xs text-red-600 hover:underline">View Tasks</div>
            </div>

            <!-- Unread Emails (Placeholder) -->
            <div class="bg-blue-50 rounded-lg p-4 flex flex-col items-center justify-center border border-blue-200 hover:shadow-md transition-all cursor-pointer">
                <div class="text-3xl font-bold text-blue-600 mb-2">{{ unreadEmails }}</div>
                <div class="text-sm text-gray-700 text-center">Unread Emails</div>
                <div class="mt-2 text-xs text-blue-600 hover:underline">Coming Soon</div>
            </div>

            <!-- Meetings Today -->
            <div class="bg-purple-50 rounded-lg p-4 flex flex-col items-center justify-center border border-purple-200 hover:shadow-md transition-all cursor-pointer" @click="handleViewMeetings">
                <div v-if="loadingMeetings" class="text-3xl font-bold text-purple-600 mb-2 opacity-50">...</div>
                <div v-else class="text-3xl font-bold text-purple-600 mb-2">{{ meetingsToday }}</div>
                <div class="text-sm text-gray-700 text-center">Meetings Today</div>
                <div class="mt-2 text-xs text-purple-600 hover:underline">View Meetings</div>
            </div>

            <!-- Standups Added Today -->
            <div class="bg-green-50 rounded-lg p-4 flex flex-col items-center justify-center border border-green-200 hover:shadow-md transition-all">
                <div v-if="loadingStandups" class="text-3xl font-bold text-green-600 mb-2 opacity-50">...</div>
                <div v-else class="text-3xl font-bold text-green-600 mb-2">{{ standupCount }}</div>
                <div class="text-sm text-gray-700 text-center">Standups Today</div>
                <div class="mt-2 text-xs text-green-600">Your Updates</div>
            </div>
        </div>
    </div>
</template>
