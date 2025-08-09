<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePermissions } from '@/Directives/permissions';
import TabPanel from './Components/TabPanel.vue';
import NewEmailsTab from './Components/NewEmailsTab.vue';
import AllEmailsTab from './Components/AllEmailsTab.vue';
import WaitingApprovalTab from './Components/WaitingApprovalTab.vue';
import EmailDetailsModal from '@/Components/ProjectsEmails/EmailDetailsModal.vue';
import EmailActionModal from '@/Components/ProjectsEmails/EmailActionModal.vue';
import EditTemplateEmailModal from '@/Components/ProjectsEmails/EditTemplateEmailModal.vue';
import ComposeEmailModal from "@/Components/ComposeEmailModal.vue";
import axios from 'axios';

const { canDo } = usePermissions();
const hasViewEmailsPermission = canDo('view_emails').value;
const hasApproveEmailsPermission = canDo('approve_emails').value;
const hasComposeEmailsPermission = canDo('compose_emails').value;

// Variables for ComposeEmailModal
const projectClients = ref([]);
const projectId = ref(null);
const showComposeEmailModal = ref(false);

// Track filters for the All Emails tab
const allEmailsFilters = ref({
    type: '',
    status: '',
    startDate: '',
    endDate: '',
    search: '',
    project_id: '',
    sender_id: '',
});

// Computed property to check if all filters are cleared
const isAllFiltersCleared = computed(() => {
    return !allEmailsFilters.value.type &&
        !allEmailsFilters.value.status &&
        !allEmailsFilters.value.startDate &&
        !allEmailsFilters.value.endDate &&
        !allEmailsFilters.value.search &&
        !allEmailsFilters.value.project_id &&
        !allEmailsFilters.value.sender_id;
});

// Tab management
const activeTab = ref('new');
const tabs = [
    { id: 'new', label: 'New Emails', component: NewEmailsTab, visible: hasViewEmailsPermission },
    { id: 'all', label: 'All Emails', component: AllEmailsTab, visible: hasViewEmailsPermission },
    { id: 'waiting', label: 'Waiting Approval', component: WaitingApprovalTab, visible: hasApproveEmailsPermission },
];

// Refs for tab components to allow manual refreshing
const tabRefs = ref({
    new: null,
    all: null,
    waiting: null,
});

// Email details modal
const selectedEmail = ref(null);
const showEmailDetailsModal = ref(false);

// Action modal (for edit/reject)
const showActionModal = ref(false);
const actionModalTitle = ref('');
const actionModalApiEndpoint = ref('');
const actionModalHttpMethod = ref('post');
const actionModalSubmitButtonText = ref('');
const actionModalSuccessMessage = ref('');

// Template email modal
const showEditTemplateEmailModal = ref(false);
const editTemplateEmailId = ref(null);
const editTemplateApiEndpoint = ref('');
const editTemplateSubmitButtonText = ref('');
const editTemplateSuccessMessage = ref('');
const editTemplateClientId = ref(null);
const showApprovalButtons = ref(false);

// Event handlers
const handleViewEmail = async (email) => {
    selectedEmail.value = email;
    showEmailDetailsModal.value = true;
    showApprovalButtons.value = email.can_approve ?? false;
    // Mark the email as read
    try {
        await axios.post(`/api/inbox/emails/${email.id}/mark-as-read`);
    } catch (error) {
        console.error('Failed to mark email as read:', error);
    }
};

const handleCloseEmailDetails = () => {
    showEmailDetailsModal.value = false;
    selectedEmail.value = null;
    // Refresh the active tab when an email is closed to update the list
    refreshActiveTab();
};

const openEditEmailModal = async (email) => {
    try {
        selectedEmail.value = email;

        // Check if this is a template-based email
        const response = await axios.get(`/api/emails/${email.id}/edit-content`);
        const emailData = response.data;

        if (emailData.template_id) {
            // Set up the EditTemplateEmailModal
            editTemplateEmailId.value = email.id;
            editTemplateApiEndpoint.value = `/api/emails/${email.id}/edit-and-approve`;

            editTemplateSubmitButtonText.value = 'Approve & Send';

            if(emailData.type === 'received') {
                editTemplateSubmitButtonText.value = 'Approve';
            }

            editTemplateSuccessMessage.value = 'Email updated and approved successfully!';
            editTemplateClientId.value = emailData.client_id;

            // Show the template editor modal
            showEditTemplateEmailModal.value = true;
            showEmailDetailsModal.value = false;
        } else {
            // Regular email - use the standard EmailActionModal
            actionModalTitle.value = 'Edit and Approve Email';
            actionModalApiEndpoint.value = `/api/emails/${email.id}/edit-and-approve`;
            actionModalHttpMethod.value = 'post';

            actionModalSubmitButtonText.value = 'Approve & Send';

            if(emailData.type === 'received') {
                actionModalSubmitButtonText.value = 'Approve';
            }

            actionModalSuccessMessage.value = 'Email updated and approved successfully!';

            // Show the modal
            showActionModal.value = true;
            showEmailDetailsModal.value = false;
        }
    } catch (error) {
        console.error('Failed to determine email type:', error);
    }
};

