<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, reactive } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue'; // Only for delete confirmation
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue'; // For filters
import { usePermissions } from '@/Directives/permissions';
import { success, error } from '@/Utils/notification';

// Access user from permissions utility (if needed for display, otherwise remove)
// const authUser = useAuthUser();

// Reactive state for main project list and filters
const projects = ref([]);
const loading = ref(true);
const generalError = ref('');
const activeTab = ref('active'); // 'active' or 'archived'

// Filter & Search states
const searchQuery = ref('');
const filterStatus = ref('');
const filterClient = ref('');
const filterUser = ref('');
const filterSource = ref('');

// Fixed Status options
const filterStatusOptions = ref([
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'paid', label: 'Paid' },
    { value: 'on-hold', label: 'On Hold' },
    { value: 'archived', label: 'Archived' },
]);

// Dynamic options for other filters, derived from fetched projects
const filterClientOptions = computed(() => {
    const uniqueClients = new Map();
    projects.value.forEach(project => {
        project.clients?.forEach(client => {
            if (!uniqueClients.has(client.id)) {
                uniqueClients.set(client.id, { value: client.id, label: client.name });
            }
        });
    });
    return [{ value: '', label: 'All Clients' }, ...Array.from(uniqueClients.values())];
});

const filterUserOptions = computed(() => {
    const uniqueUsers = new Map();
    projects.value.forEach(project => {
        project.users?.forEach(user => {
            if (!uniqueUsers.has(user.id)) {
                uniqueUsers.set(user.id, { value: user.id, label: user.name });
            }
        });
    });
    return [{ value: '', label: 'All Users' }, ...Array.from(uniqueUsers.values())];
});

const filterSourceOptions = computed(() => {
    const uniqueSources = new Set();
    projects.value.forEach(project => {
        if (project.source && !uniqueSources.has(project.source)) {
            uniqueSources.add(project.source);
        }
    });
    return [{ value: '', label: 'All Sources' }, ...Array.from(uniqueSources).map(s => ({ value: s, label: s }))];
});


// Modals state (only for delete confirmation)
const showDeleteModal = ref(false);
const projectToDelete = ref(null); // Project object to be deleted

// Set up permission checking functions for global permissions
const { canDo, canView } = usePermissions();

// Global permission checks
const canCreateProjects = canDo('create_projects');
const hasAccessToProjects = computed(() => canView('projects').value);
const canManageProjectsGlobal = canDo('manage_projects'); // For edit/delete actions

// --- Fetch Initial Data ---
const fetchInitialData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        // Fetch projects including trashed (archived) projects
        const projectsResponse = await window.axios.get('/api/projects?with_trashed=true');
        projects.value = projectsResponse.data;

    } catch (error) {
        generalError.value = 'Failed to load projects.';
        console.error('Error fetching initial data:', error);
        if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            generalError.value = 'You are not authorized to view this content or your session expired. Please log in.';
        }
    } finally {
        loading.value = false;
    }
};

// --- Computed Filtered Projects ---
const filteredProjects = computed(() => {
    let filtered = projects.value;

    // First filter by active/archived tab
    if (activeTab.value === 'active') {
        filtered = filtered.filter(project => project.deleted_at === null);
    } else if (activeTab.value === 'archived') {
        filtered = filtered.filter(project => project.deleted_at !== null);
    }

    // Apply search query
    if (searchQuery.value) {
        const lowerCaseQuery = searchQuery.value.toLowerCase();
        filtered = filtered.filter(project =>
            project.name.toLowerCase().includes(lowerCaseQuery) ||
            (project.description && project.description.toLowerCase().includes(lowerCaseQuery)) ||
            (project.status && project.status.toLowerCase().includes(lowerCaseQuery.replace(/_|-/g, ' '))) || // Handle 'on_hold' or 'on-hold'
            (project.clients && project.clients.some(client => client.name.toLowerCase().includes(lowerCaseQuery))) ||
            (project.users && project.users.some(user => user.name.toLowerCase().includes(lowerCaseQuery))) ||
            (project.source && project.source.toLowerCase().includes(lowerCaseQuery))
        );
    }

    // Apply status filter
    if (filterStatus.value) {
        filtered = filtered.filter(project => project.status === filterStatus.value);
    }

    // Apply client filter
    if (filterClient.value) {
        filtered = filtered.filter(project => project.clients && project.clients.some(client => client.id === filterClient.value));
    }

    // Apply user filter
    if (filterUser.value) {
        filtered = filtered.filter(project => project.users && project.users.some(user => user.id === filterUser.value));
    }

    // Apply source filter
    if (filterSource.value) {
        filtered = filtered.filter(project => project.source === filterSource.value);
    }

    return filtered;
});

