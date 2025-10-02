<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { success, error } from '@/Utils/notification.js';
// It's good practice to import icons if you use an icon library
// For example: import { FunnelIcon, Squares2X2Icon, Bars3Icon, EllipsisVerticalIcon } from '@heroicons/vue/24/outline';

// --- STATE MANAGEMENT ---

const resources = ref([]);
const isLoading = ref(true); // Start as true on initial load
const fetchError = ref(null);

// View mode state
const viewMode = ref('grid'); // 'grid' or 'list'

// Filters state
const filters = ref({
    search: '',
    types: [], // Array of selected type values
});

// Pagination state
const page = ref(1);
const perPage = ref(12); // Grid view works better with multiples of 2, 3, 4
const total = ref(0);

// Options and projects for selection
const typeOptions = ref([]);
const projects = ref([]);

// --- Modal States ---
const selectedResource = ref(null);
const showCopyModal = ref(false);
const selectedProjectId = ref(null);

const showAddModal = ref(false);
const newResource = ref({
    title: '',
    url: '',
    type: null,
    tags: '', // Handled as a comma-separated string in the UI
});


// --- API CALLS ---

async function fetchResources() {
    isLoading.value = true;
    fetchError.value = null;
    try {
        // Pass filters to the API
        const params = {
            visible_to_team: true,
            q: filters.value.search || undefined,
            types: filters.value.types.length ? filters.value.types : undefined,
            per_page: perPage.value,
            page: page.value
        };
        const { data } = await window.axios.get('/api/shareable-resources', { params });
        resources.value = data.data || [];
        total.value = data.total || 0;
    } catch (e) {
        console.error('Failed to load team resources', e);
        fetchError.value = 'Failed to load team resources';
        error(fetchError.value);
    } finally {
        isLoading.value = false;
    }
}

async function fetchTypeOptions() {
    try {
        const { data } = await window.axios.get('/api/options/shareable_resource_types');
        typeOptions.value = Array.isArray(data) ? data : [];
    } catch (_) {
        typeOptions.value = [];
    }
}

async function fetchProjects() {
    try {
        const { data } = await window.axios.get('/api/projects-simplified');
        projects.value = Array.isArray(data) ? data : [];
    } catch (_) {
        projects.value = [];
    }
}

// --- LIFECYCLE & WATCHERS ---

onMounted(() => {
    fetchTypeOptions();
    fetchProjects();
    fetchResources();
});

// Watch the filters object deeply for changes
watch(filters, fetchResources, { deep: true });
watch([page, perPage], fetchResources);

// --- HELPERS ---

function typeAllows(resourceType, action) {
    const opt = typeOptions.value.find(o => o.value === resourceType);
    return opt?.allow?.includes(action) ?? false;
}

// --- MODAL LOGIC ---

function openCopyModal(resource) {
    selectedResource.value = resource;
    selectedProjectId.value = null;
    showCopyModal.value = true;
}

async function confirmCopy() {
    if (!selectedResource.value || !selectedProjectId.value) {
        return error('Please select a project');
    }
    try {
        await window.axios.post(`/api/shareable-resources/${selectedResource.value.id}/copy-to-project`, {
            project_id: selectedProjectId.value,
        });
        success('Resource copied to project successfully.');
        showCopyModal.value = false;
        selectedResource.value = null;
    } catch (e) {
        console.error(e);
        error(e?.response?.data?.message || 'Failed to copy resource.');
    }
}

function openAddModal() {
    newResource.value = {
        title: '',
        url: '',
        // Default to the first available type, if any
        type: typeOptions.value.length ? typeOptions.value[0].value : null,
        tags: '',
    };
    showAddModal.value = true;
}

