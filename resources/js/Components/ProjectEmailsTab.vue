<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import InputError from '@/Components/InputError.vue';
import { usePermissions } from '@/Directives/permissions';
// Import the new OZeeMultiSelect component
import OZeeMultiSelect from '@/Components/CustomMultiSelect.vue';


const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    projectClients: { // Pass project.clients down
        type: Array,
        default: () => [],
    },
    canViewEmails: {
        type: Boolean,
        required: true,
    },
    canComposeEmails: {
        type: Boolean,
        required: true,
    },
    canApproveEmails: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(['emailsUpdated']);

const emails = ref([]);
const loadingEmails = ref(true);
const emailError = ref('');
const selectedEmail = ref(null);
const showEmailModal = ref(false);

// Email filters
const emailFilters = reactive({
    type: '',
    startDate: '',
    endDate: '',
    search: '',
});

let searchDebounceTimer = null;

// Email approval data
const showEditEmailModal = ref(false);
const showRejectEmailModal = ref(false);
const editEmailForm = reactive({
    subject: '',
    body: '',
});
const editEmailErrors = ref({});
const rejectionForm = reactive({
    rejection_reason: '',
});
const rejectionErrors = ref({});
const emailSuccessMessage = ref('');

// Email composition data
const showComposeEmailModal = ref(false);
const composeEmailForm = reactive({
    project_id: props.projectId,
    client_ids: [], // Initialize as an empty array for multiple selections
    subject: '',
    body: '',
});
const composeEmailErrors = ref({});

// Functions
const applyFilters = () => {
    fetchProjectEmails();
};

const resetEmailFilters = () => {
    emailFilters.type = '';
    emailFilters.startDate = '';
    emailFilters.endDate = '';
    emailFilters.search = '';
    fetchProjectEmails();
};

const debounceSearch = () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        applyFilters();
    }, 500);
};

const fetchProjectEmails = async () => {
    loadingEmails.value = true;
    emailError.value = '';
    try {
        const params = new URLSearchParams();
        if (emailFilters.type) { params.append('type', emailFilters.type); }
        if (emailFilters.startDate) { params.append('start_date', emailFilters.startDate); }
        if (emailFilters.endDate) { params.append('end_date', emailFilters.endDate); }
        if (emailFilters.search) { params.append('search', emailFilters.search); }

        const queryString = params.toString();
        const url = `/api/projects/${props.projectId}/emails-simplified${queryString ? `?${queryString}` : ''}`;

        const response = await window.axios.get(url);
        emails.value = response.data;
        emit('emailsUpdated', emails.value); // Emit to parent for stats
    } catch (error) {
        emailError.value = 'Failed to load email data.';
        console.error('Error fetching project emails:', error);
    } finally {
        loadingEmails.value = false;
    }
};

const viewEmail = (email) => {
    selectedEmail.value = email;
    showEmailModal.value = true;
};

const approveEmail = async (email) => {
    emailSuccessMessage.value = '';
    emailError.value = '';
    try {
        await window.axios.post(`/api/emails/${email.id}/approve`);
        emailSuccessMessage.value = 'Email approved successfully!';
        await fetchProjectEmails();
        showEmailModal.value = false;
    } catch (error) {
        if (error.response && error.response.data.message) {
            emailError.value = error.response.data.message;
        } else {
            emailError.value = 'Failed to approve email.';
            console.error('Error approving email:', error);
        }
    }
};

const openEditEmailModal = (email) => {
    selectedEmail.value = email;
    editEmailForm.subject = email.subject;
    editEmailForm.body = email.body;
    editEmailErrors.value = {};
    showEditEmailModal.value = true;
    showEmailModal.value = false;
};

const saveAndApproveEmail = async () => {
    editEmailErrors.value = {};
    emailError.value = '';
    emailSuccessMessage.value = '';
    try {
        const payload = {
            subject: editEmailForm.subject,
            body: editEmailForm.body,
        };
        await window.axios.post(`/api/emails/${selectedEmail.value.id}/edit-and-approve`, payload);
        emailSuccessMessage.value = 'Email updated and approved successfully!';
        showEditEmailModal.value = false;
        await fetchProjectEmails();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            editEmailErrors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            emailError.value = error.response.data.message;
        } else {
            emailError.value = 'Failed to update and approve email.';
            console.error('Error updating and approving email:', error);
        }
    }
};

