<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import PushNotificationContainer from '@/Components/PushNotificationContainer.vue';
import StandardNotificationContainer from '@/Components/StandardNotificationContainer.vue';
import AvailabilityBlocker from '@/Components/Availability/AvailabilityBlocker.vue';
import { usePage, router } from '@inertiajs/vue3';
import { setStandardNotificationContainer, setNoticeFetcher } from '@/Utils/notification';
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
import NoticeboardModal from "@/Components/Notices/NoticeboardModal.vue";
import { useNotices } from '@/Utils/useNotices.js';
import PromptOrchestrator from '@/Components/Prompts/PromptOrchestrator.vue';
import MeetingMinutesModal from '@/Components/MeetingMinutesModal.vue';

const showingNavigationDropdown = ref(false);
const openCreateTaskModel = ref(false);
const addResource = ref(false);
const openKudoModal = ref(false);
const openMeetingMinutesModal = ref(false);

const allProjectsForSidebar = ref([]);
const loadingAllProjects = ref(true);
const activeProjectId = computed(() => usePage().props.id || null);

// Use the new composable for all notice logic
const { showNoticeModal, unreadNotices, fetchUnreadNotices, closeModal } = useNotices();

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

    // Register notice fetcher for push-triggered full modal
    setNoticeFetcher(fetchUnreadNotices);

    // Initial fetch for notices
    fetchUnreadNotices();
});

onBeforeUnmount(() => {
    // Clear the registered notice fetcher when layout unmounts
    setNoticeFetcher(null);
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
                @open-meeting-minutes-modal="openMeetingMinutesModal = true"
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

            <MeetingMinutesModal 
                :show="openMeetingMinutesModal" 
                :projects="allProjectsForSidebar"
                @close="openMeetingMinutesModal = false" 
                @minutes-added="openMeetingMinutesModal = false" 
            />
        </div>

        <!-- Task Sidebar -->
        <TaskSidebar
            @task-updated="handleTaskUpdated"
            @task-deleted="handleTaskDeleted"
        />

        <NotificationsSidebar />

        <!-- The NoticeboardModal now uses the composable's state and functions -->
        <NoticeboardModal :show="showNoticeModal"
                         :unreadNotices="unreadNotices"
                         @close="closeModal" />

        <!-- Global Prompt Orchestrator for user-data prompts (e.g., timezone) -->
        <PromptOrchestrator />
    </div>
</template>
