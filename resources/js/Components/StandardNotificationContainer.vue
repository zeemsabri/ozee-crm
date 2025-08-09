<template>
    <div class="fixed top-4 right-4 z-[100] flex flex-col items-end space-y-3">
        <!-- Standard toast notifications are stacked here -->
        <div
            v-for="notification in notifications"
            :key="notification.id"
            class="w-full max-w-sm rounded-md shadow-lg pointer-events-auto"
            :class="notificationTypeClass(notification.type)"
        >
            <div class="p-4">
                <p class="text-sm font-medium text-white">
                    {{ notification.message }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

// Local state for toast notifications
const notifications = ref([]);

/**
 * Adds a new standard notification to the list.
 * @param {string} message - The notification message.
 * @param {string} type - The type of notification (success, error, info, warning).
 * @param {number} duration - How long to display the notification in ms.
 */
const addNotification = (message, type = 'info', duration = 5000) => {
    const id = crypto.randomUUID();
    notifications.value.unshift({ id, message, type });

    // Automatically remove the notification after the duration
    setTimeout(() => {
        removeNotification(id);
    }, duration);
};

/**
 * Removes a notification from the list by its ID.
 * @param {string} id - The ID of the notification to remove.
 */
const removeNotification = (id) => {
    const index = notifications.value.findIndex(n => n.id === id);
    if (index > -1) {
        notifications.value.splice(index, 1);
    }
};

/**
 * Returns the appropriate background color class based on the notification type.
 * @param {string} type - The notification type.
 * @returns {string} Tailwind CSS class for background color.
 */
const notificationTypeClass = (type) => {
    switch (type) {
        case 'success':
            return 'bg-green-500';
        case 'error':
            return 'bg-red-500';
        case 'warning':
            return 'bg-yellow-500';
        case 'info':
        default:
            return 'bg-blue-500';
    }
};

// Expose the addNotification method so it can be called from the global utility
defineExpose({
    addNotification
});
</script>
