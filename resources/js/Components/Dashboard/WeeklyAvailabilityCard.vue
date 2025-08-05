<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const authUser = computed(() => usePage().props.auth.user);

// Weekly availability state
const weeklyAvailability = ref({
    availabilities: [],
    start_date: '',
    end_date: ''
});
const loadingAvailability = ref(false);
const availabilityError = ref('');

// Helper function to format dates for display
const formatDateDisplay = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        weekday: 'short', month: 'short', day: 'numeric'
    });
};

// Fetches user's weekly availability from the API
const fetchWeeklyAvailability = async () => {
    loadingAvailability.value = true;
    availabilityError.value = '';
    try {
        const now = new Date();
        const startDate = new Date(now);
        startDate.setDate(now.getDate() - now.getDay() + (now.getDay() === 0 ? -6 : 1)); // Set to Monday
        const endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 6); // Set to Sunday

        const formatDateForApi = (date) => {
            return date.toISOString().split('T')[0];
        };

        const response = await axios.get('/api/availabilities', {
            params: {
                user_id: authUser.value.id,
                start_date: formatDateForApi(startDate),
                end_date: formatDateForApi(endDate)
            }
        });

        weeklyAvailability.value = response.data;
    } catch (err) {
        availabilityError.value = 'Failed to load availability data';
        console.error('Error fetching weekly availability:', err);
    } finally {
        loadingAvailability.value = false;
    }
};

// Helper: Get day name (e.g., 'Mon') for a given day index (1-7 for Mon-Sun)
const getDayName = (dayIndex) => {
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return days[dayIndex - 1];
};

// Helper: Get formatted date for a given day index (e.g., 'Jul 29')
const getDayDate = (dayIndex) => {
    const date = new Date(weeklyAvailability.value.start_date);
    date.setDate(date.getDate() + dayIndex - 1);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Helper: Determines the availability status and relevant data for a given day index
const getAvailabilityStatus = (dayIndex) => {
    const date = new Date(weeklyAvailability.value.start_date);
    date.setDate(date.getDate() + dayIndex - 1);
    const currentDateString = date.toISOString().split('T')[0];

    const dayAvailabilities = weeklyAvailability.value.availabilities.filter(
        a => a.date.split('T')[0] === currentDateString
    );

    if (dayAvailabilities.length === 0) {
        return { status: 'not-set' };
    }

    const isUnavailable = dayAvailabilities.some(a => !a.is_available);
    if (isUnavailable) {
        const unavailableEntry = dayAvailabilities.find(a => !a.is_available);
        return { status: 'unavailable', reason: unavailableEntry?.reason || 'Not specified' };
    }

    const allTimeSlots = [];
    dayAvailabilities.forEach(availability => {
        if (availability.is_available && availability.time_slots && Array.isArray(availability.time_slots)) {
            availability.time_slots.forEach(slot => {
                allTimeSlots.push(`${slot.start_time} - ${slot.end_time}`);
            });
        }
    });

    return { status: 'available', slots: allTimeSlots.length > 0 ? allTimeSlots : ['All Day'] };
};

// Helper: Returns Tailwind CSS classes for the availability day card based on its status
const getDayAvailabilityClass = (dayIndex) => {
    const statusInfo = getAvailabilityStatus(dayIndex);
    let classes = '';

    if (statusInfo.status === 'available') {
        classes = 'border-green-300 bg-green-50/50';
    } else if (statusInfo.status === 'unavailable') {
        classes = 'border-red-300 bg-red-50/50';
    } else {
        classes = 'border-gray-200 bg-gray-50'; // Not set
    }

    const today = new Date();
    const currentDayDate = new Date(weeklyAvailability.value.start_date);
    currentDayDate.setDate(currentDayDate.getDate() + dayIndex - 1);

    if (today.toDateString() === currentDayDate.toDateString()) {
        classes += ' ring-2 ring-indigo-500 ring-offset-2';
    }

    return classes;
};

// Fetch data on component mount
onMounted(() => {
    fetchWeeklyAvailability();
});
</script>

<template>
    <div class="md:col-span-3 bg-white overflow-hidden shadow-xl sm:rounded-lg p-8 transition-all duration-300 hover:shadow-2xl">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">Your Weekly Availability</h3>
        <p class="mb-6 text-md text-gray-600">
            Availability for:
            <span class="font-semibold text-indigo-700">
                {{ weeklyAvailability.start_date ? formatDateDisplay(weeklyAvailability.start_date) : '' }}
                to
                {{ weeklyAvailability.end_date ? formatDateDisplay(weeklyAvailability.end_date) : '' }}
            </span>
        </p>

        <div v-if="loadingAvailability" class="text-center text-sm text-gray-500 py-6">Loading availability data...</div>
        <div v-else-if="availabilityError" class="text-center text-sm text-red-500 py-6">{{ availabilityError }}</div>
        <div v-else-if="!weeklyAvailability.availabilities || weeklyAvailability.availabilities.length === 0"
             class="text-center text-sm text-gray-500 py-6">
            No availability data found for this week.
        </div>
        <div v-else class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-4 mt-4">
            <!-- Day blocks for Availability -->
            <div v-for="i in 7" :key="'availability-day-' + i"
                 class="flex flex-col items-center justify-start p-4 border rounded-lg shadow-sm h-36 overflow-hidden text-clip relative group"
                 :class="getDayAvailabilityClass(i)">
                <span class="text-sm font-bold text-gray-800">{{ getDayName(i) }}</span>
                <span class="text-xs text-gray-600 mt-0.5">{{ getDayDate(i) }}</span>
                <div class="mt-3 text-center text-xs w-full">
                    <template v-if="getAvailabilityStatus(i).status === 'available'">
                        <p class="text-green-700 font-bold">Available</p>
                        <p v-if="getAvailabilityStatus(i).slots.length > 0" class="text-gray-600 leading-tight mt-1">
                            {{ getAvailabilityStatus(i).slots.join(', ') }}
                        </p>
                    </template>
                    <template v-else-if="getAvailabilityStatus(i).status === 'unavailable'">
                        <p class="text-red-700 font-bold">Unavailable</p>
                        <p v-if="getAvailabilityStatus(i).reason" class="text-gray-600 leading-tight mt-1 break-words">
                            Reason: {{ getAvailabilityStatus(i).reason }}
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-gray-500 font-medium">Not Set</p>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