async function confirmAddResource() {
    // Basic validation
    if (!newResource.value.title || !newResource.value.url || !newResource.value.type) {
        return error('Please fill in all required fields: Title, URL, and Type.');
    }

    try {
        const payload = {
            title: newResource.value.title,
            url: newResource.value.url,
            type: newResource.value.type,
            // API likely expects an array of strings for tags.
            // We convert the comma-separated string from the input into an array.
            tags: newResource.value.tags.split(',').map(tag => tag.trim()).filter(Boolean),
        };

        // The endpoint is assumed to be POST /api/shareable-resources
        await window.axios.post('/api/shareable-resources', payload);

        success('Resource added successfully!');
        showAddModal.value = false;
        fetchResources(); // Refresh the list to show the new resource
    } catch (e) {
        console.error('Failed to add resource', e);
        error(e?.response?.data?.message || 'An error occurred while adding the resource.');
    }
}


function resetFilters() {
    filters.value.search = '';
    filters.value.types = [];
    // fetchResources will be triggered by the watcher
}

const hasActiveFilters = computed(() => {
    return filters.value.search !== '' || filters.value.types.length > 0;
});

</script>

<template>
    <AuthenticatedLayout>
        <div class="p-4 sm:p-6 lg:p-8">
            <!-- Main Header -->
            <header class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Team Resources</h1>
                    <p class="mt-1 text-sm text-gray-500">Browse, search, and manage shared resources for your team.</p>
                </div>
                <div class="mt-4 sm:mt-0 flex items-center space-x-2">
                    <!-- View Switcher -->
                    <div class="inline-flex rounded-md shadow-sm">
                        <button @click="viewMode = 'grid'" :class="['px-3 py-2 rounded-l-md border border-gray-300', viewMode === 'grid' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">
                            <!-- Grid Icon (SVG) -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM13 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z" /></svg>
                        </button>
                        <button @click="viewMode = 'list'" :class="['px-3 py-2 -ml-px rounded-r-md border border-gray-300', viewMode === 'list' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50']">
                            <!-- List Icon (SVG) -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                        </button>
                    </div>
                </div>
            </header>

            <div class="flex gap-8">
                <!-- Filters Sidebar -->
                <aside class="w-64 hidden lg:block">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Filter Options</h2>
                    <div class="space-y-6">
                        <!-- Search Filter -->
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                            <div class="mt-1 relative">
                                <input v-model="filters.search" id="search" type="text" placeholder="Search by title, url..." class="w-full border-gray-300 rounded-md shadow-sm pl-10" />
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" /></svg>
                                </div>
                            </div>
                        </div>
                        <!-- Type Filter -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-700">Type</h3>
                            <div class="mt-2 space-y-2">
                                <div v-for="opt in typeOptions" :key="opt.value" class="flex items-center">
                                    <input :id="`type-${opt.value}`" :value="opt.value" v-model="filters.types" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                    <label :for="`type-${opt.value}`" class="ml-3 text-sm text-gray-600">{{ opt.label }}</label>
                                </div>
                            </div>
                        </div>
                        <!-- Clear Filters Button -->
                        <div v-if="hasActiveFilters">
                            <button @click="resetFilters" class="w-full text-sm text-indigo-600 hover:text-indigo-800">Clear all filters</button>
                        </div>
                    </div>
                </aside>

                <!-- Main Content Area -->
                <main class="flex-1">
                    <div v-if="isLoading">
                        <!-- Skeleton Loader -->
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                            <div v-for="i in 6" :key="i" class="bg-white p-4 rounded-lg shadow animate-pulse">
                                <div class="h-6 bg-gray-200 rounded w-3/4 mb-4"></div>
                                <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                                <div class="flex flex-wrap gap-2">
                                    <div class="h-5 bg-gray-200 rounded-full w-16"></div>
                                    <div class="h-5 bg-gray-200 rounded-full w-20"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="fetchError" class="text-center py-12">
                        <p class="text-red-600 font-semibold">{{ fetchError }}</p>
                        <p class="text-gray-500 mt-2">Please try refreshing the page.</p>
                    </div>
                    <div v-else-if="!resources.length" class="text-center py-20 border-2 border-dashed rounded-lg">
                        <h3 class="text-xl font-semibold text-gray-800">No Resources Found</h3>
                        <p class="mt-2 text-gray-500">Try adjusting your filters or add a new resource.</p>
                    </div>

                    <div v-else>
                        <!-- Grid View -->
                        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                            <div v-for="r in resources" :key="r.id" class="bg-white rounded-lg shadow border border-gray-200 hover:shadow-lg transition-shadow duration-300 flex flex-col">
                                <div class="p-5 flex-grow">
                                    <h4 class="font-bold text-lg text-gray-800 truncate">{{ r.title }}</h4>
                                    <a :href="r.url" target="_blank" rel="noopener" class="text-sm text-indigo-600 hover:underline truncate block mt-1">{{ r.url }}</a>
                                    <p class="text-xs text-gray-500 uppercase mt-3 mb-2 font-semibold">{{ r.type }}</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="tag in (r.tags || [])" :key="tag.id" class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-800">{{ tag.name }}</span>
                                        <span v-if="!r.tags || !r.tags.length" class="text-gray-400 text-sm italic">No tags</span>
                                    </div>
                                </div>
                                <div class="border-t p-3 bg-gray-50 flex justify-end">
                                    <button v-if="typeAllows(r.type, 'copy')" @click="openCopyModal(r)" class="px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-md font-semibold">
                                        Copy to Project
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- List View -->
                        <div v-if="viewMode === 'list'" class="overflow-x-auto rounded-lg border">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tags</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="r in resources" :key="r.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ r.title }}</td>
                                    <td class="px-6 py-4 text-gray-500">{{ r.type }}</td>
                                    <td class="px-6 py-4 text-indigo-600"><a :href="r.url" target="_blank" rel="noopener" class="hover:underline">Visit Link &rarr;</a></td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            <span v-for="tag in (r.tags || [])" :key="tag.id" class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-800">{{ tag.name }}</span>
                                            <span v-if="!r.tags || !r.tags.length" class="text-gray-400 text-sm italic">No tags</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button v-if="typeAllows(r.type, 'copy')" @click="openCopyModal(r)" class="px-3 py-1.5 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-md font-semibold">Copy</button>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Redesigned Pagination -->
                        <div class="flex items-center justify-between mt-6">
                            <div class="text-sm text-gray-600">
                                Showing <span class="font-medium">{{ resources.length }}</span> of <span class="font-medium">{{ total }}</span> results
                            </div>
                            <div class="space-x-2">
                                <button class="px-3 py-1.5 border rounded-md text-sm font-medium bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="page<=1" @click="page = Math.max(1, page-1)">Previous</button>
                                <button class="px-3 py-1.5 border rounded-md text-sm font-medium bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="resources.length < perPage" @click="page = page+1">Next</button>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <!-- Redesigned Copy Modal -->
        <div v-if="showCopyModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" @click.self="showCopyModal=false">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Copy to Project</h3>
                <p class="text-sm text-gray-500 mb-4">Select a project to copy "{{ selectedResource?.title }}" to.</p>
                <div>
                    <label for="project-select" class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <select v-model="selectedProjectId" id="project-select" class="border-gray-300 rounded-md w-full p-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option :value="null" disabled>-- Please select a project --</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium hover:bg-gray-50" @click="showCopyModal=false">Cancel</button>
                    <button class="px-4 py-2 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700 disabled:opacity-50" @click="confirmCopy" :disabled="!selectedProjectId">Confirm Copy</button>
                </div>
            </div>
        </div>

        <!-- Add Resource Modal -->
        <div v-if="showAddModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4" @click.self="showAddModal=false">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
                <h3 class="text-xl font-semibold text-gray-900 mb-4">Add New Resource</h3>
                <form @submit.prevent="confirmAddResource">
                    <div class="space-y-4">
                        <div>
                            <label for="add-title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input v-model="newResource.title" id="add-title" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label for="add-url" class="block text-sm font-medium text-gray-700">URL</label>
                            <input v-model="newResource.url" id="add-url" type="url" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label for="add-type" class="block text-sm font-medium text-gray-700">Type</label>
                            <select v-model="newResource.type" id="add-type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
                                <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="add-tags" class="block text-sm font-medium text-gray-700">Tags</label>
                            <input v-model="newResource.tags" id="add-tags" type="text" placeholder="e.g. design, inspiration, vue" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">Separate tags with a comma.</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium hover:bg-gray-50" @click="showAddModal=false">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700">Save Resource</button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
