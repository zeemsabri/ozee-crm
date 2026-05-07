<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';

const filters = reactive({
    fileable_types: [],
    project_ids: [],
    linked_status: null,
    parent_status: null,
    search: '',
    date_from: '',
    date_to: '',
    page: 1,
    per_page: 20,
});

const state = reactive({
    files: [],
    filterOptions: {
        fileable_types: [],
        projects: [],
        linked_statuses: [],
        parent_statuses: [],
    },
    pagination: {
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 20,
    },
    loading: false,
    selectedIds: new Set(),
});

const showDeleteConfirm = ref(false);
const deleteLoading = ref(false);

const selectedCount = computed(() => state.selectedIds.size);
const allSelected = computed(() => {
    return state.files.length > 0 && state.selectedIds.size === state.files.length;
});

const formatFileSize = (bytes) => {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIdx = 0;
    while (size >= 1024 && unitIdx < units.length - 1) {
        size /= 1024;
        unitIdx++;
    }
    return `${size.toFixed(2)} ${units[unitIdx]}`;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString();
};

const getParentStatusBadge = (status) => {
    const badges = {
        active: 'bg-emerald-50 text-emerald-700 border border-emerald-100',
        soft_deleted: 'bg-yellow-50 text-yellow-700 border border-yellow-100',
        missing: 'bg-red-50 text-red-700 border border-red-100',
    };
    const labels = {
        active: 'Active',
        soft_deleted: 'Soft Deleted',
        missing: 'Missing',
    };
    return {
        class: badges[status] || 'bg-gray-50 text-gray-700 border border-gray-100',
        label: labels[status] || status,
    };
};

const toggleSelectFile = (fileId) => {
    if (state.selectedIds.has(fileId)) {
        state.selectedIds.delete(fileId);
    } else {
        state.selectedIds.add(fileId);
    }
};

const toggleSelectAll = () => {
    if (allSelected.value) {
        state.selectedIds.clear();
    } else {
        state.files.forEach(file => {
            state.selectedIds.add(file.id);
        });
    }
};

const clearSelection = () => {
    state.selectedIds.clear();
};

const resetFilters = () => {
    filters.fileable_types = [];
    filters.project_ids = [];
    filters.linked_status = null;
    filters.parent_status = null;
    filters.search = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.page = 1;
};

const loadFiles = async () => {
    state.loading = true;
    try {
        const queryParams = new URLSearchParams();
        if (filters.fileable_types.length) {
            filters.fileable_types.forEach(t => queryParams.append('fileable_types[]', t));
        }
        if (filters.project_ids.length) {
            filters.project_ids.forEach(p => queryParams.append('project_ids[]', p));
        }
        if (filters.linked_status) {
            queryParams.append('linked_status', filters.linked_status);
        }
        if (filters.parent_status) {
            queryParams.append('parent_status', filters.parent_status);
        }
        if (filters.search) {
            queryParams.append('search', filters.search);
        }
        if (filters.date_from) {
            queryParams.append('date_from', filters.date_from);
        }
        if (filters.date_to) {
            queryParams.append('date_to', filters.date_to);
        }
        queryParams.append('page', filters.page);
        queryParams.append('per_page', filters.per_page);

        const { data } = await window.axios.get(`/api/admin/media-files/list?${queryParams.toString()}`);
        state.files = data.files.data || [];
        state.filterOptions = data.filters || {};
        state.pagination = {
            current_page: data.files.current_page || 1,
            last_page: data.files.last_page || 1,
            total: data.files.total || 0,
            per_page: data.files.per_page || 20,
        };
        state.selectedIds.clear();
    } catch (error) {
        console.error('Failed to load media files:', error);
    } finally {
        state.loading = false;
    }
};

const viewFile = async (file) => {
    if (!file.has_path) {
        alert('File path not available');
        return;
    }
    try {
        const { data } = await window.axios.get(`/api/admin/media-files/${file.id}/view-url`);
        if (data.url) {
            window.open(data.url, '_blank');
        }
    } catch (error) {
        console.error('Error viewing file:', error);
        alert('Unable to view file');
    }
};

const confirmDelete = () => {
    if (selectedCount.value === 0) return;
    showDeleteConfirm.value = true;
};

const deleteSelected = async () => {
    const ids = Array.from(state.selectedIds);
    deleteLoading.value = true;
    try {
        const { data } = await window.axios.post(`/api/admin/media-files/bulk-delete`, {
            ids,
        });
        // Show summary
        alert(`${data.deleted_count} file(s) deleted, ${data.failed_count} failed`);
        // Clear selection and reload
        state.selectedIds.clear();
        await loadFiles();
        showDeleteConfirm.value = false;
    } catch (error) {
        console.error('Error deleting files:', error);
        alert('Error deleting files');
    } finally {
        deleteLoading.value = false;
    }
};

// Watch filters for changes and trigger reload (except page which is handled separately)
watch(
    () => [
        filters.fileable_types,
        filters.project_ids,
        filters.linked_status,
        filters.parent_status,
        filters.search,
        filters.date_from,
        filters.date_to,
        filters.per_page,
    ],
    () => {
        filters.page = 1; // Reset to page 1 when filters change
        loadFiles();
    }
);

onMounted(() => {
    loadFiles();
});
</script>