// --- Project Statistics ---
const projectStats = computed(() => {
    const stats = {
        total: projects.value.length,
        active: 0,
        completed: 0,
        onHold: 0,
        paid: 0,
        archived: 0,
        // requiringAttention: 0, // Add if you have data for this
    };

    projects.value.forEach(project => {
        if (project.status === 'active') stats.active++;
        if (project.status === 'completed') stats.completed++;
        if (project.status === 'on-hold' || project.status === 'on_hold') stats.onHold++;
        if (project.status === 'paid') stats.paid++;
        if (project.status === 'archived') stats.archived++;
    });

    return stats;
});

// Helper: Returns Tailwind CSS classes for project status badges
const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'completed': return 'bg-blue-100 text-blue-800';
        case 'paid': return 'bg-purple-100 text-purple-800';
        case 'on-hold':
        case 'on_hold': return 'bg-red-100 text-red-800';
        case 'archived': return 'bg-gray-100 text-gray-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

// --- Archive Project ---
const archiveProject = async (project) => {
    if (!canDo('delete_projects').value) {
        error('You do not have permission to archive this project.');
        return;
    }

    try {
        await window.axios.post(`/api/projects/${project.id}/archive`);
        // Refresh the projects list
        await fetchInitialData();
        success('Project archived successfully!');
    } catch (err) {
        generalError.value = err.response?.data?.message || 'Failed to archive project.';
        console.error('Error archiving project:', err);
        error('Failed to archive project.');
    }
};

// --- Restore Project ---
const restoreProject = async (project) => {
    if (!canDo('delete_projects').value) {
        error('You do not have permission to restore this project.');
        return;
    }

    try {
        await window.axios.post(`/api/projects/${project.id}/restore`);
        // Refresh the projects list
        await fetchInitialData();
        success('Project restored successfully!');
    } catch (err) {
        generalError.value = err.response?.data?.message || 'Failed to restore project.';
        console.error('Error restoring project:', err);
        error('Failed to restore project.');
    }
};

// --- Delete Project ---
const confirmProjectDeletion = (project) => {
    if (!canDo('delete_projects').value) {
        error('You do not have permission to delete this project.');
        return;
    }
    projectToDelete.value = project;
    showDeleteModal.value = true;
};

const deleteProject = async () => {
    generalError.value = '';
    const projectId = projectToDelete.value?.id;
    if (!projectId) { generalError.value = 'Invalid project.'; return; }
    if (!canDo('delete_projects').value) { generalError.value = 'You do not have permission to delete this project.'; return; }

    try {
        await window.axios.delete(`/api/projects/${projectId}`);
        projects.value = projects.value.filter(p => p.id !== projectId);
        showDeleteModal.value = false;
        projectToDelete.value = null;
        success('Project deleted successfully!');
    } catch (err) {
        generalError.value = err.response?.data?.message || 'Failed to delete project.';
        console.error('Error deleting project:', err);
    }
};

// --- Clear Filters ---
const clearFilters = () => {
    searchQuery.value = '';
    filterStatus.value = '';
    filterClient.value = '';
    filterUser.value = '';
    filterSource.value = '';
};

