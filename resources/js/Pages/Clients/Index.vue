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
                                            <PrimaryButton as="a" :href="`/clients/${client.id}`">View</PrimaryButton>
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
