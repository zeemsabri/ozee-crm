<script setup>
import {computed, onMounted, ref} from 'vue';
import {
    notificationSidebarState,
    closeNotificationsSidebar,
    markNotificationAndRefetch,
} from '@/Utils/notification-sidebar';
import RightSidebar from '@/Components/RightSidebar.vue';
import { openTaskDetailSidebar } from '@/Utils/sidebar';
import { formatDate } from '@/Utils/notification';
import { router } from '@inertiajs/vue3';

// A computed property to get the notifications from the global state
const notifications = computed(() => notificationSidebarState.value.notifications);

// State for the active tab, defaulting to 'New'
const activeTab = ref('New');

// Computed property to filter notifications based on the active tab
const filteredNotifications = computed(() => {
    if (activeTab.value === 'New') {
        return notifications.value.filter(n => !n.isRead);
    }
    return notifications.value;
});

// Handles the click on a notification's "View Task" button.
const handleButtonClick = async (notification) => {
    // Mark the notification as read on the backend and re-fetch
    await markNotificationAndRefetch(notification.view_id);

    const taskId = notification.task_id || getTaskIdFromUrl(notification.url);
    const projectId = notification.project_id || getProjectIdFromUrl(notification.url);

    if (taskId && projectId) {
        openTaskDetailSidebar(taskId, projectId);
        closeNotificationsSidebar();
    } else {
        console.warn('Could not open task sidebar. Falling back to opening URL in new tab.');
        if (notification.url) {
            window.open(notification.url, '_blank');
        }
    }
};

// Utility to extract task ID from URL
const getTaskIdFromUrl = (url) => {
    const match = url.match(/\/task\/(\d+)/);
    return match ? parseInt(match[1]) : null;
};

// Utility to extract project ID from URL
const getProjectIdFromUrl = (url) => {
    const match = url.match(/\/project\/(\d+)\//);
    return match ? parseInt(match[1]) : null;
};

</script>

<template>
    <RightSidebar
        :show="notificationSidebarState.show"
        @update:show="closeNotificationsSidebar"
        title="Notifications"
        :initial-width="35"
        class="z-[100]"
    >
        <template #content>
            <div class="flex border-b border-gray-200">
                <!-- Tab for New Notifications -->
                <button
                    @click="activeTab = 'New'"
                    :class="{ 'border-blue-600 text-blue-600 font-semibold': activeTab === 'New', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'New' }"
                    class="flex-1 py-4 px-1 text-center text-sm border-b-2 transition-colors duration-200"
                >
                    New
                    <span v-if="notifications.filter(n => !n.isRead).length > 0" class="ml-2 px-2 py-1 text-xs font-bold bg-red-600 text-white rounded-full">
                        {{ notifications.filter(n => !n.isRead).length }}
                    </span>
                </button>
                <!-- Tab for All Notifications -->
                <button
                    @click="activeTab = 'All'"
                    :class="{ 'border-blue-600 text-blue-600 font-semibold': activeTab === 'All', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'All' }"
                    class="flex-1 py-4 px-1 text-center text-sm border-b-2 transition-colors duration-200"
                >
                    All
                </button>
            </div>

            <div class="p-4 space-y-2 overflow-y-auto">
                <div v-if="filteredNotifications.length === 0" class="p-6 text-center text-gray-500">
                    <span v-if="activeTab === 'New'">You have no new notifications.</span>
                    <span v-else>You have no notifications.</span>
                </div>
                <div
                    v-for="notification in filteredNotifications"
                    :key="notification.id"
                    class="bg-white rounded-xl shadow-sm p-4 flex items-start space-x-4 border border-gray-200 transition-all duration-200 hover:shadow-md cursor-pointer"
                    :class="{ 'bg-gray-50': notification.isRead }"
                    @click="handleButtonClick(notification)"
                >
                    <!-- Content section -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <div class="flex-1 min-w-0">
                                <h3 v-if="notification.title" class="text-base font-semibold text-gray-900 truncate">
                                    {{ notification.title }}
                                </h3>
                                <p v-if="notification.project_name" class="text-xs text-gray-500">
                                    {{ notification.project_name }}
                                </p>
                            </div>
                        </div>

                        <p v-if="notification.message" class="text-sm text-gray-600 mt-1 mb-2">
                            {{ notification.message }}
                        </p>

                        <div class="flex items-center text-xs text-gray-500 font-medium">
                            <span v-if="notification.due_date">Due: {{ formatDate(notification.due_date) }}</span>
                            <span v-if="notification.due_date && notification.priority" class="mx-2">•</span>
                            <span v-if="notification.priority">Priority: {{ notification.priority }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </RightSidebar>
</template>