const openRejectEmailModal = (email) => {
    selectedEmail.value = email;
    rejectionForm.rejection_reason = '';
    rejectionErrors.value = {};
    showRejectEmailModal.value = true;
    showEmailModal.value = false;
};

const submitRejection = async () => {
    rejectionErrors.value = {};
    emailError.value = '';
    emailSuccessMessage.value = '';
    try {
        await window.axios.post(`/api/emails/${selectedEmail.value.id}/reject`, { rejection_reason: rejectionForm.rejection_reason });
        emailSuccessMessage.value = 'Email rejected successfully!';
        rejectionForm.rejection_reason = '';
        showRejectEmailModal.value = false;
        await fetchProjectEmails();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            rejectionErrors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            emailError.value = error.response.data.message;
        } else {
            emailError.value = 'Failed to reject email.';
            console.error('Error rejecting email:', error);
        }
    }
};

const openComposeEmailModal = () => {
    composeEmailForm.project_id = props.projectId;
    composeEmailForm.client_ids = []; // Ensure it's reset to an empty array
    composeEmailForm.subject = '';
    composeEmailForm.body = '';
    composeEmailErrors.value = {};
    emailSuccessMessage.value = '';
    showComposeEmailModal.value = true;
};

const submitEmailForApproval = async () => {
    composeEmailErrors.value = {};
    emailError.value = '';
    emailSuccessMessage.value = '';

    if (!composeEmailForm.client_ids || composeEmailForm.client_ids.length === 0) {
        composeEmailErrors.client_ids = ['Please select at least one client.'];
        return;
    }

    try {
        // client_ids are already an array of IDs from the custom multi-select component
        const formattedClientIds = composeEmailForm.client_ids.map(id => ({ id }));

        const payload = {
            project_id: composeEmailForm.project_id,
            client_ids: formattedClientIds,
            subject: composeEmailForm.subject,
            body: composeEmailForm.body,
            status: 'pending_approval',
        };

        await window.axios.post('/api/emails', payload);

        emailSuccessMessage.value = 'Email submitted for approval successfully!';
        composeEmailForm.client_ids = [];
        composeEmailForm.subject = '';
        composeEmailForm.body = '';
        composeEmailErrors.value = {};
        showComposeEmailModal.value = false;
        await fetchProjectEmails();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            composeEmailErrors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            emailError.value = error.response.data.message;
        } else {
            emailError.value = 'Failed to submit email. An unexpected error occurred.';
            console.error('Error submitting email:', error);
        }
    }
};

onMounted(() => {
    if (props.canViewEmails) {
        fetchProjectEmails();
    }
});
</script>

