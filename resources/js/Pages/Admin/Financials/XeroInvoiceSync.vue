<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { success, error } from '@/Utils/notification';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const invoices = ref([]);
const projects = ref([]);
const crmServices = ref([]);
const clients = ref([]);
const loading = ref(true);
const filterSearch = ref('');
const filterSyncStatus = ref('unsynced');
const filterXeroStatus = ref('active');

const syncStatusOptions = [
    { value: 'all', label: 'All Invoices' },
    { value: 'synced', label: 'Synced Only' },
    { value: 'unsynced', label: 'Unsynced Only' },
];

const xeroStatusOptions = [
    { value: 'active', label: 'Active (Excl. Voided/Deleted)' },
    { value: 'all', label: 'All Xero Statuses' },
    { value: 'PAID', label: 'PAID' },
    { value: 'AUTHORISED', label: 'AUTHORISED' },
    { value: 'VOIDED', label: 'VOIDED' },
    { value: 'DELETED', label: 'DELETED' },
    { value: 'DRAFT', label: 'DRAFT' },
    { value: 'SUBMITTED', label: 'SUBMITTED' },
];

const filteredInvoices = computed(() => {
    return invoices.value.filter(inv => {
        // 1. Search Filter
        if (filterSearch.value.trim()) {
            const query = filterSearch.value.toLowerCase();
            const invNum = (inv.invoice_number || '').toLowerCase();
            const contactName = (inv.contact_name || '').toLowerCase();
            const contactEmail = (inv.contact_email || '').toLowerCase();
            const xeroId = (inv.xero_invoice_id || '').toLowerCase();
            if (!invNum.includes(query) && !contactName.includes(query) && !contactEmail.includes(query) && !xeroId.includes(query)) {
                return false;
            }
        }

        // 2. Sync Status Filter
        if (filterSyncStatus.value === 'synced' && !inv.is_already_synced) {
            return false;
        }
        if (filterSyncStatus.value === 'unsynced' && inv.is_already_synced) {
            return false;
        }

        // 3. Xero Status Filter
        if (filterXeroStatus.value === 'active' && (inv.status === 'VOIDED' || inv.status === 'DELETED')) {
            return false;
        }
        if (filterXeroStatus.value !== 'all' && filterXeroStatus.value !== 'active') {
            if (inv.status !== filterXeroStatus.value) {
                return false;
            }
        }

        return true;
    });
});

const totalCount = computed(() => invoices.value.length);
const syncedCount = computed(() => invoices.value.filter(inv => inv.is_already_synced).length);
const unsyncedCount = computed(() => invoices.value.filter(inv => !inv.is_already_synced).length);
const voidedCount = computed(() => invoices.value.filter(inv => inv.status === 'VOIDED' || inv.status === 'DELETED').length);
const activeCount = computed(() => invoices.value.filter(inv => inv.status !== 'VOIDED' && inv.status !== 'DELETED').length);

const clearFilters = () => {
    filterSearch.value = '';
    filterSyncStatus.value = 'unsynced';
    filterXeroStatus.value = 'active';
};

const hasActiveFilters = computed(() => {
    return filterSearch.value !== '' || filterSyncStatus.value !== 'unsynced' || filterXeroStatus.value !== 'active';
});

// Selected invoice for detailed view
const activeInvoice = ref(null);
const loadingDetail = ref(false);

// Sync form state (holds matching state for current active invoice)
const syncForm = reactive({
    project_id: '',
    client_id: '',
    line_items: [], // Array of { index, mapping_type: 'existing'|'new', project_service_id, new_service: { crm_service_id, amount, currency, frequency, description } }
    processing: false,
});

// Project services cache (ref to ensure reactivity)
const projectServicesCache = ref({});
const servicesLoading = ref({});

// Contact linking/creation processing state
const clientCreating = ref(false);

const currencyOptions = [
    { value: 'AUD', label: 'AUD' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
    { value: 'PKR', label: 'PKR' },
    { value: 'INR', label: 'INR' },
];

const frequencyOptions = [
    { value: 'one_off', label: 'One Off' },
    { value: 'monthly', label: 'Monthly' },
];

const fetchInitialData = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/xero/invoices');
        invoices.value = data.invoices || [];
        projects.value = data.projects || [];
        crmServices.value = data.crm_services || [];
        clients.value = data.clients || [];
    } catch (e) {
        error(e?.response?.data?.message || 'Failed to load initial sync data.');
    } finally {
        loading.value = false;
    }
};