// --- Lifecycle Hook ---
onMounted(() => {
    if (!hasAccessToProjects.value) {
        window.location.href = route('dashboard'); // Assuming 'route' helper is available
        return;
    }
    fetchInitialData();
});
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between py-4">
                <h2 class="font-bold text-3xl text-gray-800 leading-tight mb-2 sm:mb-0">
                    Project Management
                </h2>
                <div v-if="canCreateProjects">
                    <Link :href="route('projects.create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150 shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create New Project
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-12xl mx-auto sm:px-6 lg:px-12">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div v-if="loading" class="text-center py-4 text-gray-500 text-lg">
                        <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Loading projects and data...
                    </div>
                    <div v-else-if="generalError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4" role="alert">
                        <span class="block sm:inline">{{ generalError }}</span>
                    </div>
                    <div v-else>
                        <!-- Tabs for Active/Archived Projects -->
                        <div class="mb-6 border-b border-gray-200">
                            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                                <li class="mr-2">
                                    <a href="#"
                                       @click.prevent="activeTab = 'active'"
                                       :class="[
                                           'inline-block p-4 rounded-t-lg border-b-2',
                                           activeTab === 'active'
                                               ? 'text-blue-600 border-blue-600 active'
                                               : 'border-transparent hover:text-gray-600 hover:border-gray-300'
                                       ]">
                                        Active Projects
                                    </a>
                                </li>
                                <li class="mr-2">
                                    <a href="#"
                                       @click.prevent="activeTab = 'archived'"
                                       :class="[
                                           'inline-block p-4 rounded-t-lg border-b-2',
                                           activeTab === 'archived'
                                               ? 'text-blue-600 border-blue-600 active'
                                               : 'border-transparent hover:text-gray-600 hover:border-gray-300'
                                       ]">
                                        Archived Projects
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Project Statistics Cards -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div class="bg-blue-50 p-6 rounded-lg shadow-sm border border-blue-200 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-blue-700">Total Projects</p>
                                    <p class="text-3xl font-bold text-blue-900 mt-1">{{ projectStats.total }}</p>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-blue-400 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m-4 10h8a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="bg-green-50 p-6 rounded-lg shadow-sm border border-green-200 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-green-700">Active Projects</p>
                                    <p class="text-3xl font-bold text-green-900 mt-1">{{ projectStats.active }}</p>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-green-400 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="bg-yellow-50 p-6 rounded-lg shadow-sm border border-yellow-200 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-yellow-700">On-Hold Projects</p>
                                    <p class="text-3xl font-bold text-yellow-900 mt-1">{{ projectStats.onHold }}</p>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-yellow-400 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="bg-purple-50 p-6 rounded-lg shadow-sm border border-purple-200 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-purple-700">Completed/Paid</p>
                                    <p class="text-3xl font-bold text-purple-900 mt-1">{{ projectStats.completed + projectStats.paid }}</p>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-purple-400 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Search and Filters -->
                        <div class="mb-8 p-4 bg-gray-50 rounded-lg shadow-inner border border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-700 mb-4">Filter Projects</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <InputLabel for="search-query" value="Search" class="mb-1" />
                                    <TextInput
                                        id="search-query"
                                        type="text"
                                        v-model="searchQuery"
                                        placeholder="Search by name, client, user..."
                                        class="w-full"
                                    />
                                </div>
                                <div>
                                    <InputLabel for="filter-status" value="Status" class="mb-1" />
                                    <SelectDropdown
                                        id="filter-status"
                                        v-model="filterStatus"
                                        :options="filterStatusOptions"
                                        value-key="value"
                                        label-key="label"
                                        placeholder="All Statuses"
                                        class="w-full"
                                    />
                                </div>
                                <div>
                                    <InputLabel for="filter-client" value="Client" class="mb-1" />
                                    <SelectDropdown
                                        id="filter-client"
                                        v-model="filterClient"
                                        :options="filterClientOptions"
                                        value-key="value"
                                        label-key="label"
                                        placeholder="All Clients"
                                        class="w-full"
                                    />
                                </div>
                                <div>
                                    <InputLabel for="filter-user" value="Assigned User" class="mb-1" />
                                    <SelectDropdown
                                        id="filter-user"
                                        v-model="filterUser"
                                        :options="filterUserOptions"
                                        value-key="value"
                                        label-key="label"
                                        placeholder="All Users"
                                        class="w-full"
                                    />
                                </div>
                                <div>
                                    <InputLabel for="filter-source" value="Source" class="mb-1" />
                                    <SelectDropdown
                                        id="filter-source"
                                        v-model="filterSource"
                                        :options="filterSourceOptions"
                                        value-key="value"
                                        label-key="label"
                                        placeholder="All Sources"
                                        class="w-full"
                                    />
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <SecondaryButton @click="clearFilters" class="px-4 py-2">Clear Filters</SecondaryButton>
                            </div>
                        </div>

                        <!-- Project List Table -->
                        <div v-if="filteredProjects.length === 0" class="p-6 bg-gray-50 rounded-lg text-gray-600 text-center border border-gray-200 shadow-sm">
                            No projects found matching your criteria.
                        </div>
                        <div v-else class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clients</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Users</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="project in filteredProjects" :key="project.id">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ project.name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <span v-if="project.clients && project.clients.length">
                                            {{ project.clients.map(client => client.name).join(', ') }}
                                        </span>
                                        <span v-else class="text-gray-400">None</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="['px-2 inline-flex text-xs leading-5 font-semibold rounded-full capitalize', getStatusBadgeClass(project.status)]">
                                            {{ project.status.replace('_', ' ') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <span v-if="project.users && project.users.length">
                                            {{ project.users.map(user => user.name).join(', ') }}
                                        </span>
                                        <span v-else class="text-gray-400">None</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <Link :href="route('projects.edit', project.id)" v-permission="'manage_projects'" class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring active:bg-indigo-700 transition ease-in-out duration-150">Edit</Link>

                                            <!-- Archive button (only shown for non-archived projects) -->
                                            <button
                                                v-if="activeTab === 'active'"
                                                v-permission="'delete_projects'"
                                                @click="archiveProject(project)"
                                                class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring active:bg-gray-700 transition ease-in-out duration-150">
                                                Archive
                                            </button>

                                            <!-- Restore button (only shown for archived projects) -->
                                            <button
                                                v-if="activeTab === 'archived'"
                                                v-permission="'delete_projects'"
                                                @click="restoreProject(project)"
                                                class="inline-flex items-center px-3 py-1 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:border-green-700 focus:ring active:bg-green-700 transition ease-in-out duration-150">
                                                Restore
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal (Only modal remaining) -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this project?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    This action cannot be undone. All associated conversations and emails will also be deleted.
                </p>
                <div v-if="projectToDelete" class="mt-4 text-gray-800">
                    <strong>Project:</strong> {{ projectToDelete.name }}
                    <span v-if="projectToDelete.clients && projectToDelete.clients.length">
                        (Clients: {{ projectToDelete.clients.map(client => client.name).join(', ') }})
                    </span>
                </div>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteProject">Delete Project</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
