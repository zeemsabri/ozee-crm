<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import PushNotificationContainer from '@/Components/PushNotificationContainer.vue';
import AvailabilityBlocker from '@/Components/Availability/AvailabilityBlocker.vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { usePermissions, useGlobalPermissions } from '@/Directives/permissions';
import { setStandardNotificationContainer } from '@/Utils/notification';
import CreateTaskModal from "@/Components/ProjectTasks/CreateTaskModal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import CreateResourceForm from "@/Components/ShareableResource/CreateForm.vue";
import RightSidebar from '@/Components/RightSidebar.vue';
import TaskDetailSidebar from '@/Components/ProjectTasks/TaskDetailSidebar.vue';
import { sidebarState, closeTaskDetailSidebar } from '@/Utils/sidebar';
import NotificationsSidebar from '@/Components/NotificationsSidebar.vue';
import {
    openNotificationsSidebar,
    notificationSidebarState,
    fetchNotificationsFromDatabase,
} from '@/Utils/notification-sidebar';
import LeftSidebar from '@/Components/LeftSidebar.vue';
import { Bell } from 'lucide-vue-next';
import { markRaw } from 'vue';

const showingNavigationDropdown = ref(false);

const user = computed(() => usePage().props.auth.user);
const roleName = computed(() => user.value.role_data?.name || '');

const { canDo } = usePermissions();
const canAccessProjects = canDo('manage_projects');
const canComposeEmails = canDo('compose_emails');
const canApproveEmails = canDo('approve_emails');
const canManageUsers = canDo('create_users');
const canManageRoles = canDo('manage_roles');
const canManageTaskTypes = canDo('manage_task_types');
const canAccessClients = canDo('create_clients');

const openCreateTaskModel = ref(false);
const addResource = ref(false);

const allProjectsForSidebar = ref([]);
const loadingAllProjects = ref(true);
const activeProjectId = computed(() => usePage().props.id || null);

const handleTaskDetailUpdated = () => {
    console.log('Task updated from sidebar. Refreshing project data if necessary.');
};

const handleTaskDeleted = () => {
    console.log('Task deleted from sidebar. Refreshing project data if necessary.');
    closeTaskDetailSidebar();
};

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
    delete window.axios.defaults.headers.common['Authorization'];
};

