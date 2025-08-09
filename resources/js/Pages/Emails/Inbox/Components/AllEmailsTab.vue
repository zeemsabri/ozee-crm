<script setup>
import { ref, onMounted, reactive, watch } from 'vue';
import axios from 'axios';
import EmailList from '@/Components/ProjectsEmails/EmailList.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    isActive: Boolean,
});

const emails = ref([]);
const loading = ref(true);
const error = ref('');
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0
});

const emit = defineEmits(['view-email', 'filters-changed']);

// Email filters
const filters = reactive({
    type: '',
    status: '',
    startDate: '',
    endDate: '',
    search: '',
    project_id: '',
    sender_id: '',
});

// Lists for dropdowns
const projects = ref([]);
const senders = ref([]);
const showStatusFilter = ref(true);

// Fetch projects and senders for filter dropdowns
const fetchFilterOptions = async () => {
    try {
        // Fetch projects - this endpoint already filters by user's permissions
        const projectsResponse = await axios.get('/api/projects-simplified');
        projects.value = projectsResponse.data.map(project => ({
            value: project.id,
            label: project.name
        }));

        // Fetch users (potential senders)
        const usersResponse = await axios.get('/api/users');
        senders.value = usersResponse.data.map(user => ({
            value: user.id,
            label: user.name
        }));
    } catch (err) {
        console.error('Error fetching filter options:', err);
    }
};

let searchDebounceTimer = null;

// Watch for changes in type filter to toggle status filter visibility
watch(() => filters.type, (newType) => {
    showStatusFilter.value = newType !== 'received';
});

// Watch for any changes in filters and emit event to parent
watch(filters, (newFilters) => {
    emit('filters-changed', { ...newFilters });
}, { deep: true });

// Watch for changes in project_id to update sender list
watch(() => filters.project_id, async (newProjectId) => {
    if (newProjectId) {
        try {
            // Fetch users for the selected project
            const response = await axios.get(`/api/projects/${newProjectId}/users`);
            senders.value = response.data.map(user => ({
                value: user.id,
                label: user.name
            }));
        } catch (err) {
            console.error('Error fetching project users:', err);
        }
    } else {
        // If no project selected, fetch all users
        try {
            const response = await axios.get('/api/users');
            senders.value = response.data.map(user => ({
                value: user.id,
                label: user.name
            }));
        } catch (err) {
            console.error('Error fetching users:', err);
        }
    }

    // Reset sender filter when project changes
    filters.sender_id = '';
});

const fetchEmails = async (page = 1) => {
    loading.value = true;
    error.value = '';

    try {
        // Build query parameters
        const params = new URLSearchParams();
        if (filters.type) params.append('type', filters.type);
        if (filters.status && showStatusFilter.value) params.append('status', filters.status);
        if (filters.startDate) params.append('start_date', filters.startDate);
        if (filters.endDate) params.append('end_date', filters.endDate);
        if (filters.search) params.append('search', filters.search);
        if (filters.project_id) params.append('project_id', filters.project_id);
        if (filters.sender_id) params.append('sender_id', filters.sender_id);

        // Add pagination parameters
        params.append('page', page);
        params.append('per_page', pagination.value.perPage);

        const queryString = params.toString();
        const url = `/api/inbox/all-emails${queryString ? `?${queryString}` : ''}`;

        const response = await axios.get(url);

        // Update emails and pagination data
        emails.value = response.data.data;
        pagination.value = {
            currentPage: response.data.current_page,
            lastPage: response.data.last_page,
            perPage: response.data.per_page,
            total: response.data.total
        };
    } catch (err) {
        console.error('Error fetching all emails:', err);
        error.value = 'Failed to load emails. Please try again.';
    } finally {
        loading.value = false;
    }
};

const applyFilters = () => {
    // Reset to page 1 when applying filters
    pagination.value.currentPage = 1;
    fetchEmails(1);
};

const resetFilters = () => {
    filters.type = '';
    filters.status = '';
    filters.startDate = '';
    filters.endDate = '';
    filters.search = '';
    filters.project_id = '';
    filters.sender_id = '';
    // Reset status filter visibility
    showStatusFilter.value = true;
    // Reset to page 1 when resetting filters
    pagination.value.currentPage = 1;
    fetchEmails(1);
};

const changePage = (page) => {
    fetchEmails(page);
};

const debounceSearch = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        applyFilters();
    }, 500);
};

const handleViewEmail = (email) => {
    emit('view-email', email);

    // Mark the email as read in the UI
    const index = emails.value.findIndex(e => e.id === email.id);
    if (index !== -1) {
        emails.value[index].is_read = true;
    }
};

