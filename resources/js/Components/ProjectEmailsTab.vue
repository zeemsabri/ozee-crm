<script setup>
import { ref, onMounted, reactive, computed, watch } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { usePermissions, useAuthUser } from '@/Directives/permissions'; // Import useAuthUser
import EmailActionModal from './ProjectsEmails/EmailActionModal.vue';
import ComposeEmailModal from './ProjectsEmails/ComponseEmailModal.vue';
import EditTemplateEmailModal from './ProjectsEmails/EditTemplateEmailModal.vue';
import EmailDetailsModal from './ProjectsEmails/EmailDetailsModal.vue';
import EmailList from './ProjectsEmails/EmailList.vue';

const props = defineProps({
    projectId: {
        type: Number,
        required: true,
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

// State for the new ComposeEmailModal
const showComposeEmailModal = ref(false);

// State for the EditTemplateEmailModal
const showEditTemplateEmailModal = ref(false);
const editTemplateEmailId = ref(null);
const editTemplateApiEndpoint = ref('');
const editTemplateSubmitButtonText = ref('');
const editTemplateSuccessMessage = ref('');
const editTemplateClientId = ref(null);

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

const viewEmail = async (email) => {
    selectedEmail.value = null; // Clear old email data
    showEmailDetailsModal.value = true;
    try {
        const response = await window.axios.get(`/api/emails/${email.id}`);
        // The API now returns an object with 'subject' and 'body_html'
        selectedEmail.value = {
            ...email, // Keep existing properties
            subject: response.data.subject,
            body: response.data.body_html,
        };
    } catch (error) {
        console.error('Failed to fetch full email details:', error);
        selectedEmail.value = {
            subject: 'Error',
            body: 'Failed to load email content.',
            created_at: new Date().toISOString(),
            status: 'error',
            sender: { name: 'System' },
        };
    }
};

// --- EmailActionModal related functions (now only Edit/Reject) ---

const openEditEmailModal = async (email) => {
    try {
        console.log('Opening email editor for email ID:', email.id);
        selectedEmail.value = email;

        // Check if this is a template-based email by fetching minimal data
        const response = await window.axios.get(`/api/emails/${email.id}/edit-content`);
        const emailData = response.data;

        if (emailData.template_id) {
            console.log('Opening template-based email editor for email ID:', email.id);

            // Set up the EditTemplateEmailModal
            editTemplateEmailId.value = email.id;
            editTemplateApiEndpoint.value = `/api/emails/${email.id}/edit-and-approve`;
            editTemplateSubmitButtonText.value = 'Approve & Send';
            editTemplateSuccessMessage.value = 'Email updated and approved successfully!';
            editTemplateClientId.value = emailData.client_id;

            // Show the template editor modal
            showEditTemplateEmailModal.value = true;
            showEmailDetailsModal.value = false; // Close details modal
        } else {
            // Regular email - use the standard EmailActionModal
            actionModalTitle.value = 'Edit and Approve Email';
            actionModalApiEndpoint.value = `/api/emails/${email.id}/edit-and-approve`;
            actionModalHttpMethod.value = 'post';
            actionModalSubmitButtonText.value = 'Approve & Send';
            actionModalSuccessMessage.value = 'Email updated and approved successfully!';

            // Show the modal - it will fetch its own data
            showActionModal.value = true;
            showEmailDetailsModal.value = false; // Close details modal
        }
    } catch (error) {
        console.error('Failed to determine email type:', error);
        emailError.value = 'Failed to load email for editing.';
    }
};

const openRejectEmailModal = (email) => {
    selectedEmail.value = email; // Keep selected email for context
    actionModalTitle.value = 'Reject Email';
    actionModalApiEndpoint.value = `/api/emails/${email.id}/reject`;
    actionModalHttpMethod.value = 'post';
    actionModalSubmitButtonText.value = 'Reject Email';
    actionModalSuccessMessage.value = 'Email rejected successfully!';
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

// Handlers for EditTemplateEmailModal
const handleEditTemplateEmailModalClose = () => {
    showEditTemplateEmailModal.value = false;
};

const handleEditTemplateEmailModalSubmitted = async (responseData) => {
    console.log('Template email edited and submitted successfully:', responseData);
    await fetchProjectEmails(); // Refresh email list
    showEditTemplateEmailModal.value = false; // Close the modal
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
<!--                <PrimaryButton class="bg-indigo-600 hover:bg-indigo-700 transition-colors" @click="showComposeEmailModal = true">-->
<!--                    Compose Email-->
<!--                </PrimaryButton>-->
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

        <EmailList
            :emails="emails"
            :loading="loadingEmails"
            :error="emailError"
            @view="viewEmail"
        />

        <EmailDetailsModal
            :show="showEmailDetailsModal"
            :email="selectedEmail"
            :can-approve-emails="canApproveEmails"
            @close="showEmailDetailsModal = false"
            @edit="openEditEmailModal"
            @reject="openRejectEmailModal"
        />

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
            :submit-button-text="actionModalSubmitButtonText"
            :success-message="actionModalSuccessMessage"
            :email-id="selectedEmail?.id"
            :project-id="projectId"
            @close="handleActionModalClose"
            @submitted="handleActionModalSubmitted"
            @error="(err) => console.error('EmailActionModal error:', err)"
            @fetchEmails="fetchProjectEmails"
        />

        <EditTemplateEmailModal
            :show="showEditTemplateEmailModal"
            title="Edit Template Email"
            :api-endpoint="editTemplateApiEndpoint"
            http-method="post"
            :submit-button-text="editTemplateSubmitButtonText"
            :success-message="editTemplateSuccessMessage"
            :email-id="editTemplateEmailId"
            :project-id="projectId"
            :client-id="editTemplateClientId"
            @close="handleEditTemplateEmailModalClose"
            @submitted="handleEditTemplateEmailModalSubmitted"
            @error="(err) => console.error('EditTemplateEmailModal error:', err)"
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
