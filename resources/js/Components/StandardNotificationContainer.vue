<template>
    <div class="fixed top-4 right-4 z-[9999] flex flex-col items-end space-y-3">
        <!-- Standard toast notifications are stacked here -->
        <div
            v-for="notification in notifications"
            :key="notification.id"
            class="w-full max-w-sm rounded-md shadow-lg pointer-events-auto overflow-hidden"
            :class="notificationTypeClass(notification.type)"
        >
            <div class="p-4">
                <p class="text-sm font-medium text-white">
                    {{ notification.message }}
                </p>
                <div v-if="notification.confirm" class="mt-3 flex gap-2">
                    <button
                        class="px-3 py-1 text-sm font-semibold rounded bg-white/90 text-slate-800 hover:bg-white"
                        @click="handleConfirm(notification.id)"
                    >
                        {{ notification.confirm.confirmText || 'Confirm' }}
                    </button>
                    <button
                        class="px-3 py-1 text-sm font-semibold rounded bg-transparent border border-white/70 text-white hover:bg-white/10"
                        @click="handleCancel(notification.id)"
                    >
                        {{ notification.confirm.cancelText || 'Cancel' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

// Local state for toast notifications
const notifications = ref([]);

/**
 * Adds a new standard or confirm notification to the list.
 * Overloads:
 * - addNotification(message, type, duration)
 * - addNotification(configObject)
 * @returns {string} id of the created notification
 */
const addNotification = (messageOrConfig, type = 'info', duration = 5000) => {
    let config;
    if (typeof messageOrConfig === 'object' && messageOrConfig !== null) {
        config = { ...messageOrConfig };
    } else {
        config = { message: messageOrConfig, type, duration };
    }

    const id = config.id || crypto.randomUUID();

    const notification = {
        id,
        message: config.message || '',
        type: config.type || 'info',
        duration: typeof config.duration === 'number' ? config.duration : 5000,
        sticky: !!config.sticky,
        confirm: config.confirm || null, // { confirmText, cancelText, onConfirm, onCancel }
    };

    notifications.value.unshift(notification);

    // Automatically remove the notification after the duration unless sticky or confirm
    if (!notification.sticky && !notification.confirm) {
        setTimeout(() => {
            removeNotification(id);
        }, notification.duration);
    }

    return id;
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

const handleConfirm = (id) => {
    const n = notifications.value.find(n => n.id === id);
    if (n && n.confirm && typeof n.confirm.onConfirm === 'function') {
        try { n.confirm.onConfirm(); } catch (_) {}
    }
    removeNotification(id);
};

const handleCancel = (id) => {
    const n = notifications.value.find(n => n.id === id);
    if (n && n.confirm && typeof n.confirm.onCancel === 'function') {
        try { n.confirm.onCancel(); } catch (_) {}
    }
    removeNotification(id);
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
