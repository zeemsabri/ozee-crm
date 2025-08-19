<script setup>
import { ref, computed, onMounted } from 'vue';
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
    <nav class="border-b border-gray-100 bg-white z-10 relative w-full">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between">
                <div class="flex flex-1 min-w-0">
                    <div class="flex shrink-0 items-center">
                        <Link :href="route('dashboard')">
                            <ApplicationLogo class="block h-16 w-auto fill-current text-gray-800" />
                        </Link>
                    </div>

                    <div class="hidden sm:-my-px sm:ms-10 sm:flex sm:flex-wrap sm:gap-4 overflow-x-auto no-scrollbar">
                        <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </NavLink>

                        <NavLink
                            :href="route('inbox')"
                            :active="route().current('inbox')"
                        >
                            Inbox
                        </NavLink>

                        <NavLink
                            :href="route('bonus-system.index')"
                            :active="route().current('bonus-system.index')"
                        >
                            Bonus System
                        </NavLink>

                        <NavLink
                            :href="route('leaderboard.index')"
                            :active="route().current('leaderboard.index')"
                        >
                            Leaderboard
                        </NavLink>

                        <NavLink
                            :href="route('kudos.index')"
                            :active="route().current('kudos.index')"
                        >
                            Kudos
                        </NavLink>

                        <NavLink v-if="canDo('add_expendables').value " :href="route('project-expendables.index')" class="!px-2 !py-1.5">Project Expendables</NavLink>

                        <AdminDropdown v-if="canManageRoles" />
                    </div>
                </div>

                <div class="hidden sm:ms-6 sm:flex sm:items-center">
                    <PrimaryButton
                        type="button"
                        @click="emit('openCreateTaskModal')"
                        class="mr-2 px-4 py-2 text-sm"
                    >
                        Add Task
                    </PrimaryButton>

                    <PrimaryButton
                        type="button"
                        @click="emit('openAddResource')"
                        class="mr-2 px-4 py-2 text-sm"
                    >
                        Add Resource
                    </PrimaryButton>

                    <!-- Fun Kudo Button -->
                    <PrimaryButton
                        v-if="canDo('create_kudos')"
                        type="button"
                        @click="$emit('open-kudo-modal')"
                        class="mr-2 px-4 py-2 text-sm bg-orange-500 hover:bg-orange-600"
                    >
                        ✨ Give Kudo
                    </PrimaryButton>

                    <!-- Monthly Points Badge -->
                    <div class="mr-3">
                        <div
                            v-if="!loadingPoints"
                            class="inline-flex items-center px-3 py-1 rounded-full bg-gradient-to-r from-green-500 to-emerald-600 text-white text-sm font-semibold shadow-md ring-2 ring-green-300/60"
                            title="Your points this month"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3 0 2.25 3 5 3 5s3-2.75 3-5c0-1.657-1.343-3-3-3z" />
                            </svg>
                            <span>{{ monthlyPoints ?? 0 }}</span>
                            <span class="ml-1 text-white/80">pts</span>
                        </div>
                        <div v-else class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 text-gray-500 text-sm font-medium">
                            Loading...
                        </div>
                    </div>

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
