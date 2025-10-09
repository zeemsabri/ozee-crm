<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AvailabilityModal from '@/Components/Availability/AvailabilityModal.vue';

// State variables for the component
const showPrompt = ref(false); // Controls overall visibility of the prompt banner
const loading = ref(true); // Indicates if API data is being fetched
const nextWeekDates = ref([]); // Stores dates for next week to pass to the modal
const showAvailabilityModal = ref(false); // Controls visibility of the availability submission modal
const error = ref(''); // Stores any error messages from API calls
const shouldBlockUser = ref(false); // Flag from API: if true, user is blocked from certain features
const allNextWeekdaysCovered = ref(false); // Flag from API: true if all next week's weekdays have availability submitted
const currentDay = ref(null); // Current day of the week (1=Monday, 7=Sunday)
const isThursdayToSaturday = ref(false); // Flag from API: true if current day is Thursday, Friday, or Saturday

// Get current user ID from Inertia's usePage().props.auth.user
const currentUserId = computed(() => {
    return usePage().props.auth.user?.id || null;
});

// Ensures that Axios includes the Authorization header if an authToken exists in localStorage.
// This is crucial for making authenticated API requests.
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        console.log('Auth headers set in AvailabilityPrompt'); // For debugging purposes
    }
};

// Main function to fetch and update the availability prompt status from the API.
const checkShouldShowPrompt = async () => {
    loading.value = true; // Set loading state to true
    error.value = ''; // Clear previous errors

    try {
        ensureAuthHeaders(); // Ensure authentication headers are set before the request

        const response = await axios.get('/api/availability-prompt'); // Fetch data from the API

        // Update reactive state with data from the API response
        shouldBlockUser.value = response.data.should_block_user;
        // Backward compatibility: prefer specific key if present else fallback
        allNextWeekdaysCovered.value = (response.data.all_next_weekdays_covered ?? response.data.all_weekdays_covered) || false;
        const allCurrentWeekdaysCovered = response.data.all_current_weekdays_covered ?? true;
        currentDay.value = response.data.current_day;
        isThursdayToSaturday.value = response.data.is_thursday_to_saturday;
        // Show prompt if backend says so OR it's Thu-Sat (so users can still update)
        showPrompt.value = response.data.should_show_prompt || isThursdayToSaturday.value;

        // Generate dates for next week if the prompt is active
        if (showPrompt.value) {
            generateNextWeekDates(response.data.next_week_start, response.data.next_week_end);
        }

        // Store blocking status in localStorage for other components to read.
        // This is a common pattern for sharing global state in simple setups.
        localStorage.setItem('shouldBlockUser', String(shouldBlockUser.value));
        localStorage.setItem('allNextWeekdaysCovered', String(allNextWeekdaysCovered.value));
        localStorage.setItem('allCurrentWeekdaysCovered', String(!!allCurrentWeekdaysCovered));
        localStorage.setItem('allWeekdaysCovered', String(allNextWeekdaysCovered.value)); // Compatibility with older listeners

        // Dispatch a custom event to notify other components about the availability status change.
        // This is a reactive way for loosely coupled components to communicate.
        const event = new CustomEvent('availability-status-updated', {
            detail: {
                shouldBlockUser: shouldBlockUser.value,
                allNextWeekdaysCovered: allNextWeekdaysCovered.value,
                allCurrentWeekdaysCovered: !!allCurrentWeekdaysCovered
            }
        });
        window.dispatchEvent(event);

    } catch (err) {
        console.error('Error checking availability prompt:', err);
        showPrompt.value = false; // Hide prompt on error

        if (err.response && err.response.status === 401) {
            error.value = 'Authentication error. Please refresh the page or log in again.';
        } else {
            error.value = 'Failed to load availability prompt status.';
        }
    } finally {
        loading.value = false; // Set loading state to false
    }
};

// Generates an array of date objects for the next week, used by AvailabilityModal.
const generateNextWeekDates = (startDateStr, endDateStr) => {
    const dates = [];
    const startDate = new Date(startDateStr);
    const endDate = new Date(endDateStr);

    let currentDate = new Date(startDate); // Start from the beginning of next week
    while (currentDate <= endDate) {
        dates.push({
            value: currentDate.toISOString().split('T')[0], // YYYY-MM-DD format
            label: currentDate.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' }) // e.g., "Monday, Jul 29"
        });
        currentDate.setDate(currentDate.getDate() + 1); // Move to the next day
    }
    nextWeekDates.value = dates;
};

