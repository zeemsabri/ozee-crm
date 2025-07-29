<script setup>
import { ref, onMounted, reactive, computed, watch } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue'; // Keep for view-only modal
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { usePermissions, useAuthUser } from '@/Directives/permissions'; // Import useAuthUser
import EmailActionModal from './ProjectsEmails/EmailActionModal.vue';
// No longer import useEmailSignature or useEmailTemplate here as they are now in ComposeEmailModal
// import { useEmailSignature } from '@/Composables/useEmailSignature';
// import { useEmailTemplate } from '@/Composables/useEmailTemplate';

// NEW: Import the standalone ComposeEmailModal
import ComposeEmailModal from './ProjectsEmails/ComponseEmailModal.vue';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
    },
    // projectClients is no longer passed to ComposeEmailModal directly from here
    // as ComposeEmailModal will fetch its own clients.
    // However, ProjectEmailsTab itself still needs it for general context if any
    // other part of its functionality depends on it (e.g., if you later add filtering by client).
    // For now, removing it from here as it's primarily used by compose.
    // projectClients: {
    //     type: Array,
    //     default: () => [],
    // },
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
    userProjectRole: { // Keep as it's used for permissions
        type: Object,
        required: true
    },
    openCompose : { // This prop will now control the new ComposeEmailModal
        type: Boolean,
        required: false,
        default: false
    }
});

const emit = defineEmits(['emailsUpdated', 'resetOpenCompose']);

const emails = ref([]);
const loadingEmails = ref(true);
const emailError = ref(''); // General error for fetching emails

const selectedEmail = ref(null);
const showEmailDetailsModal = ref(false); // For viewing email details

// Reactive state for the dynamic EmailActionModal (now only for Edit/Reject)
const showActionModal = ref(false);
const actionModalTitle = ref('');
const actionModalApiEndpoint = ref('');
const actionModalHttpMethod = ref('post');
const actionModalSubmitButtonText = ref('');
const actionModalSuccessMessage = ref('');
const actionModalInitialData = reactive({});

// State for the new ComposeEmailModal
const showComposeEmailModal = ref(false);

// Get authenticated user (still needed for permissions, not for signature directly here)
const authUser = useAuthUser();

// Email filters
const emailFilters = reactive({
    type: '',
    startDate: '',
    endDate: '',
    search: '',
});

let searchDebounceTimer = null;

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
    showEmailDetailsModal.value = true;
};

// --- EmailActionModal related functions (now only Edit/Reject) ---

const openEditEmailModal = (email) => {
    selectedEmail.value = email; // Keep selected email for context
    actionModalTitle.value = 'Edit and Approve Email';
    actionModalApiEndpoint.value = `/api/emails/${email.id}/edit-and-approve`;
    actionModalHttpMethod.value = 'post';
    actionModalSubmitButtonText.value = 'Approve & Send';
    actionModalSuccessMessage.value = 'Email updated and approved successfully!';
    // Initialize form data for editing
    let emailBody = email.body || '';
    Object.assign(actionModalInitialData, {
        subject: email.subject,
        body: emailBody,
    });
    showActionModal.value = true;
    showEmailDetailsModal.value = false; // Close details modal
};

const openRejectEmailModal = (email) => {
    selectedEmail.value = email; // Keep selected email for context
    actionModalTitle.value = 'Reject Email';
    actionModalApiEndpoint.value = `/api/emails/${email.id}/reject`;
    actionModalHttpMethod.value = 'post';
    actionModalSubmitButtonText.value = 'Reject Email';
    actionModalSuccessMessage.value = 'Email rejected successfully!';
    // Initialize form data for rejection
    Object.assign(actionModalInitialData, {
        rejection_reason: '',
    });
    showActionModal.value = true;
    showEmailDetailsModal.value = false; // Close details modal
};

// Handle submission from EmailActionModal (Edit/Reject)
const handleActionModalSubmitted = async (responseData) => {
    console.log('Form submitted successfully:', responseData);
    await fetchProjectEmails(); // Refresh email list
    showActionModal.value = false; // Close the form modal
    // EmailActionModal (via BaseFormModal) handles success message internally
};

