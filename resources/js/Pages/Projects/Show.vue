<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch, reactive } from 'vue';
import ProjectForm from '@/Components/ProjectForm.vue';
import Modal from '@/Components/Modal.vue';
import ProjectMeetingsList from '@/Components/ProjectMeetingsList.vue';
import DailyStandups from '@/Components/DailyStandups/DailyStandups.vue';
import MeetingModal from '@/Components/MeetingModal.vue';
import StandupModal from '@/Components/StandupModal.vue';
import NotesModal from '@/Components/NotesModal.vue'; // For adding standalone notes
import ProjectMagicLinkModal from '@/Components/ProjectMagicLinkModal.vue'; // New component

// Imported new components
import ProjectGeneralInfoCard from '@/Components/ProjectGeneralInfoCard.vue';
import ProjectStatsCards from '@/Components/ProjectStatsCards.vue';
import ProjectTabsNavigation from '@/Components/ProjectTabsNavigation.vue';
import ProjectTasksTab from '@/Components/ProjectTasks/ProjectTasksTab.vue'; // Updated path
import ProjectEmailsTab from '@/Components/ProjectEmailsTab.vue';
import ProjectNotesTab from '@/Components/ProjectNotesTab.vue';
import ProjectFinancialsCard from '@/Components/ProjectOverviewCards/ProjectFinancialsCard.vue'; // New
import ProjectClientsCard from '@/Components/ProjectOverviewCards/ProjectClientsCard.vue'; // New
import ProjectTeamCard from '@/Components/ProjectOverviewCards/ProjectTeamCard.vue'; // New
import UserFinancialsCard from '@/Components/ProjectOverviewCards/UserFinancialCard.vue'; // New
import UserTransactionsModal from '@/Components/ProjectFinancials/UserTransactionsModal.vue'; // New

// NEW: Import the standalone ComposeEmailModal
import ComposeEmailModal from '@/Components/ProjectsEmails/ComponseEmailModal.vue'; // Adjust path if necessary

// NEW: Import the new global components
import RightSidebar from '@/Components/RightSidebar.vue'; // New global sidebar component
import TaskDetailSidebar from '@/Components/ProjectTasks/TaskDetailSidebar.vue'; // New task detail component for sidebar
import CreateTaskModal from '@/Components/ProjectTasks/CreateTaskModal.vue'; // New standalone create task modal

// NEW: Import Deliverables components
import ProjectDeliverablesTab from '@/Components/ProjectsDeliverables/ProjectDeliverableTab.vue'; // Adjust path
import CreateDeliverableModal from '@/Components/ProjectsDeliverables/CreateDeliverableModal.vue'; // Adjust path
import DeliverableDetailSidebar from '@/Components/ProjectsDeliverables/DeliverableDetailSidebar.vue'; // NEW: Deliverable detail sidebar

// SEO Import
import SeoReportTab from '@/Components/ProjectsSeoReports/SeoReportTab.vue'; // Adjust path as needed
import CreateSeoReportModal from "@/Components/ProjectsSeoReports/CreateSeoReportModal.vue";

// Currency utilities and SelectDropdown
import SelectDropdown from '@/Components/SelectDropdown.vue'; // For currency switcher
import { fetchCurrencyRates, displayCurrency } from '@/Utils/currency'; // Import displayCurrency

import { useAuthUser, useProjectRole, usePermissions, fetchProjectPermissions } from '@/Directives/permissions';
import PrimaryButton from "@/Components/PrimaryButton.vue";

// Use the permission utilities
const authUser = useAuthUser();

// Get project ID from Inertia page props
const projectId = usePage().props.id;

// Project data (still holds overview data from main /api/projects/{id} call)
const project = ref({
    clients: [],
    users: [],
    notes: [],
    transactions: [],
    documents: [],
    meetings: [],
    tasks: [], // Ensure tasks is initialized for ProjectStatsCards
    emails: [], // Ensure emails is initialized for ProjectStatsCards
    deliverables: [], // Initialize deliverables array
});
const loading = ref(true); // For the main page load
const generalError = ref('');

// Modals managed by Show.vue or passed down
const showEditModal = ref(false);
const showMeetingModal = ref(false);
const showStandupModal = ref(false);
const showAddNoteModal = ref(false);
const showMagicLinkModal = ref(false);
const showUserTransactionsModal = ref(false); // New state for user transactions modal
const showComposeEmailModal = ref(false);
const showCreateDeliverableModal = ref(false);

