<template>
    <div class="fixed bottom-4 right-4 z-50 flex flex-col items-end space-y-4" v-if="!sidebarIsOpen">
        <!-- Notifications are stacked here -->
        <div v-for="notification in visibleNotifications" :key="notification.id" class="w-full max-w-sm">
            <div class="bg-white rounded-xl shadow-lg p-5 flex items-start space-x-4 border border-gray-200">
                <!-- Content section -->
                <div class="flex-1 min-w-0">
                    <!-- Title, Project Name -->
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="flex-1 min-w-0">
                            <h3 v-if="notification.title" class="text-lg font-semibold text-gray-900 truncate">
                                {{ notification.title }}
                            </h3>
                            <p v-if="notification.project_name" class="text-sm text-gray-500">
                                {{ notification.project_name }}
                            </p>
                        </div>
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
    </div>
</template>

<script setup>
import { ref, computed, onMounted, getCurrentInstance } from 'vue';
import { setPushNotificationContainer } from '@/Utils/notification';
import { openTaskDetailSidebar } from '@/Utils/sidebar';
import { markNotificationAndRefetch, notificationSidebarState } from '@/Utils/notification-sidebar';
import { formatDate } from '@/Utils/notification';
import { watch } from 'vue';

const props = defineProps({
    sidebarIsOpen: {
        type: Boolean,
        default: false
    }
});

// The local array to hold notifications for the push container
const localNotifications = ref([]);

// The maximum number of notifications to display at once
const maxVisible = 3;

// A computed property to get only the visible notifications for the toast container
const visibleNotifications = computed(() => {
    // Only show unread notifications as toasts
    return localNotifications.value.filter(n => !n.isRead).slice(0, maxVisible);
});

// Watch the global state for changes to hide notifications in the local array
watch(() => notificationSidebarState.value.notifications, (newNotifications) => {
    // When the global state changes (e.g., a notification is read),
    // update our local notifications.
    const newUnread = newNotifications.filter(n => !n.isRead);
    localNotifications.value = [...newUnread];
});

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
 * This function is now responsible for adding the notification to the local state.
 * @param {object} payload - The notification payload from the push event.
 */
const addNotification = (payload) => {
    localNotifications.value.push(payload);
};

/**
 * Handles the click event for the notification's action button.
 */
const handleButtonClick = async (notification) => {
    // Mark the notification as read on the backend and re-fetch
    await markNotificationAndRefetch(notification.view_id);

    const taskId = notification.task_id || getTaskIdFromUrl(notification.url);
    const projectId = notification.project_id || getProjectIdFromUrl(notification.url);

    if (taskId && projectId) {
        openTaskDetailSidebar(taskId, projectId);
    } else {
        console.warn('Could not open task sidebar. Falling back to opening URL in new tab.');
        if (notification.url) {
            window.open(notification.url, '_blank');
        }
    }
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