// Opens the AvailabilityModal to allow the user to submit/update availability.
const openAvailabilityModal = () => {
    showAvailabilityModal.value = true;
};

// Callback function when availability is successfully saved in the modal.
// Re-checks the prompt status to update the UI immediately.
const handleAvailabilitySaved = () => {
    checkShouldShowPrompt();
};

// Event handler for document visibility changes.
// Re-checks prompt status when the tab/window becomes active again.
const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        nextTick(() => { // Use nextTick to ensure DOM updates are flushed before re-check
            checkShouldShowPrompt();
        });
    }
};

// Event handler for localStorage changes (e.g., from another browser tab).
// Re-checks prompt status if relevant localStorage items are updated.
const handleStorageChange = (event) => {
    if (event.key === 'shouldBlockUser' || event.key === 'allNextWeekdaysCovered') {
        nextTick(() => {
            checkShouldShowPrompt();
        });
    }
};

// Lifecycle hook: Component mounted
onMounted(() => {
    checkShouldShowPrompt(); // Initial check when the component loads

    // Add event listeners for dynamic updates
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('storage', handleStorageChange);
});

// Lifecycle hook: Component unmounted (cleanup)
onUnmounted(() => {
    // Remove event listeners to prevent memory leaks
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('storage', handleStorageChange);
});
</script>

<template>
    <div v-if="showPrompt && !loading && !error"
         :class="[
            'border-l-4 p-4 mb-6 rounded-md shadow-sm transition-all duration-300',
            shouldBlockUser ? 'bg-red-50 border-red-500' : 'bg-indigo-50 border-indigo-500'
         ]">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex-shrink-0 flex items-center">
                <svg v-if="shouldBlockUser" class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <svg v-else class="h-6 w-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
<p class="ml-3 text-sm font-medium"
                   :class="shouldBlockUser ? 'text-red-800' : 'text-indigo-800'">
                    <!-- Smarter, contextual messaging -->
                    <template v-if="shouldBlockUser">
                        <span class="font-bold">Action Required:</span>
                        <span>
                            <!-- We can't directly access current-week flag here, so provide clear general guidance -->
                            You may need to submit availability for the current week (up to today) and for all weekdays of next week.
                        </span>
                    </template>
                    <template v-else-if="!allNextWeekdaysCovered">
                        Please submit your availability for next week. If you missed last week's deadline, you may also need to submit for the current week.
                    </template>
                    <template v-else>
                        Thank you for submitting your availability for next week! You can still update it if needed.
                    </template>
                </p>
            </div>
            <div class="flex-shrink-0 mt-3 sm:mt-0">
                <PrimaryButton
                    @click="openAvailabilityModal"
                    class="whitespace-nowrap px-6 py-2 text-sm font-semibold rounded-md shadow-md"
                    :class="{
                        'bg-red-600 hover:bg-red-700 focus:ring-red-500': shouldBlockUser,
                        'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500': !shouldBlockUser && !allNextWeekdaysCovered,
                        'bg-green-600 hover:bg-green-700 focus:ring-green-500': !shouldBlockUser && allNextWeekdaysCovered
                    }"
                >
                    {{ allNextWeekdaysCovered ? 'Update Availability' : 'Submit Availability' }}
                </PrimaryButton>
            </div>
        </div>
    </div>
    <div v-else-if="loading" class="text-center text-sm text-gray-500 py-4">Loading availability prompt...</div>
    <div v-else-if="error" class="text-center text-sm text-red-500 py-4">{{ error }}</div>

    <!-- Availability Modal -->
    <AvailabilityModal
        :show="showAvailabilityModal"
        :next-week-dates="nextWeekDates"
        :date="nextWeekDates.length > 0 ? nextWeekDates[0].value : ''"
        :userId="currentUserId"
        @close="showAvailabilityModal = false"
        @availability-saved="handleAvailabilitySaved"
    />
</template>