// NEW: State for the RightSidebar and TaskDetailSidebar
const showRightSidebar = ref(false);
const selectedTaskIdForSidebar = ref(null);
const selectedDeliverableIdForSidebar = ref(null); // NEW: State for Deliverable Detail Sidebar

// NEW: State for the global CreateTaskModal
const showGlobalCreateTaskModal = ref(false);

// SEO Report Modals
const showCreateSeoReportModal = ref(false);
const selectedSeoReportInitialData = ref(null); // To pass data for editing

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'on_hold', label: 'On Hold' },
    { value: 'archived', label: 'Archived' },
];
const departmentOptions = [
    { value: 'Website Designing', label: 'Website Designing' },
    { value: 'SEO', label: 'SEO' },
    { value: 'Social Media', 'label': 'Social Media' },
    { value: 'Content Writing', label: 'Content Writing' },
    { value: 'Graphic Design', label: 'Graphic Design' },
];
const sourceOptions = [
    { value: 'UpWork', label: 'UpWork' },
    { value: 'Direct', label: 'Direct Client' },
    { value: 'Wix Marketplace', label: 'Wix Marketplace' },
    { value: 'Referral', label: 'Referral' },
];

// Ref for meetings list component to call its methods
const meetingsListComponent = ref(null);

// Track which tab is currently selected (null means main overview)
const selectedTab = ref(null);

// Get the user's project-specific role for permission checks
const userProjectRole = useProjectRole(project);

// Set up permission checking functions
const { canDo, canView } = usePermissions(projectId, userProjectRole);

// Centralized permission checks based on project role and global permissions
const isSuperAdmin = computed(() => authUser.value?.role_data?.slug === 'super-admin');

const canManageProjects = computed(() => {
    return canDo('manage_projects').value || isSuperAdmin.value;
});

const canEditProject = computed(() => {
    return canDo('edit_projects').value || isSuperAdmin.value;
});


const canViewEmails = computed(() => canView('emails').value);
const canComposeEmails = computed(() => canDo('compose_emails').value);
const canApproveEmails = computed(() => canDo('approve_emails').value);
const canViewNotes = computed(() => canView('project_notes').value);
const canAddNotes = computed(() => canDo('add_project_notes').value);

// Permissions for Deliverables
const canViewDeliverables = computed(() => canView('deliverables').value);
const canCreateDeliverables = computed(() => canDo('create_deliverables').value);

// Permissions for SEO Reports (NEW)
const canViewSeoReports = computed(() => canView('seo_reports').value);
const canCreateSeoReports = computed(() => canDo('create_seo_reports').value); // Assuming a permission for creating SEO reports


// Permissions for new financial/client/user cards
const canViewClientContacts = computed(() => canView('client_contacts').value);
const canViewUsers = computed(() => canView('users').value);
const canViewProjectServicesAndPayments = computed(() => canView('project_financial', userProjectRole).value);
const canViewProjectTransactions = computed(() => canView('project_transactions').value);
const canViewClientFinancial = computed(() => canView('client_financial').value); // For contract details

// Currency options (moved here from currency.js for display in SelectDropdown)
const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

// State for due and overdue tasks
const tasksDueToday = ref([]);

// Function to fetch due and overdue tasks for the project
const fetchDueAndOverdueTasks = async () => {
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/due-and-overdue-tasks`);
        tasksDueToday.value = response.data;
    } catch (error) {
        console.error('Error fetching due and overdue tasks:', error);
    }
};

const latestNotes = computed(() => {
    if (!project.value.notes) return [];
    return [...project.value.notes]
        .filter(note => note.type !== 'standup') // Exclude standups from general notes overview
        .sort((a,b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 3);
});

// State for latest emails
const latestEmails = ref([]);

// Function to fetch latest emails
const fetchLatestEmails = async () => {
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/emails-simplified`, {
            params: {
                limit: 5 // Get only the latest 5 emails
            }
        });
        latestEmails.value = response.data;
    } catch (error) {
        console.error('Error fetching latest emails:', error);
    }
};

// Data fetching for the entire project
const fetchProjectData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        // Fetch full project details including relationships the user has access to
        const response = await window.axios.get(`/api/projects/${projectId}`); // This hits the show method
        project.value = response.data;
        console.log('Full project data received (Show.vue main fetch):', project.value);

        // Fetch due and overdue tasks after project data is loaded
        await fetchDueAndOverdueTasks();

    } catch (error) {
        generalError.value = 'Failed to load project data.';
        console.error('Error fetching project data:', error);
        if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            generalError.value = 'You are not authorized to view this project or your session expired. Please log in.';
        }
    } finally {
        loading.value = false;
    }
};