const openRejectEmailModal = (email) => {
    selectedEmail.value = email;
    actionModalTitle.value = 'Reject Email';
    actionModalApiEndpoint.value = `/api/emails/${email.id}/reject`;
    actionModalHttpMethod.value = 'post';
    actionModalSubmitButtonText.value = 'Reject Email';
    actionModalSuccessMessage.value = 'Email rejected successfully!';
    showActionModal.value = true;
    showEmailDetailsModal.value = false;
};

const handleActionModalSubmitted = () => {
    showActionModal.value = false;
    refreshActiveTab();
};

const handleActionModalClose = () => {
    showActionModal.value = false;
    refreshActiveTab();
};

const handleEditTemplateEmailModalSubmitted = () => {
    showEditTemplateEmailModal.value = false;
    refreshActiveTab();
};

const handleEditTemplateEmailModalClose = () => {
    showEditTemplateEmailModal.value = false;
    refreshActiveTab();
};

// Manually trigger a refresh of the active tab's data
const refreshActiveTab = () => {
    const tabComponent = tabRefs.value[activeTab.value];
    if (tabComponent && typeof tabComponent.refresh === 'function') {
        tabComponent.refresh();
    } else {
        console.error(`Cannot refresh tab: ${activeTab.value} Component reference not found or refresh method is missing.`);
    }
};

// Handle filters changed event from AllEmailsTab
const handleFiltersChanged = (filters) => {
    allEmailsFilters.value = filters;
};

// Switch tab handler
const switchTab = (tabId) => {
    activeTab.value = tabId;
};

onMounted(() => {
    // Check if user has permission to view emails
    if (!hasViewEmailsPermission) {
        // Redirect to dashboard or show error
        window.location.href = '/dashboard';
    }
});
</script>

<template>
    <Head title="Inbox" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Inbox
                </h2>
                <button
                    v-if="hasComposeEmailsPermission"
                    @click="showComposeEmailModal = true"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Compose Email
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <!-- Tabs -->
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                <button
                                    v-for="tab in tabs.filter(tab => tab.visible)"
                                    :key="tab.id"
                                    @click="switchTab(tab.id)"
                                    :class="[
                                        activeTab === tab.id
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                        'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                                    ]"
                                >
                                    {{ tab.label }}
                                </button>
                            </nav>
                        </div>

                        <!-- Tab Content -->
                        <div class="mt-6">
                            <div v-for="tab in tabs" :key="tab.id">
                                <TabPanel :active="activeTab === tab.id">
                                    <component
                                        :is="tab.component"
                                        :ref="el => { if (el) tabRefs[tab.id] = el }"
                                        @view-email="handleViewEmail"
                                        @filters-changed="tab.id === 'all' ? handleFiltersChanged : () => {}"
                                        :is-active="activeTab === tab.id"
                                    />
                                </TabPanel>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Details Modal -->
        <EmailDetailsModal
            :show="showEmailDetailsModal"
            :email="selectedEmail"
            :can-approve-emails="showApprovalButtons"
            @close="handleCloseEmailDetails"
            @edit="openEditEmailModal"
            @reject="openRejectEmailModal"
        />

        <!-- Email Action Modal (Edit/Reject) -->
        <EmailActionModal
            :show="showActionModal"
            :title="actionModalTitle"
            :api-endpoint="actionModalApiEndpoint"
            :http-method="actionModalHttpMethod"
            :submit-button-text="actionModalSubmitButtonText"
            :success-message="actionModalSuccessMessage"
            :email-id="selectedEmail?.id"
            :project-id="selectedEmail?.conversation?.project?.id"
            @close="handleActionModalClose"
            @submitted="handleActionModalSubmitted"
            @error="(err) => console.error('EmailActionModal error:', err)"
        />

        <!-- Template Email Modal -->
        <EditTemplateEmailModal
            :show="showEditTemplateEmailModal"
            title="Edit Template Email"
            :api-endpoint="editTemplateApiEndpoint"
            http-method="post"
            :submit-button-text="editTemplateSubmitButtonText"
            :success-message="editTemplateSuccessMessage"
            :email-id="editTemplateEmailId"
            :client-id="editTemplateClientId"
            @close="handleEditTemplateEmailModalClose"
            @submitted="handleEditTemplateEmailModalSubmitted"
            @error="(err) => console.error('EditTemplateEmailModal error:', err)"
        />

        <ComposeEmailModal
            :show="showComposeEmailModal"
            :title="'Compose New Email'"
            :api-endpoint="'/api/emails/templated'"
            :http-method="'post'"
            :clients="projectClients"
            :submit-button-text="'Submit for Approval'"
            :success-message="'Email submitted for approval successfully!'"
            :project-id="projectId"
            @close="showComposeEmailModal = false"
            @submitted="showComposeEmailModal = false"
            @error="(error) => console.error('Email submission error:', error)"
        />

    </AuthenticatedLayout>
</template>
