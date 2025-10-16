<script setup>
import { reactive, onMounted, computed, watch, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import EmailFilters from '@/Pages/Emails/Inbox/Components/EmailFilters.vue';
import EmailCategoryFilters from '@/Pages/Emails/Inbox/Components/EmailCategoryFilters.vue';
import EmailList from '@/Pages/Emails/Inbox/Components/EmailList.vue';
import EmailDetailsContent from '@/Pages/Emails/Inbox/Components/EmailDetailsContent.vue';
import ComposeEmailContent from '@/Pages/Emails/Inbox/Components/ComposeEmailContent.vue';
import CustomComposeEmailContent from '@/Pages/Emails/Inbox/Components/CustomComposeEmailContent.vue';
import EmailActionContent from '@/Pages/Emails/Inbox/Components/EmailActionContent.vue';
import ReceivedEmailActionContent from '@/Pages/Emails/Inbox/Components/ReceivedEmailActionContent.vue';
import CustomEmailApprovalContent from '@/Pages/Emails/Inbox/Components/CustomEmailApprovalContent.vue';
import { usePermissions, usePermissionStore } from '@/Directives/permissions.js';
import { fetchEmails as fetchEmailsApi, markAsRead as markAsReadApi } from '@/Services/api-service.js';
import axios from 'axios';
import {
    StarIcon,
    ClockIcon,
    PaperAirplaneIcon,
    InboxArrowDownIcon,
    ListBulletIcon,
    ChevronRightIcon,
    ChevronLeftIcon,
    AdjustmentsHorizontalIcon
} from '@heroicons/vue/24/outline';
import Notification from "@/src/Components/Notification.vue";


// Centralized UI state for the entire inbox dashboard
const inboxState = reactive({
    // State for the RightSidebar component
    sidebar: {
        show: false,
        title: '',
        mode: null, // Controls the content rendered in the sidebar: 'view-email', 'compose', etc.
        loading: false,
        data: null,
    },
    // Filters for the email list
    filters: {
        type: 'new',
        status: '',
        startDate: '',
        endDate: '',
        search: '',
        projectId: null,
        senderId: '',
        categoryIds: [],
    },
    // The email list and related loading/error state
    emails: [],
    loadingEmails: false,
    emailError: null,
    pagination: {},
    counts: {
        'new': 0,
        'waiting-approval': 0,
        'sent': 0,
        'received': 0,
        'all': 0,
    },
});

const showAdvancedFilters = ref(false);
const notifications = ref(null);

// Use the permissions hook to check for user capabilities
const { canDo } = usePermissions();

// Computed properties to check for permissions, simplifying template logic
const canViewEmails = computed(() => canDo('view_emails').value);
const canApproveEmails = computed(() => canDo('approve_emails').value);
const canComposeEmails = computed(() => canDo('compose_emails').value);
const canCreateCustomEmails = computed(() => canDo('create_custom_emails').value);

const filterOptions = computed(() => [
    { type: 'new', label: 'New Emails', icon: StarIcon },
    { type: 'waiting-approval', label: 'Waiting Approval', icon: ClockIcon },
    { type: 'sent', label: 'Sent', icon: PaperAirplaneIcon },
    { type: 'received', label: 'Received', icon: InboxArrowDownIcon },
    { type: 'all', label: 'All Emails', icon: ListBulletIcon },
]);

const sidebarTitle = computed(() => {
    if (inboxState.sidebar.mode === 'view-email') {
        return inboxState.sidebar.data?.subject || 'Email Details';
    } else if (inboxState.sidebar.mode === 'compose') {
        return 'Compose New Email';
    } else if (inboxState.sidebar.mode === 'edit') {
        return 'Edit & Approve Template Email';
    } else if (inboxState.sidebar.mode === 'custom-edit') {
        return 'Edit & Approve Custom Email';
    } else if (inboxState.sidebar.mode === 'reject') {
        return 'Reject Email';
    } else if (inboxState.sidebar.mode === 'received-edit') {
        return 'Approve Received Email';
    }
    return 'Inbox';
});


const fetchCounts = async () => {
    try {
        const response = await axios.get('/api/inbox/counts');
        inboxState.counts = {
            ...inboxState.counts,
            'waiting-approval': response.data['waiting-approval'],
            'received': response.data['received'],
        };
    } catch (error) {
        console.error('Failed to fetch email counts:', error);
    }
};

const openComposeEmail = () => {
    inboxState.sidebar.show = true;
    inboxState.sidebar.title = 'Compose New Email';
    inboxState.sidebar.mode = 'compose';
    inboxState.sidebar.data = null;
};

// Open Custom Emails (non-template based)
const openCustomComposeEmail = async () => {
    inboxState.sidebar.show = true;
    inboxState.sidebar.title = 'Compose Custom Email';
    inboxState.sidebar.mode = 'custom-compose';
    inboxState.sidebar.data = null;
};

const handleViewEmail = async (email) => {
    inboxState.sidebar.loading = true;
    inboxState.sidebar.show = true;
    inboxState.sidebar.title = 'Email Details';
    inboxState.sidebar.mode = 'view-email';
    inboxState.sidebar.data = email;

    try {
        await markAsReadApi(email.id);
    } catch (error) {
        console.error('Failed to mark email as read:', error);
    } finally {
        inboxState.sidebar.loading = false;
        fetchEmails();
        fetchCounts();
    }
};

const fetchEmails = async (page = 1) => {
    inboxState.loadingEmails = true;
    inboxState.emailError = null;

    try {
        // Include category_ids in the filters when making API call
        const filtersWithCategories = {
            ...inboxState.filters,
            category_ids: inboxState.filters.categoryIds
        };
        const response = await fetchEmailsApi(filtersWithCategories, page);
        inboxState.emails = response.data;
        inboxState.pagination = {
            currentPage: response.current_page,
            lastPage: response.last_page,
            total: response.total,
        };
        inboxState.counts[inboxState.filters.type] = response.total;
    } catch (err) {
        console.error('Error fetching emails:', err);
        inboxState.emailError = 'Failed to load emails. Please try again.';
    } finally {
        inboxState.loadingEmails = false;
    }
};

const changePage = (page) => {
    fetchEmails(page);
};

const handleFilterChange = (newFilters) => {
    Object.assign(inboxState.filters, newFilters);
};

const handleFilterTypeChange = (newType) => {
    inboxState.filters.type = newType;
    if (newType === 'all') {
        inboxState.filters.status = '';
        inboxState.filters.startDate = '';
        inboxState.filters.endDate = '';
        inboxState.filters.search = '';
        inboxState.filters.projectId = null;
        inboxState.filters.senderId = '';
        // Keep categoryIds when changing type - they should work additively
    }
    if (newType !== 'waiting-approval' && newType !== 'received') {
        inboxState.counts[newType] = 0;
    }
};

const handleCategoryFiltersChange = (selectedCategories) => {
    inboxState.filters.categoryIds = selectedCategories;
};

const showNotification = (notification) => {
    notifications.value = notification;
    setTimeout(() => {
        notifications.value = null;
    }, 5000);
};

onMounted(() => {
    usePermissionStore().fetchGlobalPermissions();
    fetchCounts();
});

const handleEditEmail = (email) => {
    inboxState.sidebar.show = true;
    inboxState.sidebar.data = email;

    if (email.type === 'received' && (email.status === 'pending_approval_received' || email.status === 'received')) {
        inboxState.sidebar.mode = 'received-edit';
        inboxState.sidebar.title = 'Approve Received Email';
    } else {
        if (!email.template_id) {
            inboxState.sidebar.mode = 'custom-edit';
            inboxState.sidebar.title = 'Edit & Approve Custom Email';
        } else {
            inboxState.sidebar.mode = 'edit';
            inboxState.sidebar.title = 'Edit & Approve Template Email';
        }
    }
};

const handleRejectEmail = (email) => {
    inboxState.sidebar.show = true;
    inboxState.sidebar.title = 'Reject Email';
    inboxState.sidebar.mode = 'reject';
    inboxState.sidebar.data = email;
};

const handleSubmitted = () => {
    inboxState.sidebar.show = false;
    fetchEmails();
    fetchCounts();
};

let debounceTimer = null;
watch(() => inboxState.filters, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetchEmails(1);
    }, 500);
}, { deep: true, immediate: true });
</script>

