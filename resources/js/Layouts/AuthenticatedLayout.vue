<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import PushNotificationContainer from '@/Components/PushNotificationContainer.vue';
import StandardNotificationContainer from '@/Components/StandardNotificationContainer.vue'; // Import the new component
import AvailabilityBlocker from '@/Components/Availability/AvailabilityBlocker.vue';
import { usePage, router } from '@inertiajs/vue3';
import { setStandardNotificationContainer } from '@/Utils/notification';
import CreateTaskModal from "@/Components/ProjectTasks/CreateTaskModal.vue";
import CreateResourceForm from "@/Components/ShareableResource/CreateForm.vue";
import NotificationsSidebar from '@/Components/NotificationsSidebar.vue';
import KudoModal from '@/Components/Kudos/KudoModal.vue';
import {
    openNotificationsSidebar,
    notificationSidebarState,
    fetchNotificationsFromDatabase,
} from '@/Utils/notification-sidebar';
import LeftSidebar from '@/Components/LeftSidebar.vue';
import TopNavigation from '@/Components/Layout/TopNavigation.vue';
import MobileNavigation from '@/Components/Layout/MobileNavigation.vue';
import TaskSidebar from '@/Components/Layout/TaskSidebar.vue';

const showingNavigationDropdown = ref(false);
const openCreateTaskModel = ref(false);
const addResource = ref(false);
const openKudoModal = ref(false);

const allProjectsForSidebar = ref([]);
const loadingAllProjects = ref(true);
const activeProjectId = computed(() => usePage().props.id || null);

// Create a new ref for the standard notification container
const standardNotificationContainerRef = ref(null);

const setAxiosAuthHeader = async () => {
    const token = localStorage.getItem('authToken');
    if (token) {
        window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        try {
            await window.axios.get('/api/user');
        } catch (error) {
            if (error.response && error.response.status === 401) {
                console.log('Session expired, redirecting to login');
                localStorage.removeItem('authToken');
                localStorage.removeItem('userRole');
                localStorage.removeItem('userId');
                localStorage.removeItem('userEmail');
                localStorage.removeItem('remembered');
                delete window.axios.defaults.headers.common['Authorization'];
                window.location.href = '/login';
            }
        }
    } else {
        delete window.axios.defaults.headers.common['Authorization'];
    }
};

const handleLogoutSuccess = () => {
    localStorage.removeItem('authToken');
    localStorage.removeItem('userRole');
    localStorage.removeItem('userId');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('remembered');
    delete window.axios.defaults.headers.common['Authorization'];
};

const handleLogoutError = (error) => {
    localStorage.removeItem('authToken');
    localStorage.removeItem('userRole');
    localStorage.removeItem('userId');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('remembered');
    delete window.axios.defaults.headers.common['Authorization'];
    window.location.href = '/login';
};

const fetchAllProjects = async () => {
    loadingAllProjects.value = true;
    try {
        const response = await window.axios.get('/api/projects-simplified');
        allProjectsForSidebar.value = response.data;
    } catch (error) {
        console.error('Error fetching all projects for sidebar:', error);
        allProjectsForSidebar.value = [];
    } finally {
        loadingAllProjects.value = false;
    }
};

const handleProjectSelected = (projectId) => {
    router.visit(route('projects.show', projectId));
};

const unreadNotificationCount = computed(() => {
    return notificationSidebarState.value.notifications.filter(n => !n.isRead).length;
});

const handleTaskUpdated = (task) => {
    console.log('Task updated globally.', task);
};

const handleTaskDeleted = (taskId) => {
    console.log('Task deleted globally.', taskId);
};

// --- Global Notice Modal Logic ---
const showNoticeModal = ref(false);
const unreadNotices = ref([]);
const acknowledgeChecked = ref(false);
let noticeIntervalId = null;

const fetchUnreadNotices = async () => {
    try {
        const res = await window.axios.get('/api/notices/unread');
        unreadNotices.value = res.data?.data || [];
        showNoticeModal.value = unreadNotices.value.length > 0;
    } catch (e) {
        console.error('Failed to fetch unread notices', e);
    }
};

const acknowledgeNotices = async () => {
    if (!acknowledgeChecked.value) return;
    try {
        const ids = unreadNotices.value.map(n => n.id);
        if (ids.length === 0) return;
        await window.axios.post('/api/notices/acknowledge', { notice_ids: ids });
        showNoticeModal.value = false;
        unreadNotices.value = [];
        acknowledgeChecked.value = false;
    } catch (e) {
        console.error('Failed to acknowledge notices', e);
    }
};

