<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AvailabilityModal from '@/Components/Availability/AvailabilityModal.vue';
import SingleDateAvailabilityModal from '@/Components/Availability/SingleDateAvailabilityModal.vue';

const props = defineProps({
    userId: {
        type: Number,
        default: null
    },
    isAdmin: {
        type: Boolean,
        default: false
    }
});

// State
const availabilities = ref([]);
const users = ref([]);
const loading = ref(false);
const error = ref('');
const startDate = ref('');
const endDate = ref('');
const selectedUserId = ref(props.userId);
const viewMode = ref('weekly'); // 'weekly' or 'daily'
const showAvailabilityModal = ref(false);
const showSingleDateModal = ref(false);
const selectedDate = ref('');

// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        console.log('Auth headers set in AvailabilityCalendar');
    }
};

// Fetch availabilities
const fetchAvailabilities = async () => {
    loading.value = true;
    error.value = '';

    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();

        let url = '/api/availabilities';
        const params = {};

        if (startDate.value) {
            params.start_date = startDate.value;
        }

        if (endDate.value) {
            params.end_date = endDate.value;
        }

        if (selectedUserId.value && props.isAdmin) {
            params.user_id = selectedUserId.value;
        }

        const response = await axios.get(url, { params });
        availabilities.value = response.data.availabilities;

        // Update date range if returned from API
        if (response.data.start_date) {
            startDate.value = response.data.start_date;
        }

        if (response.data.end_date) {
            endDate.value = response.data.end_date;
        }

        // If admin, fetch all users for the filter
        if (props.isAdmin && users.value.length === 0) {
            fetchUsers();
        }
    } catch (err) {
        console.error('Error fetching availabilities:', err);
        if (err.response && err.response.status === 401) {
            error.value = 'Authentication error. Please refresh the page or log in again.';
        } else {
            error.value = 'Failed to load availabilities. Please try again.';
        }
    } finally {
        loading.value = false;
    }
};

// Fetch users (for admin only)
const fetchUsers = async () => {
    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();

        const response = await axios.get('/api/users');
        users.value = response.data;
    } catch (err) {
        console.error('Error fetching users:', err);
        if (err.response && err.response.status === 401) {
            error.value = 'Authentication error. Please refresh the page or log in again.';
        }
    }
};

// Get week days
const weekDays = computed(() => {
    if (!startDate.value) return [];

    const days = [];
    const start = new Date(startDate.value);

    for (let i = 0; i < 7; i++) {
        const day = new Date(start);
        day.setDate(start.getDate() + i);
        days.push({
            date: day.toISOString().split('T')[0],
            label: day.toLocaleDateString('en-US', { weekday: 'short' }),
            fullLabel: day.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })
        });
    }

    return days;
});

// Group availabilities by date
const availabilitiesByDate = computed(() => {
    const grouped = {};

    weekDays.value.forEach(day => {
        grouped[day.date] = availabilities.value.filter(a => {
            // Extract date part from ISO date string (handles both "2025-07-25" and "2025-07-25T00:00:00.000000Z" formats)
            const availabilityDate = a.date.split('T')[0];
            return availabilityDate === day.date;
        });
    });

    return grouped;
});

// Navigate to previous week
const previousWeek = () => {
    const start = new Date(startDate.value);
    start.setDate(start.getDate() - 7);
    startDate.value = start.toISOString().split('T')[0];

    const end = new Date(endDate.value);
    end.setDate(end.getDate() - 7);
    endDate.value = end.toISOString().split('T')[0];

    fetchAvailabilities();
};

// Navigate to next week
const nextWeek = () => {
    const start = new Date(startDate.value);
    start.setDate(start.getDate() + 7);
    startDate.value = start.toISOString().split('T')[0];

    const end = new Date(endDate.value);
    end.setDate(end.getDate() + 7);
    endDate.value = end.toISOString().split('T')[0];

    fetchAvailabilities();
};

// Handle user filter change
const handleUserChange = (event) => {
    selectedUserId.value = event.target.value ? parseInt(event.target.value) : null;
    fetchAvailabilities();
};

// Open availability modal for a specific date
const openAvailabilityModal = (date) => {
    selectedDate.value = date;
    showAvailabilityModal.value = true;
};

// Open single date availability modal
const openSingleDateModal = (date) => {
    selectedDate.value = date;
    showSingleDateModal.value = true;
};

// Handle availability saved
const handleAvailabilitySaved = () => {
    fetchAvailabilities();
};

