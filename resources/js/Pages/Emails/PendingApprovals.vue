<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, reactive } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';

// Access authenticated user
const authUser = computed(() => usePage().props.auth.user);

// Reactive state
const pendingEmails = ref([]);
const projects = ref([]);
const clients = ref([]);
const loading = ref(true);
const generalError = ref('');
const successMessage = ref('');

// Modal state
const showEditModal = ref(false);
const showRejectModal = ref(false);
const currentEmail = ref(null);
const editForm = reactive({
    project_id: '',
    client_id: '',
    subject: '',
    body: '',
});
const editErrors = ref({});
const rejectionForm = reactive({
    rejection_reason: '',
});
const rejectionErrors = ref({});

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

// Parse the 'to' field, handling both plain strings and JSON-encoded arrays
const getRecipientEmail = (email) => {
    if (!email || !email.to) return '';
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
        // Use the simplified endpoint that returns only the required fields
        const emailsResponse = await window.axios.get('/api/emails/pending-approval-simplified');
        pendingEmails.value = emailsResponse.data;

        // We still need to fetch projects for the edit modal
        const projectsResponse = await window.axios.get('/api/projects');
        projects.value = projectsResponse.data;
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
    // With the simplified API, we need to handle the data differently
    editForm.project_id = email.project?.id;
    editForm.subject = email.subject;
    editForm.body = email.body;
    editErrors.value = {};
    showEditModal.value = true;
};

// Save and approve email
const saveAndApproveEmail = async () => {
    editErrors.value = {};
    generalError.value = '';
    try {
        const payload = {
            project_id: editForm.project_id,
            subject: editForm.subject,
            body: editForm.body,
        };
        await window.axios.post(`/api/emails/${currentEmail.value.id}/edit-and-approve`, payload);
        successMessage.value = 'Email updated and approved successfully!';
        showEditModal.value = false;
        await fetchInitialData();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            editErrors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to update and approve email.';
            console.error('Error updating and approving email:', error);
        }
    }
};

// Approve email without changes
const approveEmail = async (email) => {
    generalError.value = '';
    try {
        await window.axios.post(`/api/emails/${email.id}/approve`);
        successMessage.value = 'Email approved successfully!';
        await fetchInitialData();
    } catch (error) {
        if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to approve email.';
            console.error('Error approving email:', error);
        }
    }
};

// Open reject modal
const openRejectModal = (email) => {
    currentEmail.value = email;
    rejectionForm.rejection_reason = '';
    rejectionErrors.value = {};
    showRejectModal.value = true;
};

// Submit rejection
const submitRejection = async () => {
    rejectionErrors.value = {};
    generalError.value = '';
    try {
        await window.axios.post(`/api/emails/${currentEmail.value.id}/reject`, {
            rejection_reason: rejectionForm.rejection_reason,
        });
        successMessage.value = 'Email rejected successfully!';
        rejectionForm.rejection_reason = '';
        showRejectModal.value = false;
        await fetchInitialData();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            rejectionErrors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to reject email.';
            console.error('Error rejecting email:', error);
        }
    }
};

// Lifecycle hook
onMounted(() => {
    fetchInitialData();
});
</script>

<template>
    <Head title="Pending Approvals" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pending Email Approvals</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-2xl font-bold mb-4">Emails Pending Approval</h3>

                        <div v-if="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">{{ successMessage }}</span>
                        </div>
                        <div v-if="loading" class="text-gray-600">Loading pending emails...</div>
                        <div v-else-if="generalError" class="text-red-600">{{ generalError }}</div>
                        <div v-else-if="pendingEmails.length === 0" class="text-gray-600">No pending emails found.</div>
                        <div v-else>
                            <div class="overflow-x-auto">
                                <table class="w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sender</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted On</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="email in pendingEmails" :key="email.id">
                                        <td class="px-4 py-4 whitespace-nowrap">{{ email.project?.name || 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap">{{ email.client?.name || 'N/A' }}</td>
                                        <td class="px-4 py-4 truncate max-w-xs">{{ email.subject }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap">{{ email.sender?.name || 'N/A' }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap">{{ new Date(email.created_at).toLocaleString() }}</td>
                                        <td class="px-4 py-4 whitespace-nowrap flex space-x-2">
                                            <PrimaryButton @click="approveEmail(email)" class="text-xs px-2 py-1">Approve</PrimaryButton>
                                            <PrimaryButton @click="openEditModal(email)" class="text-xs px-2 py-1">Edit & Approve</PrimaryButton>
                                            <SecondaryButton @click="openRejectModal(email)" class="text-xs px-2 py-1">Reject</SecondaryButton>
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

        <Modal :show="showEditModal" @close="showEditModal = false" max-width="3xl">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Edit and Approve Email</h2>
                <div v-if="currentEmail">
                    <form @submit.prevent="saveAndApproveEmail">
                        <div class="mb-4">
                            <InputLabel for="project_id" value="Project" />
                            <select id="project_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="editForm.project_id" required>
                                <option value="" disabled>Select a Project</option>
                                <option v-for="project in assignedProjects" :key="project.id" :value="project.id">
                                    {{ project.name }}
                                </option>
                            </select>
                            <InputError :message="editErrors.project_id ? editErrors.project_id[0] : ''" class="mt-2" />
                        </div>

                        <div class="mb-4">
                            <InputLabel for="client_name" value="Client" />
                            <div id="client_name" class="mt-1 p-2 border border-gray-300 rounded-md bg-gray-50">
                                {{ currentEmail.client?.name || 'N/A' }}
                            </div>
                        </div>

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
                            <PrimaryButton type="submit">Save & Approve</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>

        <Modal :show="showRejectModal" @close="showRejectModal = false" max-width="2xl">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Reject Email</h2>
                <div v-if="currentEmail">
                    <form @submit.prevent="submitRejection">
                        <div class="mb-6">
                            <InputLabel for="rejection_reason" value="Rejection Reason" />
                            <textarea id="rejection_reason" rows="5" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="rejectionForm.rejection_reason" required placeholder="Please provide a reason for rejecting this email (minimum 10 characters)"></textarea>
                            <InputError :message="rejectionErrors.rejection_reason ? rejectionErrors.rejection_reason[0] : ''" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end space-x-2">
                            <SecondaryButton @click="showRejectModal = false">Cancel</SecondaryButton>
                            <PrimaryButton type="submit" class="bg-red-600 hover:bg-red-700">Reject Email</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