const fetchProjectServices = async (projectId) => {
    if (!projectId || projectServicesCache.value[projectId]) return;
    servicesLoading.value[projectId] = true;
    try {
        const { data } = await axios.get(`/api/projects/${projectId}/services-quick-list`);
        projectServicesCache.value[projectId] = data || [];
    } catch (e) {
        error('Failed to load services for the selected project.');
    } finally {
        servicesLoading.value[projectId] = false;
    }
};

// Open the details and mapping full view
const openSyncView = async (invoice) => {
    loadingDetail.value = true;
    try {
        const { data } = await axios.get(`/api/admin/xero/invoices/${invoice.xero_invoice_id}`);
        activeInvoice.value = data;
        
        // Set initial project and client targets
        syncForm.project_id = invoice.suggested_project_id || '';
        syncForm.client_id = invoice.suggested_client?.id || '';
        syncForm.processing = false;
        
        // Initialize line item mappings
        syncForm.line_items = data.line_items.map(li => {
            // Try to match a CRM service by Xero Item Code if possible
            const defaultCrmService = crmServices.value.find(cs => cs.name.toLowerCase() === (li.description || '').toLowerCase()) || crmServices.value[0];

            return {
                index: li.index,
                mapping_type: 'existing', // 'existing' or 'new'
                project_service_id: '',
                new_service: {
                    crm_service_id: defaultCrmService?.id || '',
                    amount: li.unit_amount * li.quantity,
                    currency: data.currency || 'USD',
                    frequency: 'one_off',
                    description: li.description || '',
                }
            };
        });

        if (syncForm.project_id) {
            fetchProjectServices(syncForm.project_id);
        }
    } catch (e) {
        error(e?.response?.data?.message || 'Failed to load invoice line items from Xero.');
    } finally {
        loadingDetail.value = false;
    }
};

const handleProjectSelect = (projectId) => {
    syncForm.project_id = projectId;
    if (projectId) {
        fetchProjectServices(projectId);
    }
};

// Quick-create a CRM client from Xero Contact Info
const createCrmClient = async () => {
    if (!activeInvoice.value) return;
    clientCreating.value = true;
    try {
        const { data } = await axios.post('/api/admin/xero/contacts/create-client', {
            xero_contact_id: activeInvoice.value.contact_id,
            name: activeInvoice.value.contact_name,
            email: activeInvoice.value.contact_email,
        });
        
        success(data.message || 'Client created successfully.');
        
        // Add new client locally
        clients.value.push(data.client);
        syncForm.client_id = data.client.id;
        
        // Update current invoice suggested client
        activeInvoice.value.suggested_client = data.client;
    } catch (e) {
        error(e?.response?.data?.message || 'Failed to create client.');
    } finally {
        clientCreating.value = false;
    }
};

const performSync = async () => {
    if (!syncForm.project_id) {
        error('Please select a target CRM project.');
        return;
    }

    // Validate mappings
    for (let li of syncForm.line_items) {
        if (li.mapping_type === 'existing' && !li.project_service_id) {
            error('Please select an existing project service for all mapped items, or choose to create a new one.');
            return;
        }
        if (li.mapping_type === 'new' && !li.new_service.crm_service_id) {
            error('Please select a CRM service type for the new services.');
            return;
        }
    }

    syncForm.processing = true;
    try {
        const payload = {
            xero_invoice_id: activeInvoice.value.xero_invoice_id,
            project_id: syncForm.project_id,
            line_items: syncForm.line_items.map(li => {
                if (li.mapping_type === 'existing') {
                    return {
                        index: li.index,
                        project_service_id: li.project_service_id,
                    };
                } else {
                    return {
                        index: li.index,
                        new_service: li.new_service,
                    };
                }
            }),
        };

        const { data } = await axios.post('/api/admin/xero/invoices/sync', payload);

        success(data.message || 'Invoice synced successfully.');
        
        // Update the invoice in the local state instead of refetching everything
        const idx = invoices.value.findIndex(inv => inv.xero_invoice_id === activeInvoice.value.xero_invoice_id);
        if (idx !== -1) {
            invoices.value[idx].is_already_synced = true;
        }
        
        activeInvoice.value = null; // Back to list
    } catch (e) {
        error(e?.response?.data?.message || 'Sync failed.');
    } finally {
        syncForm.processing = false;
    }
};

