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
    subject: '',
    body: '',
    to: '',
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
    return project ? clients.value.find(c => c.id === project.client_id) : null;
});

// Parse the 'to' field, handling both plain strings, JSON-encoded arrays, and direct arrays
const getRecipientEmail = (email) => {
    if (!email || !email.to) return '';

    // If email.to is already an array, return the first element
    if (Array.isArray(email.to)) {
        return email.to[0] || '';
    }

    // If email.to is a string, try to parse it as JSON
    if (typeof email.to === 'string') {
        try {
            // Try parsing as JSON (e.g., "[\"email@example.com\"]")
            const parsed = JSON.parse(email.to);
            return Array.isArray(parsed) ? parsed[0] || '' : email.to;
        } catch (e) {
            // If parsing fails, assume it's a plain email string
            return email.to;
        }
    }

    return '';
};

// Fetch initial data
const fetchInitialData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const [projectsResponse, clientsResponse, emailsResponse] = await Promise.all([
            window.axios.get('/api/projects'),
            window.axios.get('/api/clients'),
            window.axios.get('/api/emails/rejected'),
        ]);
        projects.value = projectsResponse.data;
        clients.value = clientsResponse.data;
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
    editForm.project_id = email.conversation.project_id;
    editForm.client_id = email.conversation.client_id;
    editForm.subject = email.subject;
    editForm.body = email.body;
    editForm.to = getRecipientEmail(email);
    editErrors.value = {};
    showEditModal.value = true;
};

// Save edited email
const saveEditedEmail = async () => {
    editErrors.value = {};
    generalError.value = '';
    try {
        const payload = {
            project_id: editForm.project_id,
            client_id: editForm.client_id,
            subject: editForm.subject,
            body: editForm.body,
            to: editForm.to,
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

// Watch for project selection change to update the email field
watch(() => editForm.project_id, (newProjectId) => {
    if (newProjectId && selectedProjectClient.value) {
        editForm.to = selectedProjectClient.value.email;
    }
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
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejection Reason</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted On</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="email in rejectedEmails" :key="email.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ email.conversation.project.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ email.conversation.client.name }}</td>
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
                        <div class="mb-4">
                            <InputLabel for="project_id" value="Select Project" />
                            <select id="project_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="editForm.project_id" required>
                                <option value="" disabled>Select a Project</option>
                                <option v-for="project in assignedProjects" :key="project.id" :value="project.id">
                                    {{ project.name }} (Client: {{ project.client ? project.client.name : 'N/A' }})
                                </option>
                            </select>
                            <InputError :message="editErrors.project_id ? editErrors.project_id[0] : ''" class="mt-2" />
                        </div>

                        <div class="mb-4">
                            <InputLabel for="to_client_email" value="To (Client Email)" />
                            <TextInput id="to_client_email" type="email" class="mt-1 block w-full bg-gray-100"
                                       v-model="editForm.to" readonly />
                            <InputError v-if="!selectedProjectClient && editForm.project_id" message="Selected project has no client or client email is missing." class="mt-2" />
                        </div>

                        <div class="mb-4">
                            <InputLabel for="subject" value="Subject" />
                            <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="editForm.subject" required />
                            <InputError :message="editErrors.subject ? editErrors.subject[0] : ''" class="mt-2" />
                        </div>

                        <div class="mb-6">
                            <InputLabel for="body" value="Email Body" />
                            <textarea id="body" rows="10" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="editForm.body" required></textarea>
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
