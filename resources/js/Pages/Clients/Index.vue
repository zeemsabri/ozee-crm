<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3'; // Import usePage
import { ref, onMounted, computed, reactive } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TimezoneSelect from '@/Components/TimezoneSelect.vue';
import { showSuccessNotification, showErrorNotification } from '@/Utils/notification';

// Access user from Inertia props
const user = computed(() => usePage().props.auth.user);

// Reactive state for clients, loading, and form data
const clients = ref([]);
const loading = ref(true);
const errors = ref({});
const generalError = ref('');
const searchQuery = ref('');

// Modals state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showXeroSyncModal = ref(false);

// Form state for creating/editing
const clientForm = reactive({
    id: null,
    name: '',
    email: '',
    phone: '',
    address: '',
    notes: '',
    timezone: '',
});

// State for client being deleted
const clientToDelete = ref(null);
const xeroSyncLoading = ref(false);
const xeroSyncError = ref('');
const xeroSyncClient = ref(null);
const xeroCandidates = ref([]);
const selectedXeroContactId = ref(null);

// Permission checks from the AuthenticatedLayout (re-using logic here)
const isSuperAdmin = computed(() => {
    if (!user.value) return false;
    return user.value.role_data?.slug === 'super-admin' ||
           user.value.role === 'super_admin' ||
           user.value.role === 'super-admin';
});
const isManager = computed(() => {
    if (!user.value) return false;
    return user.value.role_data?.slug === 'manager' ||
           user.value.role === 'manager' ||
           user.value.role === 'manager-role' ||
           user.value.role === 'manager_role';
});
const canManageClients = computed(() => isSuperAdmin.value || isManager.value);
const canSyncXeroContacts = computed(() => canManageClients.value);


// --- Fetch Clients ---
const fetchClients = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const response = await axios.get('/api/clients');
        clients.value = response.data.data;
    } catch (error) {
        generalError.value = 'Failed to fetch clients.';
        console.error('Error fetching clients:', error);
        if (error.response && error.response.status === 403) {
            generalError.value = 'You do not have permission to view clients.';
        }
    } finally {
        loading.value = false;
    }
};

// --- Create Client ---
const openCreateModal = () => {
    // Reset form for new client
    clientForm.id = null;
    clientForm.name = '';
    clientForm.email = '';
    clientForm.phone = '';
    clientForm.address = '';
    clientForm.notes = '';
    clientForm.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    errors.value = {};
    generalError.value = '';
    showCreateModal.value = true;
};

const createClient = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        const response = await axios.post('/api/clients', clientForm);
        const newClient = response.data.data;
        clients.value.push(newClient); // Add new client to the list
        showCreateModal.value = false;
        
        // Two-step process: Trigger Xero Sync immediately
        openXeroSyncModal(newClient);
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to create client.';
            console.error('Error creating client:', error);
        }
    }
};

// --- Edit Client ---
const openEditModal = (client) => {
    clientForm.id = client.id;
    clientForm.name = client.name;
    clientForm.email = client.email;
    clientForm.phone = client.phone;
    clientForm.address = client.address;
    clientForm.notes = client.notes;
    clientForm.timezone = client.timezone || '';
    errors.value = {};
    generalError.value = '';
    showEditModal.value = true;
};

const updateClient = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        const response = await axios.put(`/api/clients/${clientForm.id}`, clientForm);
        // Find and update the client in the local list
        const index = clients.value.findIndex(c => c.id === clientForm.id);
        if (index !== -1) {
            clients.value[index] = response.data.data;
        }
        showEditModal.value = false;
        alert('Client updated successfully!');
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to update client.';
            console.error('Error updating client:', error);
        }
    }
};

// --- Delete Client ---
const confirmClientDeletion = (client) => {
    clientToDelete.value = client;
    showDeleteModal.value = true;
};

const deleteClient = async () => {
    generalError.value = '';
    try {
        await axios.delete(`/api/clients/${clientToDelete.value.id}`);
        clients.value = clients.value.filter(c => c.id !== clientToDelete.value.id); // Remove from list
        showDeleteModal.value = false;
        clientToDelete.value = null; // Clear
        alert('Client deleted successfully!');
    } catch (error) {
        generalError.value = 'Failed to delete client.';
        console.error('Error deleting client:', error);
    }
};

// --- Xero Contact Sync ---
const openXeroSyncModal = async (client) => {
    xeroSyncClient.value = client;
    xeroCandidates.value = [];
    selectedXeroContactId.value = null;
    xeroSyncError.value = '';
    showXeroSyncModal.value = true;

    await fetchXeroCandidates(client.id);
};

