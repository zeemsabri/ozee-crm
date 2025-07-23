<script setup>
import { ref, computed, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { usePermissions, useGlobalPermissions } from '@/Directives/permissions';

const showingNavigationDropdown = ref(false);

const user = computed(() => usePage().props.auth.user);
// Get role name from role_data (new system)
const roleName = computed(() => user.value.role_data?.name || '');

// Use the permissions system instead of hardcoded role checks
const { canDo } = usePermissions();

// Global permission checks for menu items
const canAccessProjects = canDo('access_projects');
const canComposeEmails = canDo('compose_emails');
const canApproveEmails = canDo('approve_emails');
const canManageUsers = canDo('manage_users');
const canManageRoles = canDo('manage_roles');
const canManageTaskTypes = canDo('manage_task_types');
const canAccessClients = canDo('access_clients');


const setAxiosAuthHeader = async () => {
    const token = localStorage.getItem('authToken');
    if (token) {
        window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
        delete window.axios.defaults.headers.common['Authorization'];
    }
};

onMounted(() => {
    setAxiosAuthHeader();
});

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
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800" />
                                </Link>
                            </div>

                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                                    Dashboard
                                </NavLink>



                                <NavLink v-if="canAccessProjects" :href="route('projects.index')" :active="route().current('projects.index')">
                                    Projects
                                </NavLink>

                                <NavLink v-if="canComposeEmails" :href="route('emails.compose')" :active="route().current('emails.compose')">
                                    Compose Email
                                </NavLink>

                                <NavLink v-if="canApproveEmails" :href="route('emails.pending')" :active="route().current('emails.pending')">
                                    Approve Emails
                                </NavLink>

                                <NavLink v-if="canComposeEmails" :href="route('emails.rejected')" :active="route().current('emails.rejected')">
                                    Rejected Emails
                                </NavLink>

                                <!-- Admin dropdown for roles and permissions -->
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

                                            <DropdownLink v-if="canAccessClients" :href="route('clients.index')" :active="route().current('clients.index')">
                                                Clients
                                            </DropdownLink>

                                            <DropdownLink v-if="canManageUsers" :href="route('users.index')" :active="route().current('users.index')">
                                                Users
                                            </DropdownLink>

                                            <DropdownLink v-if="canManageTaskTypes" :href="route('task-types.index')" :active="route().current('task-types.index')">
                                                Task Types
                                            </DropdownLink>

                                            <DropdownLink :href="route('admin.roles.index')">
                                                Manage Roles
                                            </DropdownLink>
                                            <DropdownLink :href="route('admin.permissions.index')">
                                                Manage Permissions
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
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

                        <!-- Admin section for mobile -->
                        <div v-if="canManageRoles" class="mt-3 space-y-1">
                            <div class="px-4 font-medium text-base text-gray-800">Admin</div>
                            <ResponsiveNavLink :href="route('admin.roles.index')" :active="route().current('admin.roles.index')">
                                Manage Roles
                            </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('admin.permissions.index')" :active="route().current('admin.permissions.index')">
                                Manage Permissions
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

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
