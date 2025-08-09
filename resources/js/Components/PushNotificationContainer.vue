<template>
    <div class="fixed bottom-4 right-4 z-50 flex flex-col items-end space-y-2" v-if="!sidebarIsOpen">
        <!-- Container for the notifications -->
        <div
            class="notification-list w-full max-w-xs space-y-2"
            v-if="!isMinimized && visibleNotifications.length > 0"
        >
            <!-- Notifications are stacked here -->
            <div
                v-for="notification in visibleNotifications"
                :key="notification.id"
                class="bg-white rounded-lg shadow-lg p-3 flex items-start space-x-3 border border-gray-200 transition-opacity duration-300 opacity-60 hover:opacity-100"
            >
                <!-- Content section -->
                <div class="flex-1 min-w-0">
                    <!-- Title, Project Name -->
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <div class="flex-1 min-w-0">
                            <h3 v-if="notification.title" class="text-base font-semibold text-gray-800 truncate">
                                {{ notification.title }}
                            </h3>
                            <p v-if="notification.project_name" class="text-xs text-gray-500">
                                {{ notification.project_name }}
                            </p>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="notification.message" class="text-xs text-gray-600 mb-2">
                        {{ notification.message }}
                    </p>

                    <!-- Due date and action button -->
                    <div class="flex items-center justify-between mt-2">
                        <div v-if="notification.due_date" class="text-xs text-gray-500 font-medium">
                            Due: {{ formatDate(notification.due_date) }}
                        </div>
                        <button
                            @click="handleButtonClick(notification)"
                            class="px-2.5 py-1 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700 transition-colors duration-200 shadow"
                        >
                            {{ notification.button_label || "View" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Minimize / Show Button -->
        <div class="flex justify-end w-full max-w-xs" v-if="visibleNotifications.length > 0">
            <button @click="toggleMinimize" class="bg-white/80 backdrop-blur-sm text-gray-600 hover:text-gray-900 text-xs font-bold py-1 px-3 rounded-full shadow-md transition-all duration-300">
                <span v-if="isMinimized">Show ({{ formattedCountdown }})</span>
                <span v-else>Minimize</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { openTaskDetailSidebar } from '@/Utils/sidebar';
import {
    notificationSidebarState,
    markNotificationAndRefetch,
    markToastAsSeen
} from '@/Utils/notification-sidebar';
import { formatDate } from '@/Utils/notification';

const props = defineProps({
    sidebarIsOpen: {
        type: Boolean,
        default: false
    }
});

// --- STATE (Minimize functionality only) ---
const isMinimized = ref(false);
const minimizeTimer = ref(null);
const minimizeCountdown = ref(0);
const countdownInterval = ref(null);
const STORAGE_KEY = 'notification_minimized_state';

// --- COMPUTED ---
const maxVisible = 3;

// This component now reads directly from the global state.
// It only shows notifications that are flagged as a new push.
const visibleNotifications = computed(() => {
    return notificationSidebarState.value.notifications
        .filter(n => n.isNewPush)
        .slice(0, maxVisible);
});

const formattedCountdown = computed(() => {
    const minutes = Math.floor(minimizeCountdown.value / 60);
    const seconds = minimizeCountdown.value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// --- METHODS ---

const getTaskIdFromUrl = (url) => {
    if (!url) return null;
    const match = url.match(/\/task\/(\d+)/);
    return match ? parseInt(match[1]) : null;
};

const getProjectIdFromUrl = (url) => {
    if (!url) return null;
    const match = url.match(/\/project\/(\d+)\//);
    return match ? parseInt(match[1]) : null;
};

const toggleMinimize = () => {
    isMinimized.value = !isMinimized.value;

    clearTimeout(minimizeTimer.value);
    clearInterval(countdownInterval.value);

    if (isMinimized.value) {
        // Calculate expiry time (5 minutes from now)
        const expiryTime = Date.now() + 300000;

        // Store minimized state and expiry time in localStorage
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            isMinimized: true,
            expiryTime: expiryTime
        }));

        minimizeTimer.value = setTimeout(() => {
            isMinimized.value = false;
            clearInterval(countdownInterval.value);
            // Clear localStorage when timer expires
            localStorage.removeItem(STORAGE_KEY);
        }, 300000); // 5 minutes

        minimizeCountdown.value = 300;
        countdownInterval.value = setInterval(() => {
            if (minimizeCountdown.value > 0) {
                minimizeCountdown.value--;
            } else {
                clearInterval(countdownInterval.value);
            }
        }, 1000);
    } else {
        // Clear localStorage when notifications are shown again
        localStorage.removeItem(STORAGE_KEY);
    }
};

const handleButtonClick = async (notification) => {
    // Mark the toast as "seen" to remove it from the push container view.
    markToastAsSeen(notification.id);

    // Mark as read in the database
    await markNotificationAndRefetch(notification.view_id);

    const taskId = notification.task_id || getTaskIdFromUrl(notification.url);
    const projectId = notification.project_id || getProjectIdFromUrl(notification.url);

    if (taskId && projectId) {
        openTaskDetailSidebar(taskId, projectId);
    } else {
        console.warn('Could not open task sidebar. Falling back to redirecting.');
        if (notification.url) {
            window.location.href = notification.url;
        }
    }
};

// --- LIFECYCLE ---
onMounted(() => {
    // Check if we have a stored minimized state
    const storedState = localStorage.getItem(STORAGE_KEY);

    if (storedState) {
        try {
            const { isMinimized: storedMinimized, expiryTime } = JSON.parse(storedState);

            // Calculate remaining time
            const now = Date.now();
            const remainingTime = expiryTime - now;

            // Only restore if the timer hasn't expired
            if (remainingTime > 0) {
                isMinimized.value = storedMinimized;

                // Convert remaining milliseconds to seconds for the countdown
                minimizeCountdown.value = Math.floor(remainingTime / 1000);

                // Set up the timer to show notifications again when the time expires
                minimizeTimer.value = setTimeout(() => {
                    isMinimized.value = false;
                    clearInterval(countdownInterval.value);
                    localStorage.removeItem(STORAGE_KEY);
                }, remainingTime);

                // Set up the countdown interval
                countdownInterval.value = setInterval(() => {
                    if (minimizeCountdown.value > 0) {
                        minimizeCountdown.value--;
                    } else {
                        clearInterval(countdownInterval.value);
                    }
                }, 1000);
            } else {
                // Timer has expired, remove from localStorage
                localStorage.removeItem(STORAGE_KEY);
            }
        } catch (error) {
            console.error('Error restoring notification minimized state:', error);
            localStorage.removeItem(STORAGE_KEY);
        }
    }
});

onUnmounted(() => {
    clearTimeout(minimizeTimer.value);
    clearInterval(countdownInterval.value);
});
</script>
