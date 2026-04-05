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
import { showSuccessNotification, showErrorNotification } from '@/Utils/notification';

// Access user from Inertia props
const user = computed(() => usePage().props.auth.user);

// Reactive state for clients, loading, and form data
const clients = ref([]);
const loading = ref(true);
const errors = ref({});
const generalError = ref('');

// Modals state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);

// Form state for creating/editing
const clientForm = reactive({
    id: null,
    name: '',
    email: '',
    phone: '',
    address: '',
    notes: '',
});

// State for client being deleted
const clientToDelete = ref(null);

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
    errors.value = {};
    generalError.value = '';
    showCreateModal.value = true;
};

const createClient = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        const response = await axios.post('/api/clients', clientForm);
        clients.value.push(response.data.data); // Add new client to the list
        showCreateModal.value = false;
        alert('Client created successfully!'); // Simple success feedback
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

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-2xl font-bold mb-4">Client List</h3>

                        <div v-if="canManageClients" class="mb-6">
                            <PrimaryButton @click="openCreateModal">
                                Create New Client
                            </PrimaryButton>
                        </div>

                        <div v-if="loading" class="text-gray-600">Loading clients...</div>
                        <div v-else-if="generalError" class="text-red-600">{{ generalError }}</div>
                        <div v-else-if="clients.length === 0" class="text-gray-600">No clients found.</div>
                        <div v-else>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="client in clients" :key="client.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ client.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ client.email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ client.phone || 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <PrimaryButton as="a" :href="`/clients/${client.id}`" title="View Details">View</PrimaryButton>
                                            
                                            <!-- Telegram Code Section -->
                                            <div class="flex items-center bg-sky-50 px-2 py-1 rounded border border-sky-100" v-if="client.telegram_link_code || client.telegram_account">
                                                <div v-if="client.telegram_account" class="flex items-center text-sky-700" title="Telegram Linked">
                                                    <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.53-1.39.51-.46-.01-1.33-.26-1.98-.48-.8-.27-1.43-.42-1.37-.89.03-.25.38-.51 1.03-.78 4.04-1.76 6.74-2.92 8.09-3.48 3.85-1.6 4.64-1.88 5.17-1.89.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.13-.03.19z"/></svg>
                                                    <span class="text-xs font-semibold">@{{ client.telegram_account.username || 'Linked' }}</span>
                                                </div>
                                                <div v-else class="flex items-center">
                                                    <span class="text-[10px] font-mono font-bold text-sky-800 mr-1 bg-white px-1.5 py-0.5 rounded border border-sky-100 cursor-all select-all">/link #{{ client.telegram_link_code }}</span>
                                                    <button @click="generateTelegramCode(client)" class="p-0.5 text-sky-400 hover:text-sky-600 transition-colors" title="Regenerate Code">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <button v-else @click="generateTelegramCode(client)" class="p-1 text-gray-400 hover:text-sky-600 border border-transparent hover:border-sky-200 rounded transition-colors" title="Generate Telegram Code">
                                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.53-1.39.51-.46-.01-1.33-.26-1.98-.48-.8-.27-1.43-.42-1.37-.89.03-.25.38-.51 1.03-.78 4.04-1.76 6.74-2.92 8.09-3.48 3.85-1.6 4.64-1.88 5.17-1.89.11 0 .37.03.54.17.14.12.18.28.2.45-.02.07-.02.13-.03.19z"/></svg>
                                            </button>

                                            <PrimaryButton v-if="canManageClients" @click="openEditModal(client)">Edit</PrimaryButton>
                                            <DangerButton v-if="canManageClients" @click="confirmClientDeletion(client)">Delete</DangerButton>
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
    </AuthenticatedLayout>
</template>