const fetchXeroCandidates = async (clientId) => {
    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const response = await axios.get(`/api/clients/${clientId}/xero-contact-candidates`);
        xeroCandidates.value = response.data?.candidates || [];

        if (xeroCandidates.value.length === 1) {
            selectedXeroContactId.value = xeroCandidates.value[0].contact_id;
        }
    } catch (error) {
        xeroSyncError.value = error.response?.data?.message || 'Failed to fetch Xero contacts.';
    } finally {
        xeroSyncLoading.value = false;
    }
};

const syncClientWithXero = async () => {
    if (!xeroSyncClient.value) return;

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const payload = {};
        if (selectedXeroContactId.value) {
            payload.selected_contact_id = selectedXeroContactId.value;
        }

        const response = await axios.post(`/api/clients/${xeroSyncClient.value.id}/xero-contact-sync`, payload);
        const updatedClient = response.data?.data;

        if (updatedClient) {
            const index = clients.value.findIndex(c => c.id === updatedClient.id);
            if (index !== -1) {
                clients.value[index] = updatedClient;
            }
        }

        showSuccessNotification('Client synced with Xero contact successfully.');
        showXeroSyncModal.value = false;
    } catch (error) {
        const responseData = error.response?.data || {};

        if (responseData.requires_selection && Array.isArray(responseData.candidates)) {
            xeroCandidates.value = responseData.candidates;
        }

        xeroSyncError.value = responseData.message || 'Failed to sync client with Xero.';
        showErrorNotification(xeroSyncError.value);
    } finally {
        xeroSyncLoading.value = false;
    }
};

const createXeroContact = async () => {
    if (!xeroSyncClient.value) return;

    xeroSyncLoading.value = true;
    xeroSyncError.value = '';

    try {
        const response = await axios.post(`/api/clients/${xeroSyncClient.value.id}/xero-contact-create`);
        const updatedClient = response.data?.data;

        if (updatedClient) {
            const index = clients.value.findIndex(c => c.id === updatedClient.id);
            if (index !== -1) {
                clients.value[index] = updatedClient;
            }
        }

        showSuccessNotification('Xero contact created and linked successfully.');
        showXeroSyncModal.value = false;
    } catch (error) {
        xeroSyncError.value = error.response?.data?.message || 'Failed to create Xero contact.';
        showErrorNotification(xeroSyncError.value);
    } finally {
        xeroSyncLoading.value = false;
    }
};

const xeroCandidateOptions = computed(() => {
    return xeroCandidates.value.map((candidate) => ({
        value: candidate.contact_id,
        label: `${candidate.name || 'Unnamed Contact'}${candidate.email ? ` (${candidate.email})` : ''}`,
    }));
});

const filteredClients = computed(() => {
    if (!searchQuery.value) return clients.value;
    const q = searchQuery.value.toLowerCase();
    return clients.value.filter(c => 
        (c.name && c.name.toLowerCase().includes(q)) ||
        (c.email && c.email.toLowerCase().includes(q)) ||
        (c.company && c.company.toLowerCase().includes(q)) ||
        (c.phone && c.phone.toLowerCase().includes(q))
    );
});

const formatDateTime = (value) => {
    if (!value) return 'Never';
    return new Date(value).toLocaleString();
};

// --- Telegram Code ---
const generateTelegramCode = async (client) => {
    try {
        const response = await axios.post(`/api/clients/${client.id}/generate-telegram-code`);
        // Find and update the client in the local list
        const index = clients.value.findIndex(c => c.id === client.id);
        if (index !== -1) {
            clients.value[index].telegram_link_code = response.data.code;
        }
        showSuccessNotification('Telegram link code generated!');
    } catch (error) {
        console.error('Error generating Telegram code:', error);
        showErrorNotification('Failed to generate Telegram code.');
    }
};

// Fetch clients when the component is mounted
onMounted(() => {
    fetchClients();
});
</script>

