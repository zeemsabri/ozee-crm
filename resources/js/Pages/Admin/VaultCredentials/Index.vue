<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import { usePermissions } from '@/Directives/permissions';
import { success, error, confirmPrompt } from '@/Utils/notification';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import MultiSelectDropdown from '@/Components/MultiSelectDropdown.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import { 
    KeyIcon,
    CalendarIcon,
    UserIcon,
    FolderIcon,
    ShieldCheckIcon,
    EyeIcon,
    EyeSlashIcon,
    ArrowTopRightOnSquareIcon,
    UsersIcon,
    LockClosedIcon,
    ClipboardIcon,
    BuildingOfficeIcon
} from '@heroicons/vue/24/outline';

const { canDo } = usePermissions();
const canEditCredential = computed(() => canDo('edit_credential').value);

const credentials = ref([]);
const projects = ref([]);
const clients = ref([]);
const loading = ref(true);
const stats = ref(null);

// Filters
const filterSearch = ref('');
const filterProjectId = ref('');
const filterClientId = ref('');
const filterSource = ref('');

const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

// Actions and Sidebar
const showSidebar = ref(false);
const selectedCredential = ref(null);
const loadingLogs = ref(false);
const credentialLogs = ref([]);

// Unlock State
const unlockPinByCredential = ref({});
const unlockedByCredential = ref({});
const unlockingByCredential = ref({});

// Share State
const availableUsers = ref([]);
const sharedUsers = ref([]);
const selectedShareUserIds = ref([]);
const shareLoading = ref(false);
const shareSaving = ref(false);

// Edit State
const editingCredentialId = ref(null);
const editForm = ref({
    label: '',
    username: '',
    password: '',
    pin: '',
    is_visible_to_client: false,
    expiry_days: 0,
});

const openCredentialSidebar = async (credential) => {
    selectedCredential.value = credential;
    showSidebar.value = true;
    await fetchLogs(credential.id);
    await openSharePanel(credential.id);
};

const fetchCredentials = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            search: filterSearch.value,
            project_id: filterProjectId.value,
            client_id: filterClientId.value,
            source: filterSource.value,
        };
        const { data } = await axios.get('/api/admin/vault-credentials', { params });
        credentials.value = data.data;
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
        };
        if (selectedCredential.value) {
            const updated = data.data.find(c => c.id === selectedCredential.value.id);
            if (updated) {
                selectedCredential.value = updated;
            }
        }
    } catch (err) {
        error('Failed to load credentials.');
    } finally {
        loading.value = false;
    }
};

const fetchStats = async () => {
    try {
        const params = {
            search: filterSearch.value,
            project_id: filterProjectId.value,
            client_id: filterClientId.value,
        };
        const { data } = await axios.get('/api/admin/vault-credentials/stats', { params });
        stats.value = data;
    } catch (err) {
        console.error('Failed to fetch stats', err);
    }
};

const fetchFilterOptions = async () => {
    try {
        const [projectsRes, clientsRes] = await Promise.all([
            axios.get('/api/projects-simplified'),
            axios.get('/api/clients', { params: { per_page: 500 } })
        ]);
        projects.value = (projectsRes.data || []).map(p => ({ id: p.id, name: p.name }));
        clients.value = (clientsRes.data?.data || clientsRes.data || []).map(c => ({ id: c.id, name: c.name }));
    } catch (err) {
        console.error('Failed to fetch filter options', err);
    }
};

const clearMainFilters = () => {
    filterSearch.value = '';
    filterProjectId.value = '';
    filterClientId.value = '';
    filterSource.value = '';
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        fetchCredentials(page);
    }
};

watch([filterProjectId, filterClientId, filterSource], () => {
    fetchCredentials(1);
    fetchStats();
});

let searchTimeout;
watch(filterSearch, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchCredentials(1);
        fetchStats();
    }, 400);
});

// Unlock Logic
const unlockCredential = async (credential) => {
    const credentialId = credential.id;
    unlockingByCredential.value = {
        ...unlockingByCredential.value,
        [credentialId]: true,
    };

    try {
        const pin = unlockPinByCredential.value[credentialId] || null;
        const payload = {
            credential_id: credentialId,
        };

        if (pin) {
            payload.pin = pin;
        }

        const { data } = await axios.post('/api/vault/unlock', payload);

        unlockedByCredential.value = {
            ...unlockedByCredential.value,
            [credentialId]: data,
        };

        success('Credential unlocked successfully.');
        await fetchCredentials(pagination.value.current_page);
    } catch (err) {
        error(err?.response?.data?.message || 'Failed to unlock credential.');
    } finally {
        unlockingByCredential.value = {
            ...unlockingByCredential.value,
            [credentialId]: false,
        };
    }
};