// Handle project update from ProjectForm
const handleProjectSubmit = (updatedProject) => {
    project.value = updatedProject;
    showEditModal.value = false;
    //alert('Project updated successfully!'); // Use a proper notification system
    fetchProjectData(); // Re-fetch to ensure all relationships/permissions are updated
};

// Handlers for child component emits to update parent state or re-fetch data
const handleTasksUpdated = (updatedTasks) => {
    project.value.tasks = updatedTasks;
};

const handleEmailsUpdated = (updatedEmails) => {
    project.value.emails = updatedEmails;
    // Refresh the latest emails section using the simplified endpoint
    if (canViewEmails.value) {
        fetchLatestEmails();
    }
};

const handleNotesUpdated = (updatedNotes) => {
    project.value.notes = updatedNotes;
};

// Handler for Deliverables updates
const handleDeliverablesUpdated = () => {
    // When deliverables are updated (e.g., new one added), re-fetch project data
    // to ensure any related counts/statuses on the overview are fresh.
    fetchProjectData();
}

const handleMeetingSaved = () => {
    if (meetingsListComponent.value) {
        meetingsListComponent.value.fetchMeetings();
    }
};

const handleStandupAdded = () => {
    fetchProjectData(); // Re-fetch project notes/standups to update overview
};

const handleChangeTab = (tabName) => {
    selectedTab.value = tabName;
    // Close any open sidebars when changing tabs
    closeTaskDetailSidebar();
    closeDeliverableDetailSidebar();
};

const handleViewUserTransactions = () => {
    showUserTransactionsModal.value = true;
};

// This function now directly opens the new standalone ComposeEmailModal
const handleComposeEmailAction = () => {
    showComposeEmailModal.value = true;
    selectedTab.value = 'emails'; // Optionally switch to emails tab when composing
}

// Handler for when ComposeEmailModal is submitted/closed
const handleComposeEmailSubmitted = async () => {
    showComposeEmailModal.value = false;
    await fetchProjectData(); // Refresh all project data including emails

    // Refresh the latest emails section using the simplified endpoint
    if (canViewEmails.value) {
        await fetchLatestEmails();
    }
};

const handleComposeEmailClose = () => {
    showComposeEmailModal.value = false;
};

// Handlers for TaskDetailSidebar
const openTaskDetailSidebar = (taskId) => {
    selectedTaskIdForSidebar.value = taskId;
    selectedDeliverableIdForSidebar.value = null; // Ensure deliverable sidebar is closed
    showRightSidebar.value = true;
};

const closeTaskDetailSidebar = () => {
    showRightSidebar.value = false;
    selectedTaskIdForSidebar.value = null;
};

const handleTaskDetailUpdated = () => {
    // A task was updated from the sidebar, refresh the main task list
    fetchProjectData();
};

const handleTaskDeleted = (deletedTaskId) => {
    // Filter out the deleted task from the local tasks array if it exists
    project.value.tasks = project.value.tasks.filter(task => task.id !== deletedTaskId);
    fetchProjectData(); // Re-fetch to ensure all counts are updated
    closeTaskDetailSidebar(); // Close sidebar after deletion
};

// NEW: Handlers for DeliverableDetailSidebar
const openDeliverableDetailSidebar = (deliverableId) => {
    selectedDeliverableIdForSidebar.value = deliverableId;
    selectedTaskIdForSidebar.value = null; // Ensure task sidebar is closed
    showRightSidebar.value = true;
};

const closeDeliverableDetailSidebar = () => {
    showRightSidebar.value = false;
    selectedDeliverableIdForSidebar.value = null;
};

const handleDeliverableDetailUpdated = () => {
    // A deliverable was updated from the sidebar, refresh the main deliverables list
    fetchProjectData();
};

// Handlers for global CreateTaskModal
const openGlobalCreateTaskModal = () => {
    showGlobalCreateTaskModal.value = true;
};

const handleGlobalCreateTaskSaved = () => {
    showGlobalCreateTaskModal.value = false;
    fetchProjectData(); // Refresh tasks after creation
};

// Handlers for SEO Report Modal
const openCreateSeoReportModal = (initialData = null) => {
    selectedSeoReportInitialData.value = initialData;
    showCreateSeoReportModal.value = true;
};