<template>
    <Head title="Clients" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Clients</h2>
        </template>

        <div class="py-8">
            <div class="w-full px-4 sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-xl border border-gray-100">
                    <div class="p-6 text-gray-900">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900">Client Directory</h3>
                                <p class="text-sm text-gray-500 mt-1">Manage your customer relationships and Xero synchronization.</p>
                            </div>
                        </div>

                        <!-- Search and Actions -->
                        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="relative w-full md:w-96">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input 
                                    v-model="searchQuery" 
                                    type="text" 
                                    placeholder="Search by name, email or company..." 
                                    class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl leading-5 bg-gray-50 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent sm:text-sm transition duration-150 ease-in-out"
                                />
                            </div>
                            
                            <div v-permission="'create_clients'">
                                <PrimaryButton @click="openCreateModal" class="flex items-center gap-2 !rounded-xl !py-2.5 shadow-sm hover:shadow-md transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add New Client
                                </PrimaryButton>
                            </div>
                        </div>

                        <div v-if="loading" class="flex flex-col items-center justify-center py-12">
                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mb-4"></div>
                            <div class="text-gray-500 font-medium">Loading your clients...</div>
                        </div>
                        <div v-else-if="generalError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6">
                            {{ generalError }}
                        </div>
                        <div v-else-if="clients.length === 0" class="flex flex-col items-center justify-center py-20 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                            <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-900">No clients yet</h3>
                            <p class="text-gray-500 mb-6">Get started by creating your first client.</p>
                            <PrimaryButton v-permission="'create_clients'" @click="openCreateModal">Create Client</PrimaryButton>
                        </div>
                        <div v-else>
                            <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-sm">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Client Info</th>
                                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Contact Details</th>
                                        <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Xero Connection</th>
                                        <th scope="col" class="px-6 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="client in filteredClients" :key="client.id" class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="h-10 w-10 flex-shrink-0 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm">
                                                    {{ client.name.charAt(0) }}
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-bold text-gray-900">{{ client.name }}</div>
                                                    <div class="text-xs text-gray-500">{{ client.company || 'Private Individual' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ client.email }}</div>
                                            <div class="text-xs text-gray-500">{{ client.phone || 'No phone number' }}</div>
                                            <div v-if="client.timezone" class="text-xs text-gray-500">{{ client.timezone }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div v-if="client.xero_contact_id" class="flex flex-col">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                                    <span class="text-sm font-semibold text-emerald-800">{{ client.xero_contact_name }}</span>
                                                </div>
                                                <div class="text-[10px] text-gray-400 mt-1 uppercase font-bold tracking-wider">
                                                    Synced {{ formatDateTime(client.xero_synced_at) }}
                                                </div>
                                            </div>
                                            <div v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                Not linked
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-2">
                                                <PrimaryButton as="a" :href="`/clients/${client.id}`" class="!bg-indigo-50 !text-indigo-700 hover:!bg-indigo-100 !border-transparent !px-3 !py-1.5 text-xs font-bold transition-all">View</PrimaryButton>
                                                
                                                <SecondaryButton
                                                    v-if="canSyncXeroContacts"
                                                    @click="openXeroSyncModal(client)"
                                                    class="!px-3 !py-1.5 text-xs font-bold"
                                                >
                                                    Sync Xero
                                                </SecondaryButton>
                                                
                                                <!-- Telegram Link -->
                                                <div class="flex items-center bg-sky-50 px-3 py-1.5 rounded-lg border border-sky-100" v-if="client.telegram_link_code || client.telegram_account">
                                                    <div v-if="client.telegram_account" class="flex items-center text-sky-700" title="Telegram Linked">
                                                        <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.53-1.39.51-.46-.01-1.33-.26-1.98-.48-.8-.27-1.43-.42-1.37-.89.03-.25.38-.51 1.03-.78 4.04-1.76 6.74-2.92 8.09-3.48 3.85-1.6 4.64-1.88 5.17-1.89.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.13-.03.19z"/></svg>
                                                        <span class="text-xs font-bold">@{{ client.telegram_account.username || 'Linked' }}</span>
                                                    </div>
                                                    <div v-else class="flex items-center">
                                                        <span class="text-[10px] font-mono font-bold text-sky-800 mr-2 bg-white px-2 py-0.5 rounded border border-sky-200 cursor-all select-all">/link #{{ client.telegram_link_code }}</span>
                                                        <button @click="generateTelegramCode(client)" class="text-sky-400 hover:text-sky-600" title="Regenerate">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                                <button v-else @click="generateTelegramCode(client)" class="p-2 text-sky-400 hover:text-sky-600 hover:bg-sky-50 rounded-lg transition-colors border border-sky-100" title="Generate Telegram Code">
                                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.53-1.39.51-.46-.01-1.33-.26-1.98-.48-.8-.27-1.43-.42-1.37-.89.03-.25.38-.51 1.03-.78 4.04-1.76 6.74-2.92 8.09-3.48 3.85-1.6 4.64-1.88 5.17-1.89.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.13-.03.19z"/></svg>
                                                </button>
                                                
                                                <div class="flex items-center gap-1">
                                                    <button v-permission="'edit_clients'" @click="openEditModal(client)" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Edit">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button v-permission="'delete_clients'" @click="confirmClientDeletion(client)" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredClients.length === 0 && searchQuery">
                                        <td colspan="4" class="px-6 py-12 text-center text-gray-500 italic">
                                            No clients matching "{{ searchQuery }}"
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Create New Client</h2>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <form @submit.prevent="createClient">
                    <div class="mb-4">
                        <InputLabel for="name" value="Name" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="clientForm.name" required autofocus />
                        <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" type="email" class="mt-1 block w-full" v-model="clientForm.email" required />
                        <InputError :message="errors.email ? errors.email[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="phone" value="Phone" />
                        <TextInput id="phone" type="text" class="mt-1 block w-full" v-model="clientForm.phone" />
                        <InputError :message="errors.phone ? errors.phone[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="address" value="Address" />
                        <textarea id="address" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="clientForm.address"></textarea>
                        <InputError :message="errors.address ? errors.address[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="notes" value="Notes" />
                        <textarea id="notes" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="clientForm.notes"></textarea>
                        <InputError :message="errors.notes ? errors.notes[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="timezone" value="Timezone" />
                        <TimezoneSelect id="timezone" v-model="clientForm.timezone" class="mt-1 block w-full" />
                        <InputError :message="errors.timezone ? errors.timezone[0] : ''" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
                        <PrimaryButton class="ms-3" type="submit">Create Client</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Edit Client</h2>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <form @submit.prevent="updateClient">
                    <div class="mb-4">
                        <InputLabel for="edit_name" value="Name" />
                        <TextInput id="edit_name" type="text" class="mt-1 block w-full" v-model="clientForm.name" required autofocus />
                        <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_email" value="Email" />
                        <TextInput id="edit_email" type="email" class="mt-1 block w-full" v-model="clientForm.email" required />
                        <InputError :message="errors.email ? errors.email[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_phone" value="Phone" />
                        <TextInput id="edit_phone" type="text" class="mt-1 block w-full" v-model="clientForm.phone" />
                        <InputError :message="errors.phone ? errors.phone[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_address" value="Address" />
                        <textarea id="edit_address" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="clientForm.address"></textarea>
                        <InputError :message="errors.address ? errors.address[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_notes" value="Notes" />
                        <textarea id="edit_notes" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="clientForm.notes"></textarea>
                        <InputError :message="errors.notes ? errors.notes[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_timezone" value="Timezone" />
                        <TimezoneSelect id="edit_timezone" v-model="clientForm.timezone" class="mt-1 block w-full" />
                        <InputError :message="errors.timezone ? errors.timezone[0] : ''" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showEditModal = false">Cancel</SecondaryButton>
                        <PrimaryButton class="ms-3" type="submit">Update Client</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this client?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    This action cannot be undone. All associated projects and conversations will also be deleted.
                </p>
                <div v-if="clientToDelete" class="mt-4 text-gray-800">
                    <strong>Client:</strong> {{ clientToDelete.name }} ({{ clientToDelete.email }})
                </div>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteClient">Delete Client</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showXeroSyncModal" @close="showXeroSyncModal = false" maxWidth="2xl">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-2">Sync Client with Xero Contact</h2>

                <p class="text-sm text-gray-600 mb-4" v-if="xeroSyncClient">
                    Client: <span class="font-semibold">{{ xeroSyncClient.name }}</span>
                    <span v-if="xeroSyncClient.email">({{ xeroSyncClient.email }})</span>
                </p>

                <div v-if="xeroSyncError" class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ xeroSyncError }}
                </div>

                <div v-if="xeroSyncLoading" class="text-sm text-gray-500">Loading Xero contacts...</div>

                <div v-else>
                    <div v-if="xeroCandidates.length === 0" class="text-sm text-gray-500">
                        No Xero contact candidates found for this client.
                    </div>

                    <div v-else class="space-y-4">
                        <div>
                            <InputLabel value="Choose Xero Contact" />
                            <SelectDropdown
                                :options="xeroCandidateOptions"
                                v-model="selectedXeroContactId"
                                placeholder="Select a matching Xero contact"
                            />
                        </div>

                        <div class="max-h-48 overflow-y-auto rounded-md border border-gray-200">
                            <div
                                v-for="candidate in xeroCandidates"
                                :key="candidate.contact_id"
                                class="border-b border-gray-100 px-3 py-2 text-sm last:border-b-0"
                            >
                                <div class="font-semibold text-gray-900">{{ candidate.name || 'Unnamed Contact' }}</div>
                                <div class="text-gray-500">{{ candidate.email || 'No email' }}</div>
                                <div class="text-xs text-gray-400 mt-1">ID: {{ candidate.contact_id }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton @click="showXeroSyncModal = false">Cancel</SecondaryButton>
                    <SecondaryButton
                        v-if="!xeroSyncLoading"
                        @click="createXeroContact"
                        class="bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100"
                        title="Create this client as a new contact in Xero"
                    >
                        Create in Xero
                    </SecondaryButton>
                    <PrimaryButton
                        :disabled="xeroSyncLoading || xeroCandidates.length === 0 || (!selectedXeroContactId && xeroCandidates.length > 1)"
                        @click="syncClientWithXero"
                    >
                        Save Xero Link
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
