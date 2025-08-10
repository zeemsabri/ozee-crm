<script setup>
import { ref, computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { Link, usePage, router } from '@inertiajs/vue3';
import { usePermissions } from '@/Directives/permissions';
import { Bell } from 'lucide-vue-next';
import AdminDropdown from '@/Components/Layout/AdminDropdown.vue';
import UserDropdown from '@/Components/Layout/UserDropdown.vue';

const props = defineProps({
    unreadNotificationCount: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['openCreateTaskModal', 'openAddResource', 'openNotificationsSidebar']);

const showingNavigationDropdown = ref(false);
const user = computed(() => usePage().props.auth.user);

const { canDo } = usePermissions();
const canManageRoles = canDo('manage_roles');
</script>

<template>
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

                        <NavLink
                            v-if="canDo('view_emails')"
                            :href="route('inbox')"
                            :active="route().current('inbox')"
                        >
                            Inbox
                        </NavLink>

                        <NavLink
                            v-if="canDo('view_own_points')"
                            :href="route('bonus-system.index')"
                            :active="route().current('bonus-system.index')"
                        >
                            Bonus System
                        </NavLink>

                        <AdminDropdown v-if="canManageRoles" />
                    </div>
                </div>

                <div class="hidden sm:ms-6 sm:flex sm:items-center">
                    <PrimaryButton
                        type="button"
                        @click="emit('openCreateTaskModal')"
                        class="mr-4 px-4 py-2 text-sm"
                    >
                        Add Task
                    </PrimaryButton>

                    <PrimaryButton
                        type="button"
                        @click="emit('openAddResource')"
                        class="mr-4 px-4 py-2 text-sm"
                    >
                        Add Resource
                    </PrimaryButton>

                    <button
                        @click="emit('openNotificationsSidebar')"
                        class="relative flex-shrink-0 text-gray-400 hover:text-gray-600 p-1 rounded-full transition-colors duration-200 mr-4"
                        aria-label="View all notifications"
                    >
                        <Bell class="h-6 w-6" />
                        <span v-if="unreadNotificationCount > 0" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                            {{ unreadNotificationCount }}
                        </span>
                    </button>

                    <UserDropdown />
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
    </nav>
</template>
