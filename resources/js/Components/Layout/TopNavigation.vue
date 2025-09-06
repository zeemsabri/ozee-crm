<script setup>
import { ref, computed, onMounted } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { Link, usePage, router } from '@inertiajs/vue3';
import { usePermissions } from '@/Directives/permissions';
import { Bell, Plus, Award } from 'lucide-vue-next'; // Using lucide icons for a modern look
import AdminDropdown from '@/Components/Layout/AdminDropdown.vue';
import UserDropdown from '@/Components/Layout/UserDropdown.vue';
import BonusDropdown from '@/Components/Layout/BonusDropdown.vue';

const props = defineProps({
    unreadNotificationCount: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['openCreateTaskModal', 'openAddResource', 'openNotificationsSidebar', 'open-kudo-modal']);

const showingNavigationDropdown = ref(false);
const user = computed(() => usePage().props.auth.user);

const { canDo } = usePermissions();
const canManageRoles = canDo('manage_roles');

// Monthly points badge state
const monthlyPoints = ref(null);
const loadingPoints = ref(true);
const pointsError = ref(null);

onMounted(async () => {
    try {
        const { data } = await window.axios.get('/api/leaderboard/stats');
        monthlyPoints.value = data?.userMonthlyPoints ?? 0;
    } catch (e) {
        console.error('Failed to load monthly points', e);
        pointsError.value = 'Failed to load points';
        monthlyPoints.value = 0;
    } finally {
        loadingPoints.value = false;
    }
});
</script>

<template>
    <nav class="bg-white z-[99] relative w-full shadow-sm border-b border-gray-100">
        <!-- Main Header Bar -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between items-center">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <Link :href="route('dashboard')">
                        <ApplicationLogo class="block h-14 w-auto fill-current text-gray-800" />
                    </Link>
                </div>

                <!-- Desktop User Actions & Notifications -->
                <div class="hidden sm:flex items-center space-x-6">
                    <!-- Monthly Points Badge -->
                    <Link :href="route('leaderboard.index')" class="mr-3 cursor-pointer">
                        <div v-if="!loadingPoints" class="group relative inline-flex items-center px-3 py-1 rounded-full bg-gradient-to-r from-green-400 to-emerald-500 text-white text-sm font-semibold shadow-lg ring-2 ring-emerald-300/60 transition-all duration-300 ease-in-out">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            <span class="font-bold">{{ monthlyPoints ?? 0 }}</span>
                            <span class="ml-1 text-white/80">pts</span>
                        </div>
                        <div v-else class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 text-gray-500 text-sm font-medium">
                            Loading...
                        </div>
                    </Link>

                    <!-- Notifications Button -->
                    <button
                        @click="emit('openNotificationsSidebar')"
                        class="relative flex-shrink-0 text-gray-400 hover:text-indigo-600 p-2 rounded-full transition-colors duration-200"
                        aria-label="View all notifications"
                    >
                        <Bell class="h-6 w-6" />
                        <span v-if="unreadNotificationCount > 0" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                            {{ unreadNotificationCount }}
                        </span>
                    </button>

                    <!-- User Dropdown -->
                    <UserDropdown />
                </div>

                <!-- Mobile menu button -->
                <div class="-mr-2 flex items-center sm:hidden">
                    <button @click="showingNavigationDropdown = !showingNavigationDropdown"
                            class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                    >
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path
                                :class="{
                                    'hidden': showingNavigationDropdown,
                                    'inline-flex': !showingNavigationDropdown,
                                }"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                            <path
                                :class="{
                                    'hidden': !showingNavigationDropdown,
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

        <!-- Action & Navigation Bar -->
        <div class="bg-gray-50 border-t border-gray-100 py-2 hidden sm:block">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2 overflow-x-auto no-scrollbar">
                        <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </NavLink>
                        <NavLink :href="route('inbox')" :active="route().current('inbox')">
                            Inbox
                        </NavLink>
                        <NavLink :href="route('workspace.index')" :active="route().current('workspace.index')">
                            My Workspace
                        </NavLink>
                        <NavLink :href="route('presentations.index')" :active="route().current('presentations.index')">
                            Presentation
                        </NavLink>
                        <BonusDropdown />
                        <NavLink v-if="canDo('add_expendables').value" :href="route('project-expendables.index')">
                            Project Expendables
                        </NavLink>
                        <AdminDropdown v-if="canManageRoles" />
                    </div>

                    <div class="flex items-center space-x-2 flex-shrink-0">
                        <PrimaryButton
                            type="button"
                            @click="emit('openCreateTaskModal')"
                            class="px-3 py-1.5 text-sm"
                        >
                            <Plus class="h-4 w-4 mr-1.5" />
                            <span>Add Task</span>
                        </PrimaryButton>

                        <PrimaryButton
                            type="button"
                            @click="emit('openAddResource')"
                            class="px-3 py-1.5 text-sm"
                        >
                            <Plus class="h-4 w-4 mr-1.5" />
                            <span>Add Resource</span>
                        </PrimaryButton>

                        <button
                            v-if="canDo('create_kudos')"
                            type="button"
                            @click="$emit('open-kudo-modal')"
                            class="inline-flex items-center justify-center px-3 py-1.5 text-sm font-semibold rounded-lg bg-orange-500 text-white hover:bg-orange-600 transition-colors duration-200"
                        >
                            <Award class="h-4 w-4 mr-1.5" />
                            <span>Give Kudo</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div :class="{'block': showingNavigationDropdown, 'hidden': !showingNavigationDropdown}" class="sm:hidden">
        <div class="space-y-1 pt-2 pb-3">
                <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                    Dashboard
                </NavLink>
                <NavLink :href="route('inbox')" :active="route().current('inbox')">
                    Inbox
                </NavLink>
                <NavLink :href="route('workspace.index')" :active="route().current('workspace.index')">
                    My Workspace
                </NavLink>
                <NavLink :href="route('presentations.index')" :active="route().current('presentations.index')">
                    Presentation
                </NavLink>
                <NavLink :href="route('bonus-system.index')" :active="route().current('bonus-system.index')">
                    Bonus System
                </NavLink>
                <NavLink :href="route('leaderboard.index')" :active="route().current('leaderboard.index')">
                    Leaderboard
                </NavLink>
                <NavLink :href="route('kudos.index')" :active="route().current('kudos.index')">
                    Kudos
                </NavLink>
                <NavLink v-if="canDo('add_expendables').value" :href="route('project-expendables.index')">
                    Project Expendables
                </NavLink>
            </div>
        </div>
    </nav>
</template>