// Watch for the isActive prop to trigger the fetch when the tab becomes active
watch(() => props.isActive, (newVal) => {
    if (newVal) {
        fetchEmails();
    }
}, { immediate: true }); // 'immediate' ensures the fetch runs on initial load if the tab is active

// Expose methods to parent component
defineExpose({
    refresh: fetchEmails,
});

onMounted(() => {
    fetchFilterOptions();
});
</script>

<template>
    <div>
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-xl font-medium text-gray-900">All Emails</h3>
            <button
                @click="fetchEmails"
                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </button>
        </div>
        <!-- Filters -->
        <div class="mb-4 bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <InputLabel for="typeFilter" value="Type" />
                    <SelectDropdown
                        id="typeFilter"
                        v-model="filters.type"
                        :options="[
                            { value: '', label: 'All Types' },
                            { value: 'sent', label: 'Sent' },
                            { value: 'received', label: 'Received' }
                        ]"
                        placeholder="All Types"
                        @change="applyFilters"
                    />
                </div>

                <div v-if="showStatusFilter">
                    <InputLabel for="statusFilter" value="Status" />
                    <SelectDropdown
                        id="statusFilter"
                        v-model="filters.status"
                        :options="[
                            { value: '', label: 'All Statuses' },
                            { value: 'approved', label: 'Approved' },
                            { value: 'sent', label: 'Sent' }
                        ]"
                        placeholder="All Statuses"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <InputLabel for="projectFilter" value="Project" />
                    <SelectDropdown
                        id="projectFilter"
                        v-model="filters.project_id"
                        :options="projects"
                        placeholder="All Projects"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <InputLabel for="senderFilter" value="Sender" />
                    <SelectDropdown
                        id="senderFilter"
                        v-model="filters.sender_id"
                        :options="senders"
                        placeholder="All Senders"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <InputLabel for="startDate" value="From Date" />
                    <TextInput type="date" id="startDate" v-model="filters.startDate" class="mt-1 block w-full" @change="applyFilters" />
                </div>

                <div>
                    <InputLabel for="endDate" value="To Date" />
                    <TextInput type="date" id="endDate" v-model="filters.endDate" class="mt-1 block w-full" @change="applyFilters" />
                </div>

                <div>
                    <InputLabel for="searchFilter" value="Search Content" />
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <TextInput type="text" id="searchFilter" v-model="filters.search" class="block w-full pr-10" placeholder="Search in email content..." @input="debounceSearch" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 flex justify-end">
                <button type="button" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" @click="resetFilters">
                    Reset Filters
                </button>
            </div>
        </div>

        <div v-if="emails.length === 0 && !loading" class="text-center py-8 text-gray-500">
            <p>No emails found matching your criteria.</p>
        </div>

        <EmailList
            :emails="emails"
            :loading="loading"
            :error="error"
            @view="handleViewEmail"
        />

        <!-- Pagination -->
        <div v-if="!loading && emails.length > 0" class="mt-4 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <!-- Previous Page Button -->
                <button
                    @click="changePage(pagination.currentPage - 1)"
                    :disabled="pagination.currentPage === 1"
                    :class="[
                        pagination.currentPage === 1 ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-50',
                        'relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500'
                    ]"
                >
                    <span class="sr-only">Previous</span>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </button>

                <!-- Page Numbers -->
                <template v-for="page in pagination.lastPage" :key="page">
                    <button
                        v-if="page === 1 || page === pagination.lastPage || (page >= pagination.currentPage - 1 && page <= pagination.currentPage + 1)"
                        @click="changePage(page)"
                        :class="[
                            page === pagination.currentPage
                                ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                                : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50',
                            'relative inline-flex items-center px-4 py-2 border text-sm font-medium'
                        ]"
                    >
                        {{ page }}
                    </button>
                    <span
                        v-else-if="page === 2 || page === pagination.lastPage - 1"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700"
                    >
                        ...
                    </span>
                </template>

                <!-- Next Page Button -->
                <button
                    @click="changePage(pagination.currentPage + 1)"
                    :disabled="pagination.currentPage === pagination.lastPage"
                    :class="[
                        pagination.currentPage === pagination.lastPage ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-50',
                        'relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500'
                    ]"
                >
                    <span class="sr-only">Next</span>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </button>
            </nav>
        </div>

        <!-- Pagination Info -->
        <div v-if="!loading && emails.length > 0" class="mt-2 text-sm text-gray-500 text-center">
            Showing {{ (pagination.currentPage - 1) * pagination.perPage + 1 }} to
            {{ Math.min(pagination.currentPage * pagination.perPage, pagination.total) }}
            of {{ pagination.total }} emails
        </div>
    </div>
</template>