const handleActionModalClose = () => {
    showActionModal.value = false;
    // Any cleanup or state reset if needed when the modal closes
};

// Handler for the new ComposeEmailModal
const handleComposeEmailModalClose = () => {
    showComposeEmailModal.value = false;
    emit('resetOpenCompose'); // Important to reset the parent's `openCompose` prop
};

const handleComposeEmailModalSubmitted = async (responseData) => {
    console.log('Compose email submitted successfully:', responseData);
    await fetchProjectEmails(); // Refresh email list
    showComposeEmailModal.value = false; // Close the modal
    emit('resetOpenCompose'); // Important to reset the parent's `openCompose` prop
};

watch(() => props.openCompose, (newValue) => {
    console.log('Watcher triggered: openCompose changed to', newValue);
    if (newValue) {
        console.log('Opening compose email modal');
        showComposeEmailModal.value = true; // Open the new compose modal
        // The resetOpenCompose event is now emitted by handleComposeEmailModalClose/Submitted
        // to ensure the parent's prop is reset after the modal has handled its lifecycle.
    }
});

onMounted(() => {
    if (props.canViewEmails) {
        fetchProjectEmails();
    }
    // Initial check for openCompose when component mounts
    if(props.openCompose) {
        showComposeEmailModal.value = true;
    }
});

</script>

<template>
    <div v-if="canViewEmails" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-gray-900">Email Communication</h4>
            <div v-if="canComposeEmails" class="flex gap-3">
                <PrimaryButton class="bg-indigo-600 hover:bg-indigo-700 transition-colors" @click="showComposeEmailModal = true">
                    Compose Email
                </PrimaryButton>
            </div>
            <div v-if="emailError" class="mt-2 bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded relative" role="alert">
                <span class="block sm:inline">{{ emailError }}</span>
            </div>
        </div>

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
                                'bg-blue-200 text-blue-900': email.status === 'pending_approval_received',
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

        <Modal :show="showEmailDetailsModal" @close="showEmailDetailsModal = false">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Email Details</h3>
                    <button @click="showEmailDetailsModal = false" class="text-gray-400 hover:text-gray-500">
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

                    <div v-if="(selectedEmail.status === 'pending_approval' || selectedEmail.status === 'pending_approval_received') && canApproveEmails" class="mt-6 flex justify-end space-x-2">
                        <PrimaryButton @click="openEditEmailModal(selectedEmail)" class="bg-blue-600 hover:bg-blue-700">Edit & Approve</PrimaryButton>
                        <SecondaryButton @click="openRejectEmailModal(selectedEmail)" class="text-red-600 hover:text-red-800">Reject</SecondaryButton>
                    </div>
                </div>
            </div>
        </Modal>

        <ComposeEmailModal
            :show="showComposeEmailModal"
            :project-id="projectId"
            :user-project-role="userProjectRole"
            @close="handleComposeEmailModalClose"
            @submitted="handleComposeEmailModalSubmitted"
            @error="(err) => console.error('ComposeEmailModal error:', err)"
        />

        <EmailActionModal
            :show="showActionModal"
            :title="actionModalTitle"
            :api-endpoint="actionModalApiEndpoint"
            :http-method="actionModalHttpMethod"
            :initial-form-data="actionModalInitialData"
            :submit-button-text="actionModalSubmitButtonText"
            :success-message="actionModalSuccessMessage"
            :email-id="selectedEmail?.id"
            :project-id="projectId"
            @close="handleActionModalClose"
            @submitted="handleActionModalSubmitted"
            @error="(err) => console.error('EmailActionModal error:', err)"
        />
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

/* Custom style to attempt to make signature non-selectable/non-editable */
.unselectable-signature {
    user-select: none; /* Standard property */
    -webkit-user-select: none; /* Safari */
    -moz-user-select: none; /* Firefox */
    -ms-user-select: none; /* IE/Edge */
    pointer-events: none; /* Prevent clicks/interactions within the div */
}
</style>
