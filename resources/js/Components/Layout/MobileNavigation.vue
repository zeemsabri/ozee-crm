<script setup>
import { computed } from 'vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/Directives/permissions';

const props = defineProps({
    showingNavigationDropdown: {
        type: Boolean,
        required: true
    },
    unreadNotificationCount: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['openCreateTaskModal', 'openAddResource', 'openNotificationsSidebar', 'logoutSuccess', 'logoutError']);

const user = computed(() => usePage().props.auth.user);

const { canDo } = usePermissions();
const canAccessProjects = canDo('manage_projects');
const canComposeEmails = canDo('compose_emails');
const canApproveEmails = canDo('approve_emails');
const canManageUsers = canDo('create_users');
const canManageRoles = canDo('manage_roles');
const canManageTaskTypes = canDo('manage_task_types');
const canAccessClients = canDo('create_clients');

const handleLogoutSuccess = () => {
    localStorage.removeItem('authToken');
    localStorage.removeItem('userRole');
    localStorage.removeItem('userId');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('remembered');
    delete window.axios.defaults.headers.common['Authorization'];
    emit('logoutSuccess');
};

const handleLogoutError = (error) => {
    localStorage.removeItem('authToken');
    localStorage.removeItem('userRole');
    localStorage.removeItem('userId');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('remembered');
    delete window.axios.defaults.headers.common['Authorization'];
    window.location.href = '/login';
    emit('logoutError', error);
};
</script>

<template>
    <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
        <div class="space-y-1 pb-3 pt-2">
            <div v-if="canManageRoles" class="mt-3 space-y-1">
                <div class="px-4 font-medium text-base text-gray-800">Admin</div>
                <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                    Dashboard
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="canDo('view_emails')" :href="route('inbox')" :active="route().current('inbox')">
                    Inbox
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

                <ResponsiveNavLink v-if="canManageTaskTypes" :href="route('task-types.index')" :active="route().current('task-types.index')">
                    Task Types
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="canDo('manage_email_templates')" :href="route('email-templates.index')" :active="route().current('email-templates.index')">
                    Email Templates
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="canDo('manage_placeholder_definitions')" :href="route('placeholder-definitions.index')" :active="route().current('placeholder-definitions.index')">
                    Placeholder Definitions
                </ResponsiveNavLink>

                <ResponsiveNavLink v-if="canDo('view_shareable_resources')" :href="route('shareable-resources.index')" :active="route().current('shareable-resources.index')">
                    Shareable Resources
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
                <ResponsiveNavLink as="button" @click="emit('openCreateTaskModal')">
                    Add Task
                </ResponsiveNavLink>
                <ResponsiveNavLink as="button" @click="emit('openAddResource')">
                    Add Resource
                </ResponsiveNavLink>
                <ResponsiveNavLink as="button" @click="emit('openNotificationsSidebar')">
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
</template>
