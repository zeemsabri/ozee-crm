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
                class="bg-white rounded-lg shadow-lg p-3 flex items-start space-x-3 border border-gray-200 transition-opacity duration-300 opacity-90 hover:opacity-100"
            >
                <!-- Content section -->
                <div class="flex-1 min-w-0">
                    <!-- Title, Project Name -->
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <div class="flex-1 min-w-0">
                            <h3 v-if="notification.title" class="text-xs font-semibold text-gray-800 truncate">
                                {{ notification.title }}
                            </h3>
                            <p v-if="notification.project_name" class="text-[10px] text-gray-500">
                                {{ notification.project_name }}
                            </p>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="notification.message" class="text-[11px] text-gray-600 mb-2 leading-tight">
                        {{ notification.message }}
                    </p>

                    <!-- Due date and action button -->
                    <div class="flex items-center justify-between mt-2">
                        <div v-if="notification.due_date" class="text-[10px] text-gray-500 font-medium">
                            Due: {{ formatDate(notification.due_date) }}
                        </div>
                        <button
                            @click="handleButtonClick(notification)"
                            class="px-2 py-0.5 bg-blue-600 text-white text-[10px] font-medium rounded-md hover:bg-blue-700 transition-colors duration-200 shadow"
                        >
                            {{ notification.button_label || "View" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Minimize / Show Button -->
        <div class="flex justify-end w-full max-w-xs" v-if="visibleNotifications.length > 0">
            <button @click="toggleMinimize" class="bg-white/80 backdrop-blur-sm text-gray-600 hover:text-gray-900 text-[10px] font-bold py-1 px-3 rounded-full shadow-md transition-all duration-300">
                <span v-if="isMinimized">Show ({{ visibleNotifications.length }})</span>
                <span v-else>Minimize</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
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

// --- STATE ---
const isMinimized = ref(false);
const isWindowFocused = ref(true);
const notificationTimers = ref(new Map()); // id -> seconds remaining
const AUTO_DISMISS_SECONDS = 120; // 2 minutes

// --- COMPUTED ---
const maxVisible = 3;

const visibleNotifications = computed(() => {
    return notificationSidebarState.value.notifications
        .filter(n => n.isNewPush)
        .slice(0, maxVisible);
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
};

const handleButtonClick = async (notification) => {
    markToastAsSeen(notification.id);
    await markNotificationAndRefetch(notification.view_id);

    const taskId = notification.task_id || getTaskIdFromUrl(notification.url);
    const projectId = notification.project_id || getProjectIdFromUrl(notification.url);

    if (taskId && projectId) {
        openTaskDetailSidebar(taskId, projectId);
    } else if (notification.url) {
        window.location.href = notification.url;
    }
};

// --- AUTO DISMISS LOGIC ---

const updateFocus = () => {
    isWindowFocused.value = document.hasFocus();
};

let tickInterval = null;

const startTicker = () => {
    tickInterval = setInterval(() => {
        if (!isWindowFocused.value) return;

        for (const [id, remaining] of notificationTimers.value.entries()) {
            if (remaining <= 1) {
                markToastAsSeen(id);
                notificationTimers.value.delete(id);
            } else {
                notificationTimers.value.set(id, remaining - 1);
            }
        }
    }, 1000);
};

// Watch for new notifications to initialize their timers
watch(visibleNotifications, (newNotifications) => {
    // If we have new notifications, automatically un-minimize to show them
    if (newNotifications.length > 0 && isMinimized.value) {
        // Only un-minimize if there's a *new* addition
        const currentIdsInTimers = Array.from(notificationTimers.value.keys());
        const hasNewAddition = newNotifications.some(n => !currentIdsInTimers.includes(n.id));
        if (hasNewAddition) {
            isMinimized.value = false;
        }
    }

    // Initialize timers for new notifications
    newNotifications.forEach(n => {
        if (!notificationTimers.value.has(n.id)) {
            notificationTimers.value.set(n.id, AUTO_DISMISS_SECONDS);
        }
    });

    // Clean up timers for notifications that are no longer visible
    const visibleIds = new Set(newNotifications.map(n => n.id));
    for (const [id] of notificationTimers.value) {
        if (!visibleIds.has(id)) {
            notificationTimers.value.delete(id);
        }
    }
}, { deep: true, immediate: true });

// --- LIFECYCLE ---
onMounted(() => {
    window.addEventListener('focus', updateFocus);
    window.addEventListener('blur', updateFocus);
    updateFocus();
    startTicker();
});

onUnmounted(() => {
    window.removeEventListener('focus', updateFocus);
    window.removeEventListener('blur', updateFocus);
    if (tickInterval) clearInterval(tickInterval);
});
</script>