const handleNoticeLinkClick = (notice) => {
    window.location.href = `/notices/${notice.id}/redirect`;
};

onMounted(() => {
    setAxiosAuthHeader();
    // Set the standard notification container to the new component instance
    if (standardNotificationContainerRef.value) {
        setStandardNotificationContainer(standardNotificationContainerRef.value);
    }
    fetchAllProjects();
    fetchNotificationsFromDatabase();

    // Notice polling
    fetchUnreadNotices();
    noticeIntervalId = setInterval(fetchUnreadNotices, 60 * 1000);
});

onBeforeUnmount(() => {
    if (noticeIntervalId) clearInterval(noticeIntervalId);
});
</script>

<template>
    <div class="flex h-screen overflow-hidden">
        <!-- Standard Toast Notifications -->
        <StandardNotificationContainer ref="standardNotificationContainerRef" />

        <!-- Push Notifications (no longer needs a ref for standard notifications) -->
        <PushNotificationContainer :sidebar-is-open="notificationSidebarState.show" />

        <AvailabilityBlocker />
        <LeftSidebar
            :all-projects="allProjectsForSidebar"
            :active-project-id="activeProjectId"
            @project-selected="handleProjectSelected"
        />

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <TopNavigation
                :unread-notification-count="unreadNotificationCount"
                @open-create-task-modal="openCreateTaskModel = true"
                @open-add-resource="addResource = true"
                @open-notifications-sidebar="openNotificationsSidebar"
                @open-kudo-modal="openKudoModal = true"
            />

            <!-- Mobile Navigation -->
            <MobileNavigation
                :showing-navigation-dropdown="showingNavigationDropdown"
                :unread-notification-count="unreadNotificationCount"
                @open-create-task-modal="openCreateTaskModel = true"
                @open-add-resource="addResource = true"
                @open-notifications-sidebar="openNotificationsSidebar"
                @logout-success="handleLogoutSuccess"
                @logout-error="handleLogoutError"
            />

            <header class="bg-white shadow" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>

            <CreateTaskModal :show="openCreateTaskModel" @close="openCreateTaskModel = false" @saved="openCreateTaskModel = false" />
            <CreateResourceForm
                api-endpoint="/api/shareable-resources"
                :show="addResource"
                @close="addResource = false" />

            <KudoModal :show="openKudoModal" @close="openKudoModal = false" @submitted="openKudoModal = false" />
        </div>

        <!-- Task Sidebar -->
        <TaskSidebar
            @task-updated="handleTaskUpdated"
            @task-deleted="handleTaskDeleted"
        />

        <NotificationsSidebar />

        <!-- Notice Board Modal (Global) -->
        <div v-if="showNoticeModal" class="fixed inset-0 z-[60] flex items-center justify-center">
            <div class="absolute inset-0 bg-black bg-opacity-50" @click="() => {}"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 p-6">
                <h3 class="text-xl font-semibold mb-4">Important Notices</h3>
                <div v-for="notice in unreadNotices" :key="notice.id" class="border rounded p-4 mb-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-medium text-gray-900">{{ notice.title }}</div>
                            <div class="text-sm text-gray-600 whitespace-pre-line mt-1">{{ notice.description }}</div>
                            <div class="text-xs text-gray-500 mt-2">Type: {{ notice.type }}</div>
                        </div>
                        <div v-if="notice.url">
                            <a :href="`/notices/${notice.id}/redirect`" target="_blank" class="text-indigo-600 hover:text-indigo-800">Open Link</a>
                        </div>
                    </div>
                </div>
                <div class="flex items-center mt-4">
                    <input id="acknowledge" type="checkbox" v-model="acknowledgeChecked" class="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                    <label for="acknowledge" class="ml-2 text-sm text-gray-700">I have read and understand this notice.</label>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button @click="showNoticeModal = false" class="px-4 py-2 rounded border border-gray-300 text-gray-700">Close</button>
                    <button @click="acknowledgeNotices" :disabled="!acknowledgeChecked" class="px-4 py-2 bg-indigo-600 text-white rounded disabled:opacity-50">Acknowledge</button>
                </div>
            </div>
        </div>
    </div>
</template>
