<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AvailabilityModal from '@/Components/Availability/AvailabilityModal.vue';
import axios from 'axios';

// State
const shouldBlock = ref(false);
const loading = ref(true);
const showAvailabilityModal = ref(false);
const nextWeekDates = ref([]);

// Get current user ID from auth
const currentUserId = computed(() => {
    return usePage().props.auth.user?.id || null;
});

// Ensure authentication headers are set
const ensureAuthHeaders = () => {
    const token = localStorage.getItem('authToken');
    if (token && !axios.defaults.headers.common['Authorization']) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
};

// Check if we should block the user
const checkShouldBlock = async () => {
    loading.value = true;

    // First check localStorage which is updated by AvailabilityPrompt
    const storedShouldBlock = localStorage.getItem('shouldBlockUser');
    const storedAllWeekdaysCovered = localStorage.getItem('allWeekdaysCovered');

    if (storedShouldBlock === 'true' && storedAllWeekdaysCovered === 'false') {
        shouldBlock.value = true;
        await fetchNextWeekDates();
    } else {
        // If not found in localStorage, fetch from API
        try {
            ensureAuthHeaders();
            const response = await axios.get('/api/availability-prompt');
            shouldBlock.value = response.data.should_block_user && !response.data.all_weekdays_covered;

            if (shouldBlock.value) {
                await fetchNextWeekDates();
            }
        } catch (error) {
            console.error('Error checking if user should be blocked:', error);
            shouldBlock.value = false;
        }
    }

    loading.value = false;
};

// Fetch next week dates for the availability modal
const fetchNextWeekDates = async () => {
    try {
        ensureAuthHeaders();
        const response = await axios.get('/api/availability-prompt');
        generateNextWeekDates(response.data.next_week_start, response.data.next_week_end);
    } catch (error) {
        console.error('Error fetching next week dates:', error);
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
    // Recheck if we should still block the user
    checkShouldBlock();
};

// Listen for the availability-status-updated event
const handleAvailabilityStatusUpdated = (event) => {
    shouldBlock.value = event.detail.shouldBlockUser && !event.detail.allWeekdaysCovered;
};

// Handle visibility change - check blocking status when page becomes visible
const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        // When the page becomes visible, check if we should block
        nextTick(() => {
            checkShouldBlock();
        });
    }
};

// Handle storage changes from other tabs/windows
const handleStorageChange = (event) => {
    if (event.key === 'shouldBlockUser' || event.key === 'allWeekdaysCovered') {
        // When localStorage changes, check if we should block
        nextTick(() => {
            checkShouldBlock();
        });
    }
};

// Initialize component
onMounted(() => {
    checkShouldBlock();
    window.addEventListener('availability-status-updated', handleAvailabilityStatusUpdated);

    // Add event listeners for visibility and storage changes
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('storage', handleStorageChange);
});

// Clean up event listeners
onUnmounted(() => {
    window.removeEventListener('availability-status-updated', handleAvailabilityStatusUpdated);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('storage', handleStorageChange);
});
</script>

<template>
    <div v-if="shouldBlock && !loading" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full mx-4">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 mr-3">
                    <svg class="h-8 w-8 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Action Required</h2>
            </div>

            <p class="mb-6 text-gray-700">
                You must submit your availability for all weekdays of next week before you can continue using the application.
                This is required for planning meetings and work schedules.
            </p>

            <div class="flex justify-center">
                <PrimaryButton
                    @click="openAvailabilityModal"
                    class="bg-red-600 hover:bg-red-700"
                >
                    Submit Availability Now
                </PrimaryButton>
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
    </div>
</template>