// Edit Logic
const beginEdit = (credential) => {
    editingCredentialId.value = credential.id;
    editForm.value.label = credential.label;
    editForm.value.username = '';
    editForm.value.password = '';
    editForm.value.pin = '';
    editForm.value.is_visible_to_client = !!credential.is_visible_to_client;

    if (!credential.expires_at) {
        editForm.value.expiry_days = 0;
        return;
    }

    const now = new Date();
    const expiry = new Date(credential.expires_at);
    const days = Math.ceil((expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    editForm.value.expiry_days = Math.max(0, Number.isFinite(days) ? days : 0);
};

const cancelEdit = () => {
    editingCredentialId.value = null;
};

const saveEdit = async (credentialId) => {
    shareSaving.value = true;
    try {
        const payload = {
            label: editForm.value.label,
            is_visible_to_client: editForm.value.is_visible_to_client,
            expiry_days: Number(editForm.value.expiry_days) || 0,
        };

        if (editForm.value.username) {
            payload.username = editForm.value.username;
        }
        if (editForm.value.password) {
            payload.password = editForm.value.password;
        }
        if (editForm.value.pin) {
            payload.pin = editForm.value.pin;
        }

        await axios.put(`/api/vault/${credentialId}`, payload);

        success('Credential updated.');
        editingCredentialId.value = null;
        await fetchCredentials(pagination.value.current_page);
    } catch (err) {
        error(err?.response?.data?.message || 'Failed to update credential.');
    } finally {
        shareSaving.value = false;
    }
};

// Delete Logic
const deleteCredential = async (credentialId) => {
    if (!await confirmPrompt('Delete this credential? This action cannot be undone.')) {
        return;
    }

    try {
        await axios.delete(`/api/vault/${credentialId}`);
        success('Credential deleted.');
        if (showSidebar.value && selectedCredential.value?.id === credentialId) {
            showSidebar.value = false;
        }
        await fetchCredentials(pagination.value.current_page);
        await fetchStats();
    } catch (err) {
        error(err?.response?.data?.message || 'Failed to delete credential.');
    }
};

// Sharing Logic
const openSharePanel = async (credentialId) => {
    selectedShareUserIds.value = [];
    shareLoading.value = true;

    try {
        const [usersRes, sharedRes] = await Promise.all([
            axios.get(`/api/projects/${selectedCredential.value.project_id}/users`),
            axios.get(`/api/vault/${credentialId}/shared-users`),
        ]);

        availableUsers.value = usersRes.data || [];
        sharedUsers.value = sharedRes.data || [];
    } catch (err) {
        error('Failed to load sharing details.');
    } finally {
        shareLoading.value = false;
    }
};

const shareCredential = async (credentialId) => {
    if (!selectedShareUserIds.value.length) return;
    shareSaving.value = true;

    try {
        await axios.post(`/api/vault/${credentialId}/share`, {
            user_ids: selectedShareUserIds.value,
        });

        success('Shared successfully.');
        selectedShareUserIds.value = [];
        await openSharePanel(credentialId);
    } catch (err) {
        error('Failed to share credential.');
    } finally {
        shareSaving.value = false;
    }
};

const revokeSharedUser = async (credentialId, userId) => {
    if (!await confirmPrompt('Revoke this user\'s access?')) return;
    shareSaving.value = true;

    try {
        await axios.delete(`/api/vault/${credentialId}/share/${userId}`);
        success('Access revoked.');
        await openSharePanel(credentialId);
    } catch (err) {
        error('Failed to revoke access.');
    } finally {
        shareSaving.value = false;
    }
};

const shareUserOptions = computed(() => {
    return (availableUsers.value || []).map((user) => ({
        value: user.id,
        label: user.email ? `${user.name} (${user.email})` : user.name,
    }));
});

// Logs Logic
const fetchLogs = async (credentialId) => {
    loadingLogs.value = true;
    try {
        const { data } = await axios.get(`/api/vault/${credentialId}/logs`);
        credentialLogs.value = data || [];
    } catch (err) {
        console.error('Failed to load logs', err);
    } finally {
        loadingLogs.value = false;
    }
};

// Copy Utility
const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    success('Copied to clipboard!');
};

const formatDate = (value) => {
    if (!value) return 'Never';
    return new Date(value).toLocaleString();
};

onMounted(() => {
    fetchCredentials(1);
    fetchFilterOptions();
    fetchStats();
});
</script>

<template>
    <Head title="Credentials Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Credentials Management</h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-[100%] px-4 sm:px-6 lg:px-8">
                <!-- Stats Grid -->
                <div v-if="stats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
                    <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Credentials</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-gray-900">{{ stats.total }}</div>
                        </div>
                    </div>
                    <div class="bg-white border border-indigo-200 rounded-lg p-4 shadow-sm border-l-4 border-l-indigo-500">
                        <div class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">Team Submitted</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-indigo-950">{{ stats.team }}</div>
                        </div>
                    </div>
                    <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm border-l-4 border-l-blue-500">
                        <div class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Client Submitted</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-blue-950">{{ stats.client }}</div>
                        </div>
                    </div>
                    <div class="bg-white border border-emerald-200 rounded-lg p-4 shadow-sm border-l-4 border-l-emerald-500">
                        <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Client Visible</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-emerald-950">{{ stats.client_visible }}</div>
                        </div>
                    </div>
                    <div class="bg-white border border-amber-200 rounded-lg p-4 shadow-sm border-l-4 border-l-amber-500">
                        <div class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Expiring Soon</div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <div class="text-xl font-bold text-amber-950">{{ stats.expiring_soon }}</div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6 shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <!-- Search -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Search</label>
                            <input
                                v-model="filterSearch"
                                type="text"
                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200"
                                placeholder="Search URL, project, client..."
                            />
                        </div>

                        <!-- Project -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Project</label>
                            <SelectDropdown
                                v-model="filterProjectId"
                                :options="projects"
                                value-key="id"
                                label-key="name"
                                placeholder="All Projects"
                            />
                        </div>

                        <!-- Client -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Client</label>
                            <SelectDropdown
                                v-model="filterClientId"
                                :options="clients"
                                value-key="id"
                                label-key="name"
                                placeholder="All Clients"
                            />
                        </div>

                        <!-- Source -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Source</label>
                            <select v-model="filterSource" class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200">
                                <option value="">All Sources</option>
                                <option value="team">Team Submitted</option>
                                <option value="client">Client Submitted</option>
                            </select>
                        </div>
                    </div>

                    <!-- Clear Filters -->
                    <div v-if="filterSearch || filterProjectId || filterClientId || filterSource" class="mt-3 flex justify-end">
                        <button @click="clearMainFilters" class="text-xs font-medium text-indigo-600 hover:text-indigo-900 flex items-center gap-1">
                            Clear Filters
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-white shadow sm:rounded-lg border border-gray-200 overflow-hidden">
                    <div v-if="loading" class="p-12 text-center text-gray-500">Loading credentials...</div>
                    <div v-else-if="!credentials.length" class="p-12 text-center text-gray-500">No credentials found matching selected filters.</div>
                    
                    <div v-else class="w-full overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-8 px-3 py-3"></th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Label</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Project</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Source</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client Visible</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Expires</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Owner</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="credential in credentials" :key="credential.id" class="hover:bg-gray-50 cursor-pointer" @click="openCredentialSidebar(credential)">
                                    <td class="px-3 py-3 text-center">
                                        <ArrowTopRightOnSquareIcon class="w-4 h-4 text-gray-400 hover:text-indigo-600 transition-colors inline-block" />
                                    </td>
                                    <td class="px-4 py-3 text-xs font-medium text-gray-900 min-w-[150px]">
                                        {{ credential.label }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 min-w-[120px]">
                                        {{ credential.project?.name || '---' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 min-w-[120px]">
                                        {{ credential.client?.name || '---' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold border"
                                              :class="credential.source === 'team' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-blue-50 text-blue-700 border-blue-100'">
                                            {{ credential.source }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                        <span v-if="credential.is_visible_to_client" class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                            Yes
                                        </span>
                                        <span v-else class="text-gray-400">No</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                        {{ credential.expires_at ? new Date(credential.expires_at).toLocaleDateString() : 'Never' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                        {{ credential.owner?.name || '---' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs font-medium whitespace-nowrap" @click.stop>
                                        <div class="flex items-center justify-end gap-2">
                                            <DangerButton size="sm" @click="deleteCredential(credential.id)">
                                                Delete
                                            </DangerButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                            <div class="text-sm text-gray-600">
                                Total: <span class="font-semibold">{{ pagination.total }}</span> credentials
                            </div>
                            <div class="flex items-center gap-2" v-if="pagination.last_page > 1">
                                <SecondaryButton @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">
                                    Previous
                                </SecondaryButton>
                                <span class="text-sm text-gray-600">
                                    Page {{ pagination.current_page }} of {{ pagination.last_page }}
                                </span>
                                <SecondaryButton @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page">
                                    Next
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Sidebar Details Panel -->
        <RightSidebar :show="showSidebar" @close="showSidebar = false" title="Credential Details & Management" :initialWidth="40">
            <template #content>
                <div v-if="selectedCredential" class="p-6 space-y-6">
                    <!-- Credential Header -->
                    <div class="border-b border-gray-200 pb-4">
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-bold text-gray-900">{{ selectedCredential.label }}</h3>
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold border capitalize"
                                  :class="selectedCredential.source === 'team' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-blue-50 text-blue-700 border-blue-100'">
                                {{ selectedCredential.source }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">ID: {{ selectedCredential.id }}</p>
                    </div>

                    <!-- Meta Information -->
                    <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 flex items-center gap-1.5"><FolderIcon class="w-4 h-4" /> Project:</span>
                            <span class="font-medium text-gray-900">{{ selectedCredential.project?.name || '---' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 flex items-center gap-1.5"><BuildingOfficeIcon class="w-4 h-4" /> Client:</span>
                            <span class="font-medium text-gray-900">{{ selectedCredential.client?.name || '---' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 flex items-center gap-1.5"><UserIcon class="w-4 h-4" /> Creator:</span>
                            <span class="font-medium text-gray-900">{{ selectedCredential.owner?.name || '---' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 flex items-center gap-1.5"><CalendarIcon class="w-4 h-4" /> Created:</span>
                            <span class="font-medium text-gray-900">{{ formatDate(selectedCredential.created_at) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 flex items-center gap-1.5"><CalendarIcon class="w-4 h-4" /> Expires:</span>
                            <span class="font-medium text-gray-900">{{ formatDate(selectedCredential.expires_at) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500 flex items-center gap-1.5"><ShieldCheckIcon class="w-4 h-4" /> Client Visibility:</span>
                            <span class="font-semibold text-emerald-700" v-if="selectedCredential.is_visible_to_client">Visible to Client</span>
                            <span class="text-gray-500" v-else>Hidden from Client</span>
                        </div>
                    </div>

                    <!-- Unlock section in sidebar -->
                    <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                        <h4 class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                            <LockClosedIcon class="w-4 h-4" /> Unlock Credential
                        </h4>
                        <div class="flex items-center gap-2">
                            <input
                                v-model="unlockPinByCredential[selectedCredential.id]"
                                type="text"
                                placeholder="Enter PIN number"
                                class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm py-1.5 px-3 flex-1"
                            />
                            <PrimaryButton :disabled="unlockingByCredential[selectedCredential.id]" @click="unlockCredential(selectedCredential)">
                                Unlock
                            </PrimaryButton>
                        </div>

                        <!-- Decrypted secrets -->
                        <div v-if="unlockedByCredential[selectedCredential.id]" class="rounded-md bg-green-50 border border-green-200 p-3 mt-3 space-y-2 text-sm text-green-950">
                            <div class="flex justify-between items-center">
                                <div><span class="font-bold">Username:</span> {{ unlockedByCredential[selectedCredential.id].username }}</div>
                                <button @click="copyToClipboard(unlockedByCredential[selectedCredential.id].username)" class="text-indigo-600 hover:text-indigo-900 p-1"><ClipboardIcon class="w-4 h-4"/></button>
                            </div>
                            <div class="flex justify-between items-center">
                                <div><span class="font-bold">Password:</span> {{ unlockedByCredential[selectedCredential.id].password }}</div>
                                <button @click="copyToClipboard(unlockedByCredential[selectedCredential.id].password)" class="text-indigo-600 hover:text-indigo-900 p-1"><ClipboardIcon class="w-4 h-4"/></button>
                            </div>
                            <div v-if="unlockedByCredential[selectedCredential.id].pin" class="flex justify-between items-center">
                                <div><span class="font-bold">PIN:</span> {{ unlockedByCredential[selectedCredential.id].pin }}</div>
                                <button @click="copyToClipboard(unlockedByCredential[selectedCredential.id].pin)" class="text-indigo-600 hover:text-indigo-900 p-1"><ClipboardIcon class="w-4 h-4"/></button>
                            </div>
                        </div>
                    </div>

                    <!-- Editing -->
                    <div v-if="canEditCredential" class="border border-gray-200 rounded-lg p-4 space-y-3">
                        <h4 class="text-sm font-semibold text-gray-800">Edit Settings</h4>
                        
                        <div v-if="editingCredentialId !== selectedCredential.id">
                            <SecondaryButton @click="beginEdit(selectedCredential)">Edit Details</SecondaryButton>
                        </div>

                        <div v-else class="space-y-3">
                             <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Label (URL/Name)</label>
                                <input v-model="editForm.label" type="text" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                             </div>
                             <div>
                                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Username (optional - only to update)</label>
                                 <input v-model="editForm.username" type="text" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                             </div>
                             <div>
                                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Password (optional - only to update)</label>
                                 <input v-model="editForm.password" type="text" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                             </div>
                             <div>
                                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">PIN (optional - only to update/rotate)</label>
                                 <input v-model="editForm.pin" type="text" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                             </div>
                             <div>
                                 <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Expiry (days)</label>
                                 <input v-model.number="editForm.expiry_days" type="number" min="0" max="3650" class="w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
                             </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="editForm.is_visible_to_client" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                Visible to client
                            </label>
                            <div class="flex gap-2">
                                <PrimaryButton :disabled="shareSaving" @click="saveEdit(selectedCredential.id)">Save</PrimaryButton>
                                <SecondaryButton @click="cancelEdit">Cancel</SecondaryButton>
                            </div>
                        </div>
                    </div>

                    <!-- Sharing -->
                    <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                        <h4 class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                            <UsersIcon class="w-4 h-4" /> Share Access
                        </h4>
                        
                        <div v-if="shareLoading" class="text-xs text-gray-500">Loading project team members...</div>
                        <template v-else>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Add Team Member</label>
                            <div class="flex gap-2 items-center">
                                <div class="flex-1">
                                    <MultiSelectDropdown
                                        v-model="selectedShareUserIds"
                                        :options="shareUserOptions"
                                        :is-multi="true"
                                        placeholder="Select team members"
                                    />
                                </div>
                                <PrimaryButton :disabled="shareSaving || !selectedShareUserIds.length" @click="shareCredential(selectedCredential.id)">
                                    Share
                                </PrimaryButton>
                            </div>

                            <div class="mt-4">
                                <h5 class="text-xs font-bold text-gray-700 mb-2">Currently Shared With</h5>
                                <div v-if="!sharedUsers.length" class="text-xs text-gray-400 italic">Not shared with any team members.</div>
                                <div v-else class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                    <div v-for="user in sharedUsers" :key="user.id" class="flex items-center justify-between rounded-md bg-white border border-gray-200 px-3 py-1.5 text-xs">
                                        <span class="font-medium text-gray-800">{{ user.name }}</span>
                                        <button @click="revokeSharedUser(selectedCredential.id, user.id)" class="text-red-500 hover:text-red-700 text-xs">Revoke</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Logs -->
                    <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                        <h4 class="text-sm font-semibold text-gray-800">Activity Log</h4>
                        <div v-if="loadingLogs" class="text-xs text-gray-500">Loading activity...</div>
                        <div v-else-if="!credentialLogs.length" class="text-xs text-gray-400 italic">No access logs recorded.</div>
                        <div v-else class="space-y-3 max-h-60 overflow-y-auto">
                            <div v-for="log in credentialLogs" :key="log.id" class="border-l-2 border-indigo-500 pl-3 py-1 text-xs">
                                <p class="text-gray-800 font-medium">{{ log.description }}</p>
                                <p class="text-[10px] text-gray-500 mt-0.5">By {{ log.causer?.name || 'System' }} • {{ formatDate(log.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </RightSidebar>
    </AuthenticatedLayout>
</template>