onMounted(fetchInitialData);
</script>

<template>
    <Head title="Xero Invoices Sync" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ activeInvoice ? `Sync Invoice Details: ${activeInvoice.invoice_number}` : 'Xero Invoices Sync' }}
                </h2>
                <button 
                    v-if="activeInvoice" 
                    @click="activeInvoice = null" 
                    class="text-indigo-600 hover:text-indigo-900 text-sm font-medium"
                >
                    &larr; Back to Sync List
                </button>
                <Link v-else :href="route('admin.financials.invoices')" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                    &larr; Back to Invoices
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                
                <!-- Main Listing View -->
                <div v-if="!activeInvoice">
                    
                    <!-- Stats Dashboard Grid -->
                    <div v-if="!loading && invoices.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
                        <!-- Total Invoices Card -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">Total Invoices</div>
                            <div class="mt-2 flex items-baseline justify-between">
                                <div class="text-xl font-bold text-gray-900">{{ totalCount }}</div>
                            </div>
                        </div>

                        <!-- Active Invoices Card -->
                        <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm border-l-4 border-l-blue-500">
                            <div class="text-[10px] font-semibold text-blue-600 uppercase tracking-wider">Active (Excl. Voided)</div>
                            <div class="mt-2 flex items-baseline justify-between">
                                <div class="text-xl font-bold text-blue-950">{{ activeCount }}</div>
                            </div>
                        </div>

                        <!-- Unsynced Invoices Card -->
                        <div class="bg-white border border-amber-200 rounded-lg p-4 shadow-sm border-l-4 border-l-amber-500">
                            <div class="text-[10px] font-semibold text-amber-600 uppercase tracking-wider">Unsynced</div>
                            <div class="mt-2 flex items-baseline justify-between">
                                <div class="text-xl font-bold text-amber-950">{{ unsyncedCount }}</div>
                            </div>
                        </div>

                        <!-- Synced Invoices Card -->
                        <div class="bg-white border border-emerald-200 rounded-lg p-4 shadow-sm border-l-4 border-l-emerald-500">
                            <div class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wider">Synced</div>
                            <div class="mt-2 flex items-baseline justify-between">
                                <div class="text-xl font-bold text-emerald-950">{{ syncedCount }}</div>
                            </div>
                        </div>

                        <!-- Voided Invoices Card -->
                        <div class="bg-white border border-rose-200 rounded-lg p-4 shadow-sm border-l-4 border-l-rose-500">
                            <div class="text-[10px] font-semibold text-rose-600 uppercase tracking-wider">Voided on Xero</div>
                            <div class="mt-2 flex items-baseline justify-between">
                                <div class="text-xl font-bold text-rose-950">{{ voidedCount }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters Panel -->
                    <div v-if="!loading && invoices.length > 0" class="bg-white border border-gray-200 rounded-lg p-4 mb-6 shadow-sm">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- Text Input Search -->
                            <div class="lg:col-span-2">
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Search</label>
                                <input
                                    v-model="filterSearch"
                                    type="text"
                                    class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 placeholder-gray-400"
                                    placeholder="Search invoice number, contact name..."
                                />
                            </div>

                            <!-- Sync Status Dropdown -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Sync Status</label>
                                <SelectDropdown
                                    v-model="filterSyncStatus"
                                    :options="syncStatusOptions"
                                    value-key="value"
                                    label-key="label"
                                    placeholder="All Statuses"
                                />
                            </div>

                            <!-- Xero Status Dropdown -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Xero Status</label>
                                <SelectDropdown
                                    v-model="filterXeroStatus"
                                    :options="xeroStatusOptions"
                                    value-key="value"
                                    label-key="label"
                                    placeholder="All Statuses"
                                />
                            </div>
                        </div>
                        
                        <!-- Clear Action Button & Refresh -->
                        <div class="mt-3 flex justify-between items-center border-t border-gray-100 pt-3">
                            <div>
                                <button
                                    v-if="hasActiveFilters"
                                    @click="clearFilters"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-900 flex items-center gap-1"
                                >
                                    Clear Filters
                                </button>
                            </div>
                            <button
                                type="button"
                                @click="fetchInitialData"
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-semibold rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none"
                            >
                                <svg class="w-3.5 h-3.5 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.75 8.25H12"></path>
                                </svg>
                                Refresh from Xero
                            </button>
                        </div>
                    </div>

                    <!-- Main Listing View Container -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                        <div class="p-6 bg-white">
                            <div v-if="loading" class="text-center py-12">
                                <span class="text-gray-500 font-medium">Loading invoices from Xero...</span>
                            </div>

                            <div v-else-if="invoices.length === 0" class="text-center py-12 text-gray-500">
                                No recent invoices found in Xero.
                            </div>

                            <div v-else class="space-y-6">
                                <div v-if="filteredInvoices.length === 0" class="text-center py-12 text-gray-500">
                                    No invoices found matching the current filter.
                                </div>

                                <div v-else class="w-full overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs sm:text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice ID / Number</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact / Client</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Dates</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Amount</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Sync Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Xero Status</th>
                                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="inv in filteredInvoices" :key="inv.xero_invoice_id" :class="['hover:bg-gray-50 cursor-pointer transition-colors', {'bg-gray-50 opacity-75': inv.is_already_synced}]" @click="!inv.is_already_synced && openSyncView(inv)">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-semibold text-indigo-600">{{ inv.invoice_number || 'N/A' }}</div>
                                                    <div class="text-xs text-gray-400 font-mono">{{ inv.xero_invoice_id }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ inv.contact_name }}</div>
                                                    <div v-if="inv.suggested_client" class="text-xs text-emerald-600 font-semibold mt-1">
                                                        Matched client: {{ inv.suggested_client.name }}
                                                    </div>
                                                    <div v-else class="text-xs text-amber-500 font-semibold mt-1">
                                                        No CRM client matched
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div>{{ inv.date }}</div>
                                                    <div class="text-xs text-gray-400">Due: {{ inv.due_date }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                                    {{ inv.total_amount.toFixed(2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <span v-if="inv.is_already_synced" class="px-2 py-0.5 rounded text-[11px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-100">
                                                        Synced
                                                    </span>
                                                    <span v-else class="px-2 py-0.5 rounded text-[11px] font-semibold border bg-amber-50 text-amber-700 border-amber-100">
                                                        Unsynced
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <span :class="[
                                                        'px-2 py-0.5 rounded text-[11px] font-semibold border',
                                                        inv.status === 'PAID' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' :
                                                        inv.status === 'AUTHORISED' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' :
                                                        inv.status === 'VOIDED' ? 'bg-rose-50 text-rose-700 border-rose-100' :
                                                        'bg-blue-50 text-blue-700 border-blue-100'
                                                    ]">
                                                        {{ inv.status }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" @click.stop>
                                                    <button 
                                                        v-if="!inv.is_already_synced"
                                                        @click="openSyncView(inv)"
                                                        class="inline-flex items-center px-3 py-1.5 border border-indigo-600 text-xs font-semibold rounded-md text-indigo-600 bg-white hover:bg-indigo-50 transition-colors"
                                                    >
                                                        Map & Replicate
                                                    </button>
                                                    <span v-else class="text-gray-400 text-xs font-semibold">Synced</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Replication and Mapping View -->
                <div v-if="loadingDetail" class="text-center py-24 bg-white shadow-sm sm:rounded-lg">
                    <span class="text-gray-500 font-medium">Fetching complete invoice details and line items from Xero...</span>
                </div>
                <div v-else-if="activeInvoice" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Left: Xero Invoice Details and Customer Resolution (2 Cols) -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Invoice Card Header Details -->
                        <div class="bg-white p-6 shadow-sm rounded-lg border border-gray-200">
                            <div class="flex justify-between items-center border-b border-gray-100 pb-4 mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900">Xero Invoice: {{ activeInvoice.invoice_number }}</h3>
                                    <div class="text-xs text-gray-500 font-mono">Xero ID: {{ activeInvoice.xero_invoice_id }}</div>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                                    {{ activeInvoice.status }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                                <div>
                                    <span class="block text-xs font-semibold text-gray-400 uppercase">Issue Date</span>
                                    <span class="text-sm font-medium text-gray-800">{{ activeInvoice.date }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs font-semibold text-gray-400 uppercase">Due Date</span>
                                    <span class="text-sm font-medium text-gray-800">{{ activeInvoice.due_date }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs font-semibold text-gray-400 uppercase">Currency</span>
                                    <span class="text-sm font-bold text-indigo-600">{{ activeInvoice.currency || 'USD' }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs font-semibold text-gray-400 uppercase">Total Amount</span>
                                    <span class="text-sm font-bold text-gray-900">{{ activeInvoice.total_amount.toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Resolution -->
                        <div class="bg-white p-6 shadow-sm rounded-lg border border-gray-200 space-y-4">
                            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">1. Client / Customer Linking</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg">
                                <div>
                                    <span class="block text-xs font-semibold text-gray-500">Xero Contact Details</span>
                                    <span class="block text-sm font-bold text-gray-800 mt-1">{{ activeInvoice.contact_name }}</span>
                                    <span class="block text-xs text-gray-400">{{ activeInvoice.contact_email || 'No email associated' }}</span>
                                </div>
                                <div class="flex flex-col justify-center space-y-2">
                                    <span class="block text-xs font-semibold text-gray-500">CRM Link Status</span>
                                    <div v-if="activeInvoice.suggested_client" class="text-xs text-green-600 font-bold">
                                        Matched CRM Client: {{ activeInvoice.suggested_client.name }}
                                    </div>
                                    <div v-else class="text-xs text-amber-600 font-bold">
                                        No client link found.
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-end gap-3 pt-2">
                                <div class="flex-1">
                                    <label class="block text-xs font-semibold text-gray-700">Link to CRM Client</label>
                                    <SelectDropdown 
                                        v-model="syncForm.client_id"
                                        :options="clients"
                                        value-key="id"
                                        label-key="name"
                                        placeholder="-- Choose Client --"
                                    />
                                </div>
                                <button 
                                    v-if="!activeInvoice.suggested_client"
                                    type="button"
                                    @click="createCrmClient"
                                    :disabled="clientCreating"
                                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ clientCreating ? 'Creating...' : 'Create Client from Xero' }}
                                </button>
                            </div>
                        </div>

                        <!-- Target Project -->
                        <div class="bg-white p-6 shadow-sm rounded-lg border border-gray-200 space-y-4">
                            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">2. Match Target CRM Project</h3>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Select Project</label>
                                <SelectDropdown 
                                    :model-value="syncForm.project_id"
                                    @update:model-value="handleProjectSelect"
                                    :options="projects"
                                    value-key="id"
                                    label-key="name"
                                    placeholder="-- Select Project --"
                                />
                            </div>
                        </div>

                        <!-- Line Items Mapping Details -->
                        <div class="bg-white p-6 shadow-sm rounded-lg border border-gray-200 space-y-4">
                            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">3. Replicated Line Items & Services Mapping</h3>
                            
                            <div class="space-y-6">
                                <div v-for="li in activeInvoice.line_items" :key="li.index" class="p-5 rounded-lg border border-gray-200 bg-gray-50 space-y-4 shadow-sm">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="text-sm font-bold text-gray-900">{{ li.description || '(No description)' }}</div>
                                            <div class="text-xs text-gray-500 mt-1">Qty: {{ li.quantity }} | Unit Price: {{ li.unit_amount.toFixed(2) }} | Tax: {{ li.tax_type }}</div>
                                        </div>
                                        <div class="text-sm font-bold text-indigo-700 bg-white border border-indigo-100 px-3 py-1 rounded">
                                            Total: {{ (li.unit_amount * li.quantity).toFixed(2) }}
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 pt-3">
                                        <div v-if="!syncForm.project_id" class="text-xs text-amber-600 font-medium bg-amber-50 p-2 rounded border border-amber-100">
                                            Please select a CRM Project in Step 2 to configure service logic mapping for this item.
                                        </div>
                                        <div v-else>
                                            <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Service Logic Configuration</label>
                                            <div class="flex items-center space-x-6 mb-3">
                                                <label class="inline-flex items-center text-xs font-medium text-gray-700">
                                                    <input 
                                                        type="radio" 
                                                        value="existing"
                                                        v-model="syncForm.line_items.find(item => item.index === li.index).mapping_type" 
                                                        class="form-radio text-indigo-600"
                                                    >
                                                    <span class="ml-2">Link Existing Project Service</span>
                                                </label>
                                                <label class="inline-flex items-center text-xs font-medium text-gray-700">
                                                    <input 
                                                        type="radio" 
                                                        value="new" 
                                                        v-model="syncForm.line_items.find(item => item.index === li.index).mapping_type" 
                                                        class="form-radio text-indigo-600"
                                                    >
                                                    <span class="ml-2">Create New Project Service Inline</span>
                                                </label>
                                            </div>

                                            <!-- Option A: Link Existing Service -->
                                            <div v-if="syncForm.line_items.find(item => item.index === li.index).mapping_type === 'existing'" class="space-y-2">
                                                <select 
                                                    v-model="syncForm.line_items.find(item => item.index === li.index).project_service_id"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs"
                                                >
                                                    <option value="">-- Choose Existing Service --</option>
                                                    <option v-for="srv in (projectServicesCache[syncForm.project_id] || [])" :key="srv.id" :value="srv.id">
                                                        {{ srv.name }}
                                                    </option>
                                                </select>
                                            </div>

                                            <!-- Option B: Create New Service Inline -->
                                            <div v-else class="p-4 bg-white rounded border border-gray-200 space-y-3">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase">CRM Service Type</label>
                                                        <select 
                                                            v-model="syncForm.line_items.find(item => item.index === li.index).new_service.crm_service_id"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs"
                                                        >
                                                            <option v-for="srv in crmServices" :key="srv.id" :value="srv.id">
                                                                {{ srv.name }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase">Amount</label>
                                                        <TextInput 
                                                            type="number"
                                                            step="0.01"
                                                            v-model="syncForm.line_items.find(item => item.index === li.index).new_service.amount"
                                                            class="mt-1 block w-full text-xs py-1"
                                                        />
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase">Currency</label>
                                                        <select 
                                                            v-model="syncForm.line_items.find(item => item.index === li.index).new_service.currency"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs"
                                                        >
                                                            <option v-for="opt in currencyOptions" :key="opt.value" :value="opt.value">
                                                                {{ opt.label }}
                                                                </option>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[10px] font-bold text-gray-500 uppercase">Frequency</label>
                                                        <select 
                                                            v-model="syncForm.line_items.find(item => item.index === li.index).new_service.frequency"
                                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs"
                                                        >
                                                            <option v-for="opt in frequencyOptions" :key="opt.value" :value="opt.value">
                                                                {{ opt.label }}
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="block text-[10px] font-bold text-gray-500 uppercase">Service Description</label>
                                                    <textarea 
                                                        v-model="syncForm.line_items.find(item => item.index === li.index).new_service.description"
                                                        rows="2"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs"
                                                    ></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right Sidebar: Summary & Actions (1 Col) -->
                    <div class="space-y-6">
                        <div class="bg-white p-6 shadow-sm rounded-lg border border-gray-200 space-y-4 sticky top-6">
                            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Sync Confirmation Summary</h3>
                            
                            <div class="space-y-3 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Invoice Number:</span>
                                    <span class="font-semibold text-gray-900">{{ activeInvoice.invoice_number }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Total Lines Replicated:</span>
                                    <span class="font-semibold text-gray-900">{{ activeInvoice.line_items.length }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Total Value:</span>
                                    <span class="font-bold text-indigo-600">{{ activeInvoice.total_amount.toFixed(2) }} {{ activeInvoice.currency || 'USD' }}</span>
                                </div>
                                <div class="border-t border-gray-100 pt-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Selected Client:</span>
                                        <span class="font-semibold text-gray-900">
                                            {{ clients.find(c => c.id === Number(syncForm.client_id))?.name || 'None selected' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between mt-1">
                                        <span class="text-gray-500">Target Project:</span>
                                        <span class="font-semibold text-gray-900">
                                            {{ projects.find(p => p.id === Number(syncForm.project_id))?.name || 'None selected' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-100 space-y-2">
                                <PrimaryButton 
                                    @click="performSync"
                                    :disabled="syncForm.processing || !syncForm.project_id || !syncForm.client_id"
                                    class="w-full justify-center"
                                >
                                    {{ syncForm.processing ? 'Syncing...' : 'Sync & Save Replicated Invoice' }}
                                </PrimaryButton>
                                <button 
                                    type="button" 
                                    @click="activeInvoice = null"
                                    class="w-full text-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none"
                                >
                                    Cancel & Go Back
                                </button>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
