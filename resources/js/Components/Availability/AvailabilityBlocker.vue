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

// Additional state for smarter messaging
const allCurrentWeekdaysCovered = ref(true);
const allNextWeekdaysCovered = ref(true);
const isFridayOnwards = ref(false);

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

// Check if we should block the user (always rely on API and then sync localStorage)
const checkShouldBlock = async () => {
    loading.value = true;
    try {
        ensureAuthHeaders();
        const { data } = await axios.get('/api/availability-prompt');

        // Update state from API
        shouldBlock.value = !!data.should_block_user;
        allCurrentWeekdaysCovered.value = !!data.all_current_weekdays_covered;
        allNextWeekdaysCovered.value = !!(data.all_next_weekdays_covered ?? data.all_weekdays_covered);
        isFridayOnwards.value = !!data.is_friday_onwards;

        // Keep localStorage in sync for other parts of the app
        localStorage.setItem('shouldBlockUser', String(shouldBlock.value));
        localStorage.setItem('allCurrentWeekdaysCovered', String(allCurrentWeekdaysCovered.value));
        localStorage.setItem('allNextWeekdaysCovered', String(allNextWeekdaysCovered.value));
        // Back-compat key
        localStorage.setItem('allWeekdaysCovered', String(allNextWeekdaysCovered.value));

        // Emit global event so listeners can react immediately
        const event = new CustomEvent('availability-status-updated', {
            detail: {
                shouldBlockUser: shouldBlock.value,
                allCurrentWeekdaysCovered: allCurrentWeekdaysCovered.value,
                allNextWeekdaysCovered: allNextWeekdaysCovered.value
            }
        });
        window.dispatchEvent(event);
    } catch (error) {
        console.error('Error checking if user should be blocked:', error);
        shouldBlock.value = false;
    } finally {
        loading.value = false;
    }
};

// Open the availability modal
const openAvailabilityModal = () => {
    showAvailabilityModal.value = true;
};

// Handle availability saved
const handleAvailabilitySaved = () => {
    // Recheck if we should still block the user and update global state
    checkShouldBlock();
};

// Listen for the availability-status-updated event
const handleAvailabilityStatusUpdated = (event) => {
    shouldBlock.value = !!event.detail.shouldBlockUser;
    if ('allCurrentWeekdaysCovered' in event.detail) {
        allCurrentWeekdaysCovered.value = !!event.detail.allCurrentWeekdaysCovered;
    }
    if ('allNextWeekdaysCovered' in event.detail) {
        allNextWeekdaysCovered.value = !!event.detail.allNextWeekdaysCovered;
    }
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
    if (['shouldBlockUser', 'allWeekdaysCovered', 'allCurrentWeekdaysCovered', 'allNextWeekdaysCovered'].includes(event.key)) {
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

            <p class="mb-4 text-gray-700">
                <template v-if="!allCurrentWeekdaysCovered && !allNextWeekdaysCovered">
                    You need to submit your availability for the current week (up to today) and for all weekdays of next week.
                </template>
                <template v-else-if="!allCurrentWeekdaysCovered">
                    You need to submit your availability for the current week (up to today).
                </template>
                <template v-else>
                    You must submit your availability for all weekdays of next week before you can continue using the application.
                </template>
            </p>
            <p class="mb-6 text-gray-600">
                This information is required for planning meetings and work schedules.
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
                :userId="currentUserId"
                @close="showAvailabilityModal = false"
                @availability-saved="handleAvailabilitySaved"
            />
        </div>
    </div>
</template>
