<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AvailabilityModal from '@/Components/Availability/AvailabilityModal.vue';

// State
const showPrompt = ref(false);
const loading = ref(true);
const nextWeekDates = ref([]);
const showAvailabilityModal = ref(false);
const error = ref('');
const shouldBlockUser = ref(false);
const allWeekdaysCovered = ref(false);
const currentDay = ref(null);
const isThursdayToSaturday = ref(false);

// Get current user ID from auth
const currentUserId = computed(() => {
    return usePage().props.auth.user?.id || null;
});

// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        console.log('Auth headers set in AvailabilityPrompt');
    }
};

// Check if we should show the prompt (between Thursday and Saturday)
const checkShouldShowPrompt = async () => {
    loading.value = true;
    error.value = '';

    try {
        // Ensure auth headers are set before making the request
        ensureAuthHeaders();

        const response = await axios.get('/api/availability-prompt');

        // Store all the values from the API response
        showPrompt.value = response.data.should_show_prompt;
        shouldBlockUser.value = response.data.should_block_user;
        allWeekdaysCovered.value = response.data.all_weekdays_covered;
        currentDay.value = response.data.current_day;
        isThursdayToSaturday.value = response.data.is_thursday_to_saturday;

        // Generate dates for next week if we should show the prompt
        if (showPrompt.value) {
            generateNextWeekDates(response.data.next_week_start, response.data.next_week_end);
        }

        // Store the blocking status in localStorage so other components can access it
        localStorage.setItem('shouldBlockUser', shouldBlockUser.value);
        localStorage.setItem('allWeekdaysCovered', allWeekdaysCovered.value);

        // Emit a custom event that can be listened to by other components
        const event = new CustomEvent('availability-status-updated', {
            detail: {
                shouldBlockUser: shouldBlockUser.value,
                allWeekdaysCovered: allWeekdaysCovered.value
            }
        });
        window.dispatchEvent(event);
    } catch (error) {
        console.error('Error checking availability prompt:', error);
        showPrompt.value = false;

        if (error.response && error.response.status === 401) {
            error.value = 'Authentication error. Please refresh the page or log in again.';
        }
    } finally {
        loading.value = false;
    }
};

// Generate dates for next week
const generateNextWeekDates = (startDateStr, endDateStr) => {
    const dates = [];
    const startDate = new Date(startDateStr);
    const endDate = new Date(endDateStr);

    // Create an array of dates from start to end
    const currentDate = new Date(startDate);
    while (currentDate <= endDate) {
        dates.push({
            value: currentDate.toISOString().split('T')[0],
            label: currentDate.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })
        });
        currentDate.setDate(currentDate.getDate() + 1);
    }

    nextWeekDates.value = dates;
};

// Open the availability modal
const openAvailabilityModal = () => {
    showAvailabilityModal.value = true;
};

// Handle availability saved
const handleAvailabilitySaved = () => {
    // Recheck if we should still show the prompt
    checkShouldShowPrompt();
};

// Handle visibility change - check prompt status when page becomes visible
const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        // When the page becomes visible, check if we should show the prompt
        nextTick(() => {
            checkShouldShowPrompt();
        });
    }
};

// Handle storage changes from other tabs/windows
const handleStorageChange = (event) => {
    if (event.key === 'shouldBlockUser' || event.key === 'allWeekdaysCovered') {
        // When localStorage changes, check if we should show the prompt
        nextTick(() => {
            checkShouldShowPrompt();
        });
    }
};

// Initialize component
onMounted(() => {
    checkShouldShowPrompt();

    // Add event listeners for visibility and storage changes
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('storage', handleStorageChange);
});

// Clean up event listeners
onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('storage', handleStorageChange);
});
</script>

<template>
    <div v-if="showPrompt && !loading"
         :class="[
            'border-l-4 p-4 mb-6 rounded-md shadow-sm',
            shouldBlockUser ? 'bg-red-50 border-red-500' : 'bg-indigo-50 border-indigo-500'
         ]">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg v-if="shouldBlockUser" class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 flex-1 md:flex md:justify-between">
                <!-- Thursday - Not submitted or incomplete -->
                <p v-if="currentDay === 4 && !allWeekdaysCovered" class="text-sm text-indigo-700">
                    Please submit your availability for next week. This helps in planning meetings and work schedules.
                </p>

                <!-- Thursday - Fully submitted -->
                <p v-else-if="currentDay === 4 && allWeekdaysCovered" class="text-sm text-green-700">
                    Thank you for submitting your availability for next week! You can still update it if needed.
                </p>

                <!-- Friday/Saturday - Not submitted or incomplete -->
                <p v-else-if="(currentDay === 5 || currentDay === 6) && !allWeekdaysCovered" class="text-sm text-red-700 font-medium">
                    <span class="font-bold">Action Required:</span> You must submit your availability for all weekdays of next week.
                    {{ shouldBlockUser ? 'Other features are currently blocked until you complete this task.' : '' }}
                </p>

                <!-- Friday/Saturday - Fully submitted -->
                <p v-else-if="(currentDay === 5 || currentDay === 6) && allWeekdaysCovered" class="text-sm text-green-700">
                    Thank you for submitting your availability for next week! You can still update it if needed.
                </p>

                <p class="mt-3 text-sm md:mt-0 md:ml-6">
                    <PrimaryButton
                        @click="openAvailabilityModal"
                        class="whitespace-nowrap"
                        :class="{ 'bg-red-600 hover:bg-red-700': shouldBlockUser }"
                    >
                        {{ allWeekdaysCovered ? 'Update Availability' : 'Submit Availability' }}
                    </PrimaryButton>
                </p>
            </div>
        </div>

        <!-- Availability Modal -->
        <AvailabilityModal
            :show="showAvailabilityModal"
            :next-week-dates="nextWeekDates"
            :date="nextWeekDates.length > 0 ? nextWeekDates[0].value : ''"
            :userId="currentUserId"
            @close="showAvailabilityModal = false"
            @availability-saved="handleAvailabilitySaved"
        />
    </div>
</template>