const handleSeoReportSaved = () => {
    showCreateSeoReportModal.value = false;
    // Trigger a refresh of the SeoReportTab to show the updated data
    // This can be done by re-fetching all project data or by having SeoReportTab
    // listen to this event. For simplicity, we can just re-fetch here if needed,
    // but the SeoReportTab is already designed to re-fetch on its own when `selectedMonth` changes.
    // So, we just need to ensure the modal closes.
    // If you need to force a re-render/re-fetch in SeoReportTab, you might need a more direct method.
    // For now, let's assume SeoReportTab's watch on `selectedMonth` and its internal `fetchAvailableMonths`
    // and `fetchSeoReport` are sufficient.
    // A more explicit way would be to emit an event from here that SeoReportTab listens to.
    // For now, we rely on the reactivity of selectedMonth and the tab's internal refresh.
};


onMounted(async () => {
    console.log('Show.vue mounted, fetching initial data...');
    // Fetch project-specific permissions first, as other data fetches depend on it
    try {
        await fetchProjectPermissions(projectId);
        console.log('Project permissions fetched (includes global)');
    } catch (error) {
        console.error(`Error fetching permissions for project ${projectId}:`, error);
    }

    await fetchCurrencyRates();
    await fetchProjectData();

    if (canViewEmails.value) {
        await fetchLatestEmails();
    }
});


</script>

