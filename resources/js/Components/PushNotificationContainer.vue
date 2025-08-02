<template>
    <div class="fixed bottom-4 right-4 z-50 flex flex-col items-end space-y-4">
        <!-- Notifications are stacked here -->
        <div v-for="notification in visibleNotifications" :key="notification.id" class="w-full max-w-sm">
            <div class="bg-white rounded-xl shadow-lg p-5 flex items-start space-x-4 border border-gray-200">
                <!-- Content section -->
                <div class="flex-1 min-w-0">
                    <!-- Title, Project Name, and close button -->
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="flex-1 min-w-0">
                            <h3 v-if="notification.title" class="text-lg font-semibold text-gray-900 truncate">
                                {{ notification.title }}
                            </h3>
                            <p v-if="notification.project_name" class="text-sm text-gray-500">
                                {{ notification.project_name }}
                            </p>
                        </div>
                        <button
                            @click="removeNotification(notification.id)"
                            class="flex-shrink-0 text-gray-400 hover:text-gray-600 p-1 rounded-full transition-colors duration-200"
                            aria-label="Close notification"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Description -->
                    <p v-if="notification.message" class="text-sm text-gray-600 mb-2">
                        {{ notification.message }}
                    </p>

                    <!-- Due date and action button -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-3 space-y-2 sm:space-y-0">
                        <div v-if="notification.due_date" class="text-xs text-gray-500 font-medium">
                            Due: {{ formatDate(notification.due_date) }}
                        </div>
                        <button
                            @click="handleButtonClick(notification)"
                            class="px-3 py-1.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 shadow-md"
                        >
                            View Task
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- "View all notifications" button -->
        <button
            v-if="notifications.length > maxVisible"
            @click="viewAllNotifications"
            class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors duration-200 shadow-md mt-4"
        >
            View all notifications ({{ notifications.length - maxVisible }} more)
        </button>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, getCurrentInstance } from 'vue';
import { BellRing, X } from 'lucide-vue-next';
import { setPushNotificationContainer } from '@/Utils/notification';
import { openTaskDetailSidebar } from '@/Utils/sidebar';

// The array to hold all incoming notifications
const notifications = ref([]);

// The maximum number of notifications to display at once
const maxVisible = 3;

// A computed property to get only the visible notifications (the last 3)
const visibleNotifications = computed(() => {
    return notifications.value.slice(-maxVisible);
});

// A unique ID counter for each notification
let notificationIdCounter = 0;

/**
 * Extracts the taskId from a given URL string.
 * @param {string} url - The URL string.
 * @returns {number|null} The task ID or null if not found.
 */
const getTaskIdFromUrl = (url) => {
    const match = url.match(/\/task\/(\d+)/);
    return match ? parseInt(match[1]) : null;
};

/**
 * Extracts the projectId from a given URL string.
 * @param {string} url - The URL string.
 * @returns {number|null} The project ID or null if not found.
 */
const getProjectIdFromUrl = (url) => {
    const match = url.match(/\/project\/(\d+)\//);
    return match ? parseInt(match[1]) : null;
};

/**
 * Adds a new notification to the list.
 * @param {object} payload - The notification payload from Reverb.
 */
const addNotification = (payload) => {
    notifications.value.unshift({
        id: notificationIdCounter++,
        ...payload
    });
};

/**
 * Removes a specific notification from the list.
 * @param {number} id - The ID of the notification to remove.
 */
const removeNotification = (id) => {
    notifications.value = notifications.value.filter(n => n.id !== id);
};

/**
 * Handles the click event for the notification's action button.
 * It now opens the global task detail sidebar.
 * @param {object} notification - The notification object that was clicked.
 */
const handleButtonClick = (notification) => {
    const taskId = notification.task_id || getTaskIdFromUrl(notification.url);
    const projectId = notification.project_id || getProjectIdFromUrl(notification.url);

    if (taskId && projectId) {
        openTaskDetailSidebar(taskId, projectId);
    } else {
        // Fallback to opening a new tab if data is missing.
        console.warn('Could not open task sidebar. Falling back to opening URL in new tab.');
        if (notification.url) {
            window.open(notification.url, '_blank');
        }
    }
    removeNotification(notification.id);
};

/**
 * Formats a date string to a user-friendly format.
 * @param {string} dateString - The date string to format (e.g., 'YYYY-MM-DD').
 * @returns {string} The formatted date string.
 */
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const tomorrow = new Date(now);
    tomorrow.setDate(tomorrow.getDate() + 1);

    if (date.toDateString() === now.toDateString()) {
        return 'Today';
    } else if (date.toDateString() === tomorrow.toDateString()) {
        return 'Tomorrow';
    } else {
        return date.toLocaleDateString('en-GB'); // dd/mm/yyyy format
    }
};

/**
 * Handles the "View all notifications" button click.
 */
const viewAllNotifications = () => {
    console.log('View all notifications button clicked.');
};

// Expose the addNotification method so it can be called from outside
defineExpose({
    addNotification
});

// Use onMounted to ensure the component is fully ready before setting the global reference.
onMounted(() => {
    const instance = getCurrentInstance();
    if (instance) {
        setPushNotificationContainer(instance.exposed);
    }
});
</script>

<style scoped>
/* Scoped styles... */
</style>