<template>
    <Head title="Media Files" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Media Files</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                    <!-- Header -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Manage Media Files</h3>
                        <p class="text-sm text-gray-500">Browse and manage all uploaded files with multi-filter support.</p>
                    </div>

                    <!-- Filters -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6 border border-gray-200">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <InputLabel value="File Type" />
                                <MultiSelectDropdown
                                    v-model="filters.fileable_types"
                                    :options="state.filterOptions.fileable_types"
                                    :is-multi="true"
                                    placeholder="All types"
                                />
                            </div>

                            <div>
                                <InputLabel value="Project" />
                                <MultiSelectDropdown
                                    v-model="filters.project_ids"
                                    :options="state.filterOptions.projects"
                                    :is-multi="true"
                                    placeholder="All projects"
                                />
                            </div>

                            <div>
                                <InputLabel value="Linkage Status" />
                                <SelectDropdown
                                    v-model="filters.linked_status"
                                    :options="state.filterOptions.linked_statuses"
                                    placeholder="All statuses"
                                />
                            </div>

                            <div>
                                <InputLabel value="Parent Status" />
                                <SelectDropdown
                                    v-model="filters.parent_status"
                                    :options="state.filterOptions.parent_statuses"
                                    placeholder="All statuses"
                                />
                            </div>

                            <div>
                                <InputLabel value="Search (Filename)" />
                                <TextInput
                                    v-model="filters.search"
                                    type="text"
                                    placeholder="Search files..."
                                    class="w-full mt-1"
                                />
                            </div>

                            <div>
                                <InputLabel value="Date From" />
                                <TextInput
                                    v-model="filters.date_from"
                                    type="date"
                                    class="w-full mt-1"
                                />
                            </div>

                            <div>
                                <InputLabel value="Date To" />
                                <TextInput
                                    v-model="filters.date_to"
                                    type="date"
                                    class="w-full mt-1"
                                />
                            </div>

                            <div>
                                <InputLabel value="Per Page" />
                                <select v-model.number="filters.per_page" class="w-full mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 mt-4">
                            <SecondaryButton @click="resetFilters">Reset Filters</SecondaryButton>
                        </div>
                    </div>

                    <!-- Selection Bar -->
                    <div v-if="selectedCount > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 flex items-center justify-between">
                        <span class="text-sm text-blue-700">
                            <strong>{{ selectedCount }}</strong> file(s) selected
                        </span>
                        <div class="flex gap-2">
                            <SecondaryButton size="sm" @click="clearSelection">Clear Selection</SecondaryButton>
                            <DangerButton @click="confirmDelete" :disabled="deleteLoading">Delete Selected</DangerButton>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                        <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-gray-300" />
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Filename</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Size</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Source</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Project</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Parent Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                <tr v-for="file in state.files" :key="file.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <input type="checkbox" :checked="state.selectedIds.has(file.id)" @change="toggleSelectFile(file.id)" class="rounded border-gray-300" />
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900 truncate" :title="file.filename">{{ file.filename }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                            {{ file.mime_type || '—' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">{{ formatFileSize(file.file_size) }}</td>
                                    <td class="px-6 py-4 text-gray-700">{{ file.fileable_type }}</td>
                                    <td class="px-6 py-4">
                                        <div v-if="file.project_name" class="text-gray-900">
                                            {{ file.project_name }}
                                            <span v-if="file.project_trashed" class="text-xs text-gray-500 ml-1">(deleted)</span>
                                        </div>
                                        <span v-else class="text-gray-500 italic">Unlinked</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border"
                                            :class="getParentStatusBadge(file.parent_status).class"
                                        >
                                            {{ getParentStatusBadge(file.parent_status).label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">{{ formatDate(file.created_at) }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end space-x-2">
                                            <SecondaryButton size="sm" @click="viewFile(file)" :disabled="!file.has_path">View</SecondaryButton>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="state.files.length === 0 && !state.loading">
                                    <td colspan="9" class="px-6 py-8 text-center text-gray-500 italic">
                                        No media files found.
                                    </td>
                                </tr>

                                <tr v-if="state.loading">
                                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                                        Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-6">
                        <div class="text-sm text-gray-600">
                            Page <strong>{{ state.pagination.current_page }}</strong> of <strong>{{ state.pagination.last_page }}</strong>
                            ({{ state.pagination.total }} total files)
                        </div>
                        <div class="flex gap-2">
                            <SecondaryButton
                                @click="() => { filters.page = state.pagination.current_page - 1; loadFiles(); }"
                                :disabled="state.pagination.current_page <= 1 || state.loading"
                            >
                                Previous
                            </SecondaryButton>
                            <SecondaryButton
                                @click="() => { filters.page = state.pagination.current_page + 1; loadFiles(); }"
                                :disabled="state.pagination.current_page >= state.pagination.last_page || state.loading"
                            >
                                Next
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteConfirm" @close="showDeleteConfirm = false" maxWidth="md">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirm Delete</h3>
                <p class="text-gray-700 mb-4">
                    Are you sure you want to delete <strong>{{ selectedCount }}</strong> file(s)? This action cannot be undone.
                </p>
                <div class="flex justify-end gap-2">
                    <SecondaryButton @click="showDeleteConfirm = false" :disabled="deleteLoading">Cancel</SecondaryButton>
                    <DangerButton @click="deleteSelected" :disabled="deleteLoading">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