// Format time slot for display
const formatTimeSlot = (slot) => {
    return `${slot.start_time} - ${slot.end_time}`;
};

// Initialize component
onMounted(() => {
    // Ensure authentication headers are set as early as possible
    ensureAuthHeaders();

    // Set default date range to current week if not provided
    if (!startDate.value) {
        const today = new Date();
        const dayOfWeek = today.getDay(); // 0 = Sunday, 1 = Monday, etc.

        // Set to beginning of current week (Sunday)
        const sunday = new Date(today);
        sunday.setDate(today.getDate() - dayOfWeek);
        startDate.value = sunday.toISOString().split('T')[0];

        // Set to end of current week (Saturday)
        const saturday = new Date(today);
        saturday.setDate(today.getDate() + (6 - dayOfWeek));
        endDate.value = saturday.toISOString().split('T')[0];
    }

    // Small delay to ensure auth headers are set before making API requests
    setTimeout(() => {
        fetchAvailabilities();
    }, 100);
});

// Watch for prop changes
watch(() => props.userId, (newValue) => {
    if (newValue !== selectedUserId.value) {
        selectedUserId.value = newValue;
        fetchAvailabilities();
    }
});
</script>

<template>
    <div class="bg-white rounded-lg shadow-md p-6" data-test="availability-calendar">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4 sm:mb-0">Weekly Availability</h2>

            <div class="flex items-center space-x-4">
                <!-- User Filter (Admin Only) -->
                <div v-if="props.isAdmin" class="w-48">
                    <select
                        v-model="selectedUserId"
                        @change="handleUserChange"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    >
                        <option :value="null">All Users</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </div>

                <!-- Week Navigation -->
                <div class="flex items-center space-x-2">
                    <button
                        @click="previousWeek"
                        class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <span class="text-sm font-medium">
                        {{ new Date(startDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }} -
                        {{ new Date(endDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                    </span>

                    <button
                        @click="nextWeek"
                        class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center py-12">
            <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
            {{ error }}
        </div>

        <!-- Calendar View -->
        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th v-for="day in weekDays" :key="day.date" class="px-6 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex flex-col items-center">
                                <span class="text-lg font-bold">{{ day.label }}</span>
                                <span class="text-xs mt-1">{{ day.date }}</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td v-for="day in weekDays" :key="day.date" class="px-2 py-4 whitespace-nowrap text-sm text-gray-500 border-r border-gray-100 align-top">
                            <div class="min-h-[150px]">
                                <!-- Add Availability Button -->
                                <div class="mb-3 flex justify-center">
                                    <button
                                        @click="openSingleDateModal(day.date)"
                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:border-indigo-700 focus:shadow-outline-indigo active:bg-indigo-700 transition ease-in-out duration-150"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        Add
                                    </button>
                                </div>

                                <!-- Availabilities for this day -->
                                <div v-if="availabilitiesByDate[day.date] && availabilitiesByDate[day.date].length > 0">
                                    <div
                                        v-for="availability in availabilitiesByDate[day.date]"
                                        :key="availability.id"
                                        class="mb-2 p-2 rounded-md"
                                        :class="availability.is_available ? 'bg-green-50 border border-green-100' : 'bg-red-50 border border-red-100'"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium" :class="availability.is_available ? 'text-green-700' : 'text-red-700'">
                                                {{ availability.user ? availability.user.name + ' - ' : '' }}{{ availability.is_available ? 'Available' : 'Not Available' }}
                                            </span>
                                        </div>

                                        <div v-if="availability.is_available && availability.time_slots">
                                            <div v-for="(slot, index) in availability.time_slots" :key="index" class="text-xs text-gray-600 mt-1">
                                                {{ formatTimeSlot(slot) }}
                                            </div>
                                        </div>

                                        <div v-if="!availability.is_available && availability.reason" class="text-xs text-gray-600 mt-1">
                                            Reason: {{ availability.reason }}
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex items-center justify-center h-full text-gray-400 text-xs italic">
                                    No availability set
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Availability Modal -->
        <AvailabilityModal
            :show="showAvailabilityModal"
            :date="selectedDate"
            @close="showAvailabilityModal = false"
            @availability-saved="handleAvailabilitySaved"
        />

        <!-- Single Date Availability Modal -->
        <SingleDateAvailabilityModal
            :show="showSingleDateModal"
            :date="selectedDate"
            @close="showSingleDateModal = false"
            @availability-saved="handleAvailabilitySaved"
        />
    </div>
</template>