const handleLogoutError = (error) => {
    localStorage.removeItem('authToken');
    localStorage.removeItem('userRole');
    localStorage.removeItem('userId');
    localStorage.removeItem('userEmail');
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

const notificationContainerRef = ref(null);

const unreadNotificationCount = computed(() => {
    return notificationSidebarState.value.notifications.filter(n => !n.isRead).length;
});

onMounted(() => {
    setAxiosAuthHeader();
    if (notificationContainerRef.value) {
        setStandardNotificationContainer(notificationContainerRef.value);
    }
    fetchAllProjects();
    fetchNotificationsFromDatabase(); // Call the utility function
});
</script>

<template>
    <div class="flex h-screen overflow-hidden">
        <!-- Push Notification Container (visible only when the notifications sidebar is not open) -->
        <PushNotificationContainer
            ref="notificationContainerRef"
            :sidebar-is-open="notificationSidebarState.show"
        />
        <AvailabilityBlocker />
        <LeftSidebar
            :all-projects="allProjectsForSidebar"
            :active-project-id="activeProjectId"
            @project-selected="handleProjectSelected"
        />

        <div class="flex-1 flex flex-col overflow-hidden">
            <nav class="border-b border-gray-100 bg-white z-10 relative">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo class="block h-16 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>

                                <div v-if="canManageRoles" class="hidden sm:flex sm:items-center">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <span class="inline-flex rounded-md">
                                                <button type="button"
                                                        class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                                >
                                                    Admin
                                                    <svg class="ms-2 -me-0.5 h-4 w-4"
                                                         xmlns="http://www.w3.org/2000/svg"
                                                         viewBox="0 0 20 20"
                                                         fill="currentColor"
                                                    >
                                                        <path fill-rule="evenodd"
                                                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                              clip-rule="evenodd"
                                                        />
                                                    </svg>
                                                </button>
                                            </span>
                                        </template>

                                        <template #content>
                                            <DropdownLink v-if="canAccessProjects" :href="route('projects.index')" :active="route().current('projects.index')">
                                                Projects
                                            </DropdownLink>
                                            <DropdownLink v-if="canApproveEmails" :href="route('emails.pending')" :active="route().current('emails.pending')">
                                                Approve Emails
                                            </DropdownLink>
                                            <DropdownLink v-if="canComposeEmails" :href="route('emails.rejected')" :active="route().current('emails.rejected')">
                                                Rejected Emails
                                            </DropdownLink>
                                            <DropdownLink v-if="canManageUsers" :href="route('availability.index')" :active="route().current('availability.index')">
                                                Weekly Availability
                                            </DropdownLink>
                                            <DropdownLink v-if="canAccessClients" :href="route('clients.index')" :active="route().current('clients.index')">
                                                Clients
                                            </DropdownLink>
                                            <DropdownLink v-if="canManageUsers" :href="route('users.index')" :active="route().current('users.index')">
                                                Users
                                            </DropdownLink>
                                            <DropdownLink v-if="canManageTaskTypes" :href="route('task-types.index')" :active="route().current('task-types.index')">
                                                Task Types
                                            </DropdownLink>
                                            <DropdownLink v-if="canDo('manage_bonus_configuration')" :href="route('bonus-configuration.index')" :active="route().current('bonus-configuration.index')">
                                                Bonus Configuration
                                            </DropdownLink>
                                            <DropdownLink v-if="canDo('manage_email_templates')" :href="route('email-templates.index')" :active="route().current('email-templates.index')">
                                                Email Templates
                                            </DropdownLink>
                                            <DropdownLink v-if="canDo('manage_placeholder_definitions')" :href="route('placeholder-definitions.index')" :active="route().current('placeholder-definitions.index')">
                                                Placeholder Definitions
                                            </DropdownLink>
                                            <DropdownLink v-if="canDo('manage_roles')" :href="route('admin.roles.index')">
                                                Manage Roles
                                            </DropdownLink>
                                            <DropdownLink v-if="canDo('manage_permissions')" :href="route('admin.permissions.index')">
                                                Manage Permissions
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <PrimaryButton
                                type="button"
                                @click="openCreateTaskModel = true"
                                class="mr-4 px-4 py-2 text-sm"
                            >
                                Add Task
                            </PrimaryButton>

                            <PrimaryButton
                                type="button"
                                @click="addResource = true"
                                class="mr-4 px-4 py-2 text-sm"
                            >
                                Add Resource
                            </PrimaryButton>

                            <button
                                @click="openNotificationsSidebar"
                                class="relative flex-shrink-0 text-gray-400 hover:text-gray-600 p-1 rounded-full transition-colors duration-200 mr-4"
                                aria-label="View all notifications"
                            >
                                <Bell class="h-6 w-6" />
                                <span v-if="unreadNotificationCount > 0" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                                    {{ unreadNotificationCount }}
                                </span>
                            </button>

                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button"
                                                    class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ user.name }} ({{ user.role_data?.name }})

                                                <svg class="ms-2 -me-0.5 h-4 w-4"
                                                     xmlns="http://www.w3.org/2000/svg"
                                                     viewBox="0 0 20 20"
                                                     fill="currentColor"
                                                >
                                                    <path fill-rule="evenodd"
                                                          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                          clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button" @success="handleLogoutSuccess" @error="handleLogoutError">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <div class="-me-2 flex items-center sm:hidden">
                            <button @click="showingNavigationDropdown = !showingNavigationDropdown"
                                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <div v-if="canManageRoles" class="mt-3 space-y-1">
                            <div class="px-4 font-medium text-base text-gray-800">Admin</div>
                            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                Dashboard
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="canAccessClients" :href="route('clients.index')" :active="route().current('clients.index')">
                                Clients
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="canAccessProjects" :href="route('projects.index')" :active="route().current('projects.index')">
                                Projects
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="canManageUsers" :href="route('users.index')" :active="route().current('users.index')">
                                Users
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="canComposeEmails" :href="route('emails.compose')" :active="route().current('emails.compose')">
                                Compose Email
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="canApproveEmails" :href="route('emails.pending')" :active="route().current('emails.pending')">
                                Approve Emails
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="canComposeEmails" :href="route('emails.rejected')" :active="route().current('emails.rejected')">
                                Rejected Emails
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="canManageUsers" :href="route('availability.index')" :active="route().current('availability.index')">
                                Weekly Availability
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canDo('manage_roles')" :href="route('admin.roles.index')" :active="route().current('admin.roles.index')">
                                Manage Roles
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canDo('manage_permissions')" :href="route('admin.permissions.index')" :active="route().current('admin.permissions.index')">
                                Manage Permissions
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canDo('manage_bonus_configuration')" :href="route('bonus-configuration.index')" :active="route().current('bonus-configuration.index')">
                                Bonus Configuration
                            </ResponsiveNavLink>
                            <ResponsiveNavLink v-if="canManageTaskTypes" :href="route('task-types.index')" :active="route().current('task-types.index')">
                                Task Types
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="canDo('manage_email_templates')" :href="route('email-templates.index')" :active="route().current('email-templates.index')">
                                Email Templates
                            </ResponsiveNavLink>

                            <ResponsiveNavLink v-if="canDo('manage_placeholder_definitions')" :href="route('placeholder-definitions.index')" :active="route().current('placeholder-definitions.index')">
                                Placeholder Definitions
                            </ResponsiveNavLink>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ user.email }}
                            </div>
                            <div class="text-sm font-medium text-gray-500 capitalize">
                                {{ user.role_data?.name }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink as="button" @click="openCreateTaskModel = true">
                                Add Task
                            </ResponsiveNavLink>
                            <ResponsiveNavLink as="button" @click="addResource = true">
                                Add Resource
                            </ResponsiveNavLink>
                            <ResponsiveNavLink as="button" @click="openNotificationsSidebar">
                                Notifications ({{ unreadNotificationCount }})
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button" @success="handleLogoutSuccess" @error="handleLogoutError">
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

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
        </div>

        <RightSidebar
            :show="sidebarState.show"
            @update:show="closeTaskDetailSidebar"
            :title="sidebarState.taskId ? 'Task Details' : 'Details'"
            :initialWidth="50"
        >
            <template #content>
                <TaskDetailSidebar
                    v-if="sidebarState.taskId"
                    :task-id="sidebarState.taskId"
                    :project-id="sidebarState.projectId"
                    :project-users="[]"
                    @close="closeTaskDetailSidebar"
                    @task-updated="handleTaskDetailUpdated"
                    @task-deleted="handleTaskDeleted"
                />
            </template>
        </RightSidebar>

        <NotificationsSidebar />

    </div>
</template>