<template>
    <Head :title="project.name || 'Project Details'" />

    <AuthenticatedLayout>
        <div class="py-8 max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div v-if="loading" class="text-center text-gray-600 text-lg animate-pulse">
                Loading project details...
            </div>
            <div v-else-if="generalError" class="text-center text-red-600 text-lg font-medium">
                {{ generalError }}
            </div>
            <div v-else class="space-y-8">
                <ProjectGeneralInfoCard
                    :project="project"
                    :project-id="projectId"
                    :can-manage-projects="canManageProjects"
                    :is-super-admin="isSuperAdmin"
                    :can-edit-projects="canEditProject"
                    @open-edit-modal="showEditModal = true"
                    @open-standup-modal="showStandupModal = true"
                    @open-meeting-modal="showMeetingModal = true"
                    @open-magic-link-modal="showMagicLinkModal = true"
                    @resource-saved="fetchProjectData"
                    @open-compose-modal="handleComposeEmailAction"
                />

                <ProjectMeetingsList :project-id="projectId" ref="meetingsListComponent" />

                <ProjectStatsCards :tasks="project.tasks || []" :emails="project.emails || []" />


                <div class="flex justify-end items-center mb-5">
                    <SelectDropdown
                        id="display-currency-switcher"
                        v-model="displayCurrency"
                        :options="currencyOptions"
                        value-key="value"
                        label-key="label"
                        class="w-20"
                        containerClasses="max-w-[150px]"
                    />
                </div>

                <ProjectFinancialsCard
                    v-if="canViewProjectServicesAndPayments && canViewProjectTransactions"
                    :project-id="projectId"
                    :can-view-project-services-and-payments="canViewProjectServicesAndPayments"
                    :can-view-project-transactions="canViewProjectTransactions"
                />

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">


                    <ProjectClientsCard
                        v-if="canViewClientContacts"
                        :project-id="projectId"
                        :can-view-client-contacts="canViewClientContacts"
                    />

                    <ProjectTeamCard
                        :project-id="projectId"
                        :can-view-users="true"
                    />

                    <UserFinancialsCard
                        :project-id="projectId"
                        @viewUserTransactions="handleViewUserTransactions"
                    />
                </div>
                <ProjectTabsNavigation
                    v-model:selectedTab="selectedTab"
                    :can-view-emails="canViewEmails"
                    :can-view-notes="canViewNotes"
                    :can-view-deliverables="canViewDeliverables"
                    :can-view-seo-reports="canViewSeoReports"
                />

                <div v-if="selectedTab === null">
                    <div v-if="tasksDueToday.length > 0" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Due & Overdue Tasks</h4>
                            <button @click="selectedTab = 'tasks'" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                View All Tasks →
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="task in tasksDueToday" :key="task.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ task.name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        <span :class="{
                                            'px-2 py-1 rounded-full text-xs font-medium': true,
                                            'bg-yellow-100 text-yellow-800': task.status === 'To Do',
                                            'bg-blue-100 text-blue-800': task.status === 'In Progress',
                                            'bg-green-100 text-green-800': task.status === 'Done',
                                            'bg-red-100 text-red-800': task.status === 'Blocked',
                                            'bg-gray-100 text-gray-800': task.status === 'Archived'
                                        }">
                                            {{ task.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ task.assigned_to?.name }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button @click="openTaskDetailSidebar(task.id)" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                            View/Edit
                                        </button>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Due & Overdue Tasks</h4>
                            <button @click="selectedTab = 'tasks'" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                View All Tasks →
                            </button>
                        </div>
                        <p class="text-gray-400 text-sm">No due or overdue tasks.</p>
                    </div>

                    <div v-if="canViewEmails" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Latest Emails</h4>
                            <button @click="selectedTab = 'emails'" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                View All Emails →
                            </button>
                        </div>
                        <div v-if="!project.emails?.length" class="text-gray-400 text-sm">No email communication found.</div>
                        <div v-else class="overflow-x-auto">
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
                                <tr v-for="email in latestEmails" :key="email.id" class="hover:bg-gray-50 transition-colors">
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
                    </div>

                    <div v-if="canViewNotes" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Latest Notes</h4>
                            <div class="flex items-center gap-4">
                                <button @click="selectedTab = 'notes'" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                    View All Notes →
                                </button>
                                <div v-if="canAddNotes">
                                    <PrimaryButton class="bg-indigo-600 hover:bg-indigo-700 transition-colors" @click="showAddNoteModal = true">
                                        Add Note
                                    </PrimaryButton>
                                </div>
                            </div>
                        </div>
                        <div v-if="latestNotes.length" class="space-y-4">
                            <div v-for="note in latestNotes" :key="note.id" class="p-4 bg-gray-50 rounded-md shadow-sm hover:bg-gray-100 transition-colors">
                                <div class="flex justify-between">
                                    <div class="flex-grow">
                                        <p class="text-sm" :class="{'text-gray-700': note.content !== '[Encrypted content could not be decrypted]', 'text-red-500 italic': note.content === '[Encrypted content could not be decrypted]'}">
                                            {{ note.content }}
                                            <span v-if="note.content === '[Encrypted content could not be decrypted]'" class="text-xs text-red-400 block mt-1">
                                                (There was an issue decrypting this note. Please contact an administrator.)
                                            </span>
                                        </p>
                                        <div class="flex items-center mt-1">
                                            <p class="text-xs text-gray-500">Added by {{ note.user?.name || 'Unknown' }} on {{ new Date(note.created_at).toLocaleDateString() }}</p>
                                            <span v-if="note.reply_count > 0" class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-800 text-xs rounded-full">
                                                {{ note.reply_count }} {{ note.reply_count === 1 ? 'reply' : 'replies' }}
                                            </span>
                                        </div>
                                    </div>
                                    <div v-if="canViewNotes && note.chat_message_id && project.google_chat_id">
                                        <button @click="selectedTab = 'notes'" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                            View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-gray-400 text-sm">No notes available.</p>
                    </div>
                </div>

                <ProjectTasksTab
                    v-if="selectedTab === 'tasks'"
                    :project-id="projectId"
                    :project-users="project.users || []"
                    :can-manage-projects="canManageProjects"
                    @tasksUpdated="handleTasksUpdated"
                    @openTaskDetailSidebar="openTaskDetailSidebar"
                    @open-create-task-modal="openGlobalCreateTaskModal"
                />

                <ProjectEmailsTab
                    v-if="selectedTab === 'emails'"
                    :project-id="projectId"
                    :can-view-emails="canViewEmails"
                    :can-compose-emails="canComposeEmails"
                    :can-approve-emails="canApproveEmails"
                    :user-project-role="userProjectRole"
                    :open-compose="showComposeEmailModal"
                    @emailsUpdated="handleEmailsUpdated"
                    @resetOpenCompose="showComposeEmailModal = false"
                />

                <DailyStandups
                    v-if="selectedTab === 'standups'"
                    :projectId="projectId"
                    :users="project.users || []"
                    @standupAdded="handleStandupAdded"
                />

                <ProjectNotesTab
                    v-if="selectedTab === 'notes'"
                    :project-id="projectId"
                    :google-chat-id="project.google_chat_id"
                    :can-view-notes="canViewNotes"
                    :can-add-notes="canAddNotes"
                    @notesUpdated="handleNotesUpdated"
                    @changeTab="handleChangeTab"
                />

                <!-- Deliverables Tab Content -->
                <ProjectDeliverablesTab
                    v-if="selectedTab === 'deliverables'"
                    :project-id="projectId"
                    :can-create-deliverables="canCreateDeliverables"
                    :can-view-deliverables="canViewDeliverables"
                    @deliverablesUpdated="handleDeliverablesUpdated"
                    @openDeliverableDetailSidebar="openDeliverableDetailSidebar"
                />

                <!-- SEO Reports Tab Content (NEW) -->
                <SeoReportTab
                    v-if="selectedTab === 'seo-reports'"
                    :project-id="projectId"
                    :can-create-seo-reports="canCreateSeoReports"
                    @openCreateSeoReportModal="openCreateSeoReportModal"
                />
            </div>
        </div>

        <Modal :show="showEditModal" @close="showEditModal = false">
            <ProjectForm
                :show="showEditModal"
                :project="project"
                :statusOptions="statusOptions" :departmentOptions="departmentOptions" :sourceOptions="sourceOptions"
                :clientRoleOptions="[]" :userRoleOptions="[]" :paymentTypeOptions="[]"
                @close="showEditModal = false"
                @submit="handleProjectSubmit"
            />
        </Modal>

        <MeetingModal
            :show="showMeetingModal"
            @close="showMeetingModal = false"
            @saved="handleMeetingSaved"
            :projectId="projectId"
            :projectUsers="project.users || []"
        />

        <StandupModal
            :show="showStandupModal"
            @close="showStandupModal = false"
            @standupAdded="handleStandupAdded"
            :projectId="projectId"
        />

        <NotesModal
            :show="showAddNoteModal"
            :project-id="projectId"
            @close="showAddNoteModal = false"
            @note-added="handleNotesUpdated"
        />

        <ProjectMagicLinkModal
            :show="showMagicLinkModal"
            :project-id="projectId"
            :project-clients="project.clients || []"
            @close="showMagicLinkModal = false"
        />

        <UserTransactionsModal
            :show="showUserTransactionsModal"
            :project-id="projectId"
            :project-name="project.name"
            @close="showUserTransactionsModal = false"
        />

        <ComposeEmailModal
            :show="showComposeEmailModal"
            :project-id="projectId"
            :user-project-role="userProjectRole"
            @close="handleComposeEmailClose"
            @submitted="handleComposeEmailSubmitted"
            @error="(err) => console.error('Error composing email:', err)"
        />

        <!-- Global Right Sidebar -->
        <RightSidebar
            :show="showRightSidebar"
            @update:show="showRightSidebar = $event"
            :title="selectedTaskIdForSidebar ? 'Task Details' : (selectedDeliverableIdForSidebar ? 'Deliverable Details' : 'Details')"
            :initialWidth="50"
        >
            <template #content>
                <!-- Conditionally render TaskDetailSidebar or DeliverableDetailSidebar -->
                <TaskDetailSidebar
                    v-if="selectedTaskIdForSidebar"
                    :task-id="selectedTaskIdForSidebar"
                    :project-users="project.users || []"
                    @close="closeTaskDetailSidebar"
                    @task-updated="handleTaskDetailUpdated"
                    @task-deleted="handleTaskDeleted"
                />
                <DeliverableDetailSidebar
                    v-else-if="selectedDeliverableIdForSidebar"
                    :project-id="projectId"
                    :deliverable-id="selectedDeliverableIdForSidebar"
                    @close="closeDeliverableDetailSidebar"
                    @deliverable-updated="handleDeliverableDetailUpdated"
                />
            </template>
        </RightSidebar>

        <!-- Global Create Task Modal -->
        <CreateTaskModal
            :show="showGlobalCreateTaskModal"
            :project-id="projectId"
            @close="showGlobalCreateTaskModal = false"
            @saved="handleGlobalCreateTaskSaved"
        />

        <!-- Create Deliverable Modal -->
        <CreateDeliverableModal
            :show="showCreateDeliverableModal"
            :project-id="projectId"
            @close="showCreateDeliverableModal = false"
            @saved="handleDeliverableSaved"
        />

        <!-- Create/Edit SEO Report Modal -->
        <CreateSeoReportModal
            :project-id="projectId"
            :show="showCreateSeoReportModal"
            :initial-data="selectedSeoReportInitialData"
            @close="showCreateSeoReportModal = false"
            @saved="handleSeoReportSaved"
        />

    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom styles for subtle enhancements */
th, td {
    min-width: 120px;
}
</style>