<template>
    <Head title="Inbox" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Inbox
                </h2>
                <div class="flex items-center gap-2">
                    <button
                        v-if="canComposeEmails"
                        @click="openComposeEmail"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-25 transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Compose Email
                    </button>
                    <button
                        v-if="canCreateCustomEmails"
                        @click="openCustomComposeEmail"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 disabled:opacity-25 transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Custom Emails
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 h-[calc(100vh-200px)]">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg h-full flex">
                    <!-- Advanced Filter Panel -->
                    <transition
                        enter-active-class="transition-all duration-300 ease-out"
                        leave-active-class="transition-all duration-300 ease-in"
                        enter-from-class="-translate-x-full opacity-0"
                        leave-to-class="-translate-x-full opacity-0"
                    >
                        <div v-if="showAdvancedFilters" class="w-64 p-4 border-r border-gray-200 overflow-y-auto transform">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Advanced Filters</h3>
                            <EmailFilters
                                :initial-filters="inboxState.filters"
                                @change="handleFilterChange"
                            />
                        </div>
                    </transition>

                    <div class="flex-1 overflow-y-auto">
                        <!-- New button-based filter UI -->
                        <div class="flex items-center justify-start p-6 space-x-4">
                            <button
                                v-for="option in filterOptions"
                                :key="option.type"
                                @click="handleFilterTypeChange(option.type)"
                                :class="{
                                    'px-4 py-2 rounded-md font-medium text-sm transition-colors flex items-center space-x-2': true,
                                    'text-white bg-indigo-600 hover:bg-indigo-700': inboxState.filters.type === option.type,
                                    'text-gray-700 bg-white border border-gray-300 hover:bg-gray-100': inboxState.filters.type !== option.type,
                                }"
                            >
                                <component :is="option.icon" class="h-5 w-5" />
                                <span>{{ option.label }}</span>
                                <span v-if="inboxState.counts[option.type] > 0" class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-white text-gray-800">{{ inboxState.counts[option.type] }}</span>
                            </button>

                            <button @click="showAdvancedFilters = !showAdvancedFilters" class="px-4 py-2 rounded-md font-medium text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 transition-colors flex items-center space-x-2">
                                <AdjustmentsHorizontalIcon class="h-5 w-5" />
                                <span class="hidden sm:inline">{{ showAdvancedFilters ? 'Hide Filters' : 'Show Filters' }}</span>
                                <ChevronRightIcon v-if="!showAdvancedFilters" class="h-4 w-4" />
                                <ChevronLeftIcon v-else class="h-4 w-4" />
                            </button>
                            <button @click="fetchEmails(1)" class="px-4 py-2 rounded-md font-medium text-sm text-gray-700 bg-white border border-gray-300 hover:bg-gray-100 transition-colors flex items-center space-x-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span class="hidden sm:inline">Refresh</span>
                            </button>
                        </div>

                        <!-- Category Filters -->
                        <EmailCategoryFilters
                            :selected-categories="inboxState.filters.categoryIds"
                            :filters="inboxState.filters"
                            @update:selected-categories="handleCategoryFiltersChange"
                        />

                        <div class="p-6">
                            <EmailList
                                :emails="inboxState.emails"
                                :loading="inboxState.loadingEmails"
                                :error="inboxState.emailError"
                                :pagination="inboxState.pagination"
                                @view-email="handleViewEmail"
                                @change-page="changePage"
                                @refresh="fetchEmails"
                                @show-notification="showNotification"
                            />
                        </div>
                    </div>

                    <RightSidebar
                        :show="inboxState.sidebar.show"
                        :title="sidebarTitle"
                        @close="inboxState.sidebar.show = false"
                        :loading="inboxState.sidebar.loading"
                    >
                        <template #content>
                            <div v-if="inboxState.sidebar.mode === 'view-email'">
                                <EmailDetailsContent
                                    :email="inboxState.sidebar.data"
                                    :can-approve-emails="inboxState.sidebar.data?.can_approve"
                                    @edit="handleEditEmail"
                                    @reject="handleRejectEmail"
                                    @deleted="handleSubmitted"
                                    @updated="handleSubmitted"
                                />
                            </div>
                            <div v-else-if="inboxState.sidebar.mode === 'compose'">
                                <ComposeEmailContent
                                    :project-id="inboxState.sidebar.data?.conversation?.project?.id"
                                    @submitted="handleSubmitted"
                                />
                            </div>
                            <div v-else-if="inboxState.sidebar.mode === 'edit' || inboxState.sidebar.mode === 'reject'">
                                <EmailActionContent
                                    :email="inboxState.sidebar.data"
                                    :mode="inboxState.sidebar.mode"
                                    @submitted="handleSubmitted"
                                />
                            </div>
                            <div v-else-if="inboxState.sidebar.mode === 'received-edit'">
                                <ReceivedEmailActionContent
                                    :email="inboxState.sidebar.data"
                                    @submitted="handleSubmitted"
                                    @error="() => console.log('Error from received-edit')"
                                />
                            </div>
                            <div v-else-if="inboxState.sidebar.mode === 'custom-edit'">
                                <CustomEmailApprovalContent
                                    :email="inboxState.sidebar.data"
                                    @submitted="handleSubmitted"
                                    @error="() => {}"
                                />
                            </div>
                            <div v-else-if="inboxState.sidebar.mode === 'custom-compose'">
                                <CustomComposeEmailContent
                                    :project-id="null"
                                    :user-project-role="{}"
                                    @submitted="() => { handleSubmitted(); }"
                                    @error="() => {}"
                                />
                            </div>
                        </template>
                    </RightSidebar>
                </div>
            </div>
        </div>
        <Notification :notification="notifications" @close="notifications = null" />
    </AuthenticatedLayout>
</template>