<template>
    <div v-if="canViewEmails" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-gray-900">Email Communication</h4>
            <div v-if="canComposeEmails" class="flex gap-3">
                <PrimaryButton class="bg-indigo-600 hover:bg-indigo-700 transition-colors" @click="openComposeEmailModal">
                    Compose Email
                </PrimaryButton>
            </div>
            <div v-if="emailSuccessMessage" class="mt-2 bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded relative" role="alert">
                <span class="block sm:inline">{{ emailSuccessMessage }}</span>
            </div>
        </div>

        <!-- Email Filters -->
        <div class="mb-4 bg-gray-50 p-4 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <InputLabel for="typeFilter" value="Type" />
                    <select id="typeFilter" v-model="emailFilters.type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" @change="applyFilters">
                        <option value="">All Types</option>
                        <option value="sent">Sent</option>
                        <option value="received">Received</option>
                    </select>
                </div>

                <div>
                    <InputLabel for="startDate" value="From Date" />
                    <TextInput type="date" id="startDate" v-model="emailFilters.startDate" class="mt-1 block w-full" @change="applyFilters" />
                </div>
                <div>
                    <InputLabel for="endDate" value="To Date" />
                    <TextInput type="date" id="endDate" v-model="emailFilters.endDate" class="mt-1 block w-full" @change="applyFilters" />
                </div>

                <div>
                    <InputLabel for="searchFilter" value="Search Content" />
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <TextInput type="text" id="searchFilter" v-model="emailFilters.search" class="block w-full pr-10" placeholder="Search in email content..." @input="debounceSearch" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3 flex justify-end">
                <button type="button" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" @click="resetEmailFilters">
                    Reset Filters
                </button>
            </div>
        </div>

        <!-- Loading and Error States -->
        <div v-if="loadingEmails" class="text-center text-gray-600 text-sm animate-pulse py-4">
            Loading email data...
        </div>
        <div v-else-if="emailError" class="text-center text-red-600 text-sm font-medium py-4">
            {{ emailError }}
        </div>
        <div v-else-if="emails.length" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="email in emails" :key="email.id" class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-sm text-gray-900">{{ email.subject }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ email.sender?.name || 'N/A' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ new Date(email.created_at).toLocaleDateString() }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <span
                            :class="{
                                'px-2 py-1 rounded-full text-xs font-medium': true,
                                'bg-blue-100 text-blue-800': email.type === 'sent',
                                'bg-purple-100 text-purple-800': email.type === 'received'
                            }"
                        >
                            {{ email.type ? email.type.toUpperCase() : 'N/A' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <span
                            :class="{
                                'px-2 py-1 rounded-full text-xs font-medium': true,
                                'bg-green-100 text-green-800': email.status === 'sent',
                                'bg-yellow-100 text-yellow-800': email.status === 'pending_approval',
                                'bg-red-100 text-red-800': email.status === 'rejected',
                                'bg-gray-100 text-gray-800': email.status === 'draft'
                            }"
                        >
                            {{ email.status.replace('_', ' ').toUpperCase() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <SecondaryButton class="text-indigo-600 hover:text-indigo-800" @click="viewEmail(email)">
                            View
                        </SecondaryButton>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="text-gray-400 text-sm">No email communication found.</p>

        <!-- Email View Modal -->
        <Modal :show="showEmailModal" @close="showEmailModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Email Details</h3>
                    <button @click="showEmailModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="selectedEmail" class="space-y-4">
                    <div class="border-b pb-4">
                        <h4 class="text-xl font-medium text-gray-900 mb-2">{{ selectedEmail.subject }}</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-600">From: <span class="text-gray-900">{{ selectedEmail.sender?.name || 'N/A' }}</span></p>
                                <p class="text-gray-600 mt-1">Status:
                                    <span :class="{
                                            'px-2 py-1 rounded-full text-xs font-medium': true,
                                            'bg-green-100 text-green-800': selectedEmail.status === 'sent',
                                            'bg-yellow-100 text-yellow-800': selectedEmail.status === 'pending_approval',
                                            'bg-red-100 text-red-800': selectedEmail.status === 'rejected',
                                            'bg-gray-100 text-gray-800': selectedEmail.status === 'draft'
                                        }">
                                        {{ selectedEmail.status.replace('_', ' ').toUpperCase() }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-gray-600">Date: <span class="text-gray-900">{{ new Date(selectedEmail.created_at).toLocaleString() }}</span></p>
                            </div>
                        </div>
                    </div>

                    <div class="prose max-w-none">
                        <div v-html="selectedEmail.body"></div>
                    </div>

                    <div v-if="selectedEmail.rejection_reason" class="mt-4 p-4 bg-red-50 rounded-md">
                        <h5 class="font-medium text-red-800">Rejection Reason:</h5>
                        <p class="text-red-700">{{ selectedEmail.rejection_reason }}</p>
                    </div>

                    <div v-if="selectedEmail.approver" class="mt-4 text-sm text-gray-600">
                        <p>Approved/Rejected by: {{ selectedEmail.approver.name }}</p>
                        <p v-if="selectedEmail.sent_at">Sent at: {{ new Date(selectedEmail.sent_at).toLocaleString() }}</p>
                    </div>

                    <div v-if="selectedEmail.status === 'pending_approval' && canApproveEmails" class="mt-6 flex justify-end space-x-2">
                        <PrimaryButton @click="approveEmail(selectedEmail)" class="bg-green-600 hover:bg-green-700">Approve</PrimaryButton>
                        <PrimaryButton @click="openEditEmailModal(selectedEmail)" class="bg-blue-600 hover:bg-blue-700">Edit & Approve</PrimaryButton>
                        <SecondaryButton @click="openRejectEmailModal(selectedEmail)" class="text-red-600 hover:text-red-800">Reject</SecondaryButton>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Edit Email Modal -->
        <Modal :show="showEditEmailModal" @close="showEditEmailModal = false" max-width="3xl">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Edit and Approve Email</h3>
                    <button @click="showEditEmailModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="selectedEmail" class="space-y-4">
                    <form @submit.prevent="saveAndApproveEmail">
                        <div v-if="emailError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">{{ emailError }}</span>
                        </div>

                        <div class="mb-4">
                            <InputLabel for="subject" value="Subject" />
                            <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="editEmailForm.subject" required />
                            <InputError :message="editEmailErrors.subject ? editEmailErrors.subject[0] : ''" class="mt-2" />
                        </div>

                        <div class="mb-6">
                            <InputLabel for="body" value="Email Body" />
                            <RichTextEditor id="body" v-model="editEmailForm.body" placeholder="Edit your email here..." height="300px" />
                            <InputError :message="editEmailErrors.body ? editEmailErrors.body[0] : ''" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end space-x-2">
                            <SecondaryButton @click="showEditEmailModal = false">Cancel</SecondaryButton>
                            <PrimaryButton type="submit" class="bg-green-600 hover:bg-green-700">Save & Approve</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>

        <!-- Reject Email Modal -->
        <Modal :show="showRejectEmailModal" @close="showRejectEmailModal = false" max-width="2xl">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Reject Email</h3>
                    <button @click="showRejectEmailModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="selectedEmail" class="space-y-4">
                    <form @submit.prevent="submitRejection">
                        <div v-if="emailError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">{{ emailError }}</span>
                        </div>

                        <div class="mb-6">
                            <InputLabel for="rejection_reason" value="Rejection Reason" />
                            <textarea id="rejection_reason" rows="5" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="rejectionForm.rejection_reason" required placeholder="Please provide a reason for rejecting this email (minimum 10 characters)"></textarea>
                            <InputError :message="rejectionErrors.rejection_reason ? rejectionErrors.rejection_reason[0] : ''" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end space-x-2">
                            <SecondaryButton @click="showRejectEmailModal = false">Cancel</SecondaryButton>
                            <PrimaryButton type="submit" class="bg-red-600 hover:bg-red-700">Reject Email</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>

        <!-- Compose Email Modal -->
        <Modal :show="showComposeEmailModal" @close="showComposeEmailModal = false" max-width="3xl">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Compose New Email</h3>
                    <button @click="showComposeEmailModal = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitEmailForApproval">
                    <div v-if="emailError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline">{{ emailError }}</span>
                    </div>

                    <div class="mb-4">
                        <InputLabel for="client_ids" value="To (Clients)" />
                        <OZeeMultiSelect
                            v-model="composeEmailForm.client_ids"
                            :options="projectClients"
                            placeholder="Select one or more clients"
                            label-key="name"
                            track-by="id"
                        />
                        <InputError :message="composeEmailErrors.client_ids ? composeEmailErrors.client_ids[0] : ''" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <InputLabel for="subject" value="Subject" />
                        <TextInput id="subject" type="text" class="mt-1 block w-full" v-model="composeEmailForm.subject" required />
                        <InputError :message="composeEmailErrors.subject ? composeEmailErrors.subject[0] : ''" class="mt-2" />
                    </div>

                    <div class="mb-6">
                        <InputLabel for="body" value="Email Body" />
                        <RichTextEditor id="body" v-model="composeEmailForm.body" placeholder="Compose your email here..." height="300px" />
                        <InputError :message="composeEmailErrors.body ? composeEmailErrors.body[0] : ''" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end">
                        <SecondaryButton @click="showComposeEmailModal = false" class="mr-2">Cancel</SecondaryButton>
                        <PrimaryButton type="submit">Submit for Approval</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>

<style>
/* No specific Multiselect styles needed anymore as it's custom. */
/* You can remove these if this is the only place vue-multiselect was used. */
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
