<script setup>
import { ref, onMounted } from 'vue';
import Notification from '@/Components/Notification.vue';

// Store for notifications
const notifications = ref([]);

// Generate a unique ID for each notification
const generateId = () => {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
};

// Add a new notification
const addNotification = (message, type = 'info', duration = 5000) => {
    const id = generateId();
    notifications.value.push({ id, message, type, duration });
    return id;
};

// Remove a notification by ID
const removeNotification = (id) => {
    const index = notifications.value.findIndex(notification => notification.id === id);
    if (index !== -1) {
        notifications.value.splice(index, 1);
    }
};

// Expose methods to parent components
defineExpose({
    addNotification,
    removeNotification,
    // Helper methods for common notification types
    success: (message, duration) => addNotification(message, 'success', duration),
    error: (message, duration) => addNotification(message, 'error', duration),
    info: (message, duration) => addNotification(message, 'info', duration),
    warning: (message, duration) => addNotification(message, 'warning', duration)
});
</script>

<template>
    <div class="fixed top-4 right-4 z-50 space-y-4 w-full max-w-sm">
        <transition-group name="notification">
            <Notification
                v-for="notification in notifications"
                :key="notification.id"
                :id="notification.id"
                :type="notification.type"
                :message="notification.message"
                :duration="notification.duration"
                @close="removeNotification"
            />
        </transition-group>
    </div>
</template>

<style scoped>
.notification-enter-active,
.notification-leave-active {
    transition: all 0.3s ease;
}
.notification-enter-from {
    opacity: 0;
    transform: translateX(30px);
}
.notification-leave-to {
    opacity: 0;
    transform: translateY(-30px);
}
</style>
