<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, reactive, watch } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import Multiselect from 'vue-multiselect';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import 'vue-multiselect/dist/vue-multiselect.css';

// Access authenticated user
const authUser = computed(() => usePage().props.auth.user);

// Reactive state
const rejectedEmails = ref([]);
const projects = ref([]);
const clients = ref([]);
const loading = ref(true);
const generalError = ref('');
const successMessage = ref('');

// Modal state
const showEditModal = ref(false);
const currentEmail = ref(null);
const editForm = reactive({
    project_id: '',
    client_id: '',
    client_ids: [], // Array for multi-select
    subject: '',
    body: '',
});
const editErrors = ref({});

// Computed properties
const assignedProjects = computed(() => {
    if (authUser.value.role === 'contractor') {
        return projects.value.filter(project =>
            project.users && project.users.some(user => user.id === authUser.value.id)
        );
    }
    return projects.value;
});

const selectedProjectClient = computed(() => {
    const project = projects.value.find(p => p.id === editForm.project_id);
    // Add null check for clients.value to ensure it's an array before calling find()
    return project && Array.isArray(clients.value) ? clients.value.find(c => c.id === project.client_id) : null;
});

// Filter clients based on selected project
const filteredClients = computed(() => {
    if (!editForm.project_id) {
        return [];
    }

    const selectedProject = projects.value.find(p => p.id === editForm.project_id);
    if (!selectedProject || !selectedProject.clients) {
        return [];
    }

    const projectClientIds = selectedProject.clients.map(c => c.id);
    return clients.value.filter(client => projectClientIds.includes(client.id));
});

// Parse the 'to' field, handling both plain strings, JSON-encoded arrays, and direct arrays
const getRecipientEmails = (email) => {
    if (!email || !email.to) return [];

    // If email.to is already an array, return it
    if (Array.isArray(email.to)) {
        return email.to;
    }

    // If email.to is a string, try to parse it as JSON
    if (typeof email.to === 'string') {
        try {
            // Try parsing as JSON (e.g., "[\"email@example.com\"]")
            const parsed = JSON.parse(email.to);
            return Array.isArray(parsed) ? parsed : [email.to];
        } catch (e) {
            // If parsing fails, assume it's a plain email string
            return [email.to];
        }
    }

    return [];
};

// Find client objects based on email addresses
const findClientsByEmails = (emailAddresses) => {
    if (!Array.isArray(clients.value)) return [];

    return emailAddresses.map(email => {
        const client = clients.value.find(c => c.email === email);
        return client ? client : null;
    }).filter(client => client !== null);
};

// Fetch initial data
const fetchInitialData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        // Use the simplified endpoint that returns only the required fields
        const emailsResponse = await window.axios.get('/api/emails/rejected-simplified');
        rejectedEmails.value = emailsResponse.data;
    } catch (error) {
        generalError.value = 'Failed to load data.';
        console.error('Error fetching initial data:', error);
        if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            generalError.value = 'You are not authorized to view this content or your session expired. Please log in.';
        }
    } finally {
        loading.value = false;
    }
};

// Open edit modal
const openEditModal = (email) => {
    currentEmail.value = email;
    editForm.subject = email.subject;
    editForm.body = email.body;

    // With simplified data structure, we don't need to set project_id, client_id, or client_ids

    editErrors.value = {};
    showEditModal.value = true;
};

// Save edited email
const saveEditedEmail = async () => {
    editErrors.value = {};
    generalError.value = '';

    try {
        // Only send subject and body in the payload
        // Users should not be allowed to edit clients or email addresses during rejection edit and resubmit process
        const payload = {
            subject: editForm.subject,
            body: editForm.body,
        };
        await window.axios.put(`/api/emails/${currentEmail.value.id}`, payload);
        successMessage.value = 'Email updated successfully! You can now resubmit it.';
        await fetchInitialData();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            editErrors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to update email.';
            console.error('Error updating email:', error);
        }
    }
};

// Resubmit email
const resubmitEmail = async () => {
    editErrors.value = {};
    generalError.value = '';
    try {
        // The resubmit endpoint only needs the email ID, which we still have in currentEmail.value.id
        await window.axios.post(`/api/emails/${currentEmail.value.id}/resubmit`);
        successMessage.value = 'Email resubmitted for approval successfully!';
        showEditModal.value = false;
        await fetchInitialData();
    } catch (error) {
        if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to resubmit email.';
            console.error('Error resubmitting email:', error);
        }
    }
};

// Watch for project selection change to clear selected clients
watch(() => editForm.project_id, (newProjectId) => {
    // Always clear selected clients when project changes
    editForm.client_ids = [];
});

// Lifecycle hook
onMounted(() => {
    fetchInitialData();
});
</script>

<template>
    <Head title="Rejected Emails" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Rejected Emails</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-2xl font-bold mb-4">Your Rejected Emails</h3>

                        <div v-if="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">{{ successMessage }}</span>
                        </div>
                        <div v-if="loading" class="text-gray-600">Loading rejected emails...</div>
                        <div v-else-if="generalError" class="text-red-600">{{ generalError }}</div>
                        <div v-else-if="rejectedEmails.length === 0" class="text-gray-600">No rejected emails found.</div>
                        <div v-else>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejection Reason</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted On</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="email in rejectedEmails" :key="email.id">
                                    <td class="px-6 py-4 truncate max-w-xs">{{ email.subject }}</td>
                                    <td class="px-6 py-4 truncate max-w-xs">{{ email.rejection_reason }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ new Date(email.created_at).toLocaleString() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <PrimaryButton @click="openEditModal(email)">View/Edit</PrimaryButton>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showEditModal" @close="showEditModal = false" max-width="3xl">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Edit Rejected Email</h2>
                <div v-if="currentEmail">
                    <p class="mb-2"><strong>Rejection Reason:</strong> {{ currentEmail.rejection_reason }}</p>
                    <hr class="my-4">
                    <form @submit.prevent="saveEditedEmail">
                        <!-- Project and client selection fields removed as per requirements -->
                        <!-- Users should not be allowed to edit clients or email addresses during rejection edit and resubmit process -->

                        <div class="mb-4">
                            <InputLabel for="subject" value="Subject" />
                            <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="editForm.subject" required />
                            <InputError :message="editErrors.subject ? editErrors.subject[0] : ''" class="mt-2" />
                        </div>

                        <div class="mb-6">
                            <InputLabel for="body" value="Email Body" />
                            <RichTextEditor
                                id="body"
                                v-model="editForm.body"
                                placeholder="Edit your email here..."
                                height="300px"
                            />
                            <InputError :message="editErrors.body ? editErrors.body[0] : ''" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end space-x-2">
                            <SecondaryButton @click="showEditModal = false">Cancel</SecondaryButton>
                            <PrimaryButton type="submit">Save Changes</PrimaryButton>
                            <PrimaryButton @click="resubmitEmail" v-if="successMessage" :disabled="!successMessage">Resubmit for Approval</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
.multiselect {
    min-height: 38px;
}
.multiselect__tags {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem;
}
.multiselect__tag {
    background: #e5e7eb;
    color: #374151;
}
.multiselect__tag-icon:after {
    color: #6b7280;
}
</style>
