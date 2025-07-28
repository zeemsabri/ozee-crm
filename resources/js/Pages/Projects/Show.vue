<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
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
const emailableClients = ref([]);

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

const canViewEmails = computed(() => canView('emails').value);
const canComposeEmails = computed(() => canDo('compose_emails').value);
const canApproveEmails = computed(() => canDo('approve_emails').value);
const canViewNotes = computed(() => canView('project_notes').value);
const canAddNotes = computed(() => canDo('add_project_notes').value);

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

// Computed properties for limited data display on main page (Overview tab)
const tasksDueToday = computed(() => {
    if (!project.value.tasks) return [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return project.value.tasks.filter(task => {
        if (!task.due_date) return false;
        const taskDueDate = new Date(task.due_date);
        return taskDueDate.toDateString() === today.toDateString();
    });
});

const latestNotes = computed(() => {
    if (!project.value.notes) return [];
    return [...project.value.notes]
        .filter(note => note.type !== 'standup') // Exclude standups from general notes overview
        .sort((a,b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 3);
});

const latestEmails = computed(() => {
    if (!project.value.emails) return [];
    return [...project.value.emails]
        .sort((a,b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 3);
});

// Data fetching for the entire project
const fetchProjectData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        // Fetch full project details including relationships the user has access to
        const response = await window.axios.get(`/api/projects/${projectId}`); // This hits the show method
        project.value = response.data;
        console.log('Full project data received (Show.vue main fetch):', project.value);

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
    alert('Project updated successfully!');
    fetchProjectData(); // Re-fetch to ensure all relationships/permissions are updated
};

// Handlers for child component emits to update parent state or re-fetch data
const handleTasksUpdated = (updatedTasks) => {
    project.value.tasks = updatedTasks;
};

const handleEmailsUpdated = (updatedEmails) => {
    project.value.emails = updatedEmails;
};

const handleNotesUpdated = (updatedNotes) => {
    project.value.notes = updatedNotes;
};

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
};

const handleViewUserTransactions = () => {
    showUserTransactionsModal.value = true;
};



const fetchClients = async () => {
    loading.value = true;
    // error.value = null;
    if (!canViewClientContacts) {
        error.value = "You don't have permission to view project clients.";
        loading.value = false;
        return;
    }

    try {
        const response = await window.axios.get(`/api/projects/${projectId}/sections/clients?type=clients`);
        emailableClients.value = response.data;
    } catch (e) {
        console.error('Failed to fetch project clients:', e);
        error.value = e.response?.data?.message || 'Failed to load client data.';
    } finally {
        loading.value = false;
    }
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

    await fetchClients();
    // Fetch currency rates globally once
    await fetchCurrencyRates();
    // Then fetch the main project data
    await fetchProjectData();


});
</script>

<template>
    <Head :title="project.name || 'Project Details'" />

    <AuthenticatedLayout>
        <div class="py-8 max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Loading and Error States -->
            <div v-if="loading" class="text-center text-gray-600 text-lg animate-pulse">
                Loading project details...
            </div>
            <div v-else-if="generalError" class="text-center text-red-600 text-lg font-medium">
                {{ generalError }}
            </div>
            <div v-else class="space-y-8">
                <!-- General Information Card -->
                <ProjectGeneralInfoCard
                    :project="project"
                    :project-id="projectId"
                    :can-manage-projects="canManageProjects"
                    :is-super-admin="isSuperAdmin"
                    @open-edit-modal="showEditModal = true"
                    @open-standup-modal="showStandupModal = true"
                    @open-meeting-modal="showMeetingModal = true"
                    @open-magic-link-modal="showMagicLinkModal = true"
                    @resource-saved="fetchProjectData"
                />

                <!-- Upcoming Meetings Section -->
                <ProjectMeetingsList :project-id="projectId" ref="meetingsListComponent" />

                <!-- Project Stats Section -->
                <ProjectStatsCards :tasks="project.tasks || []" :emails="project.emails || []" />


                <!-- Currency Switcher -->
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

                <!-- Project Financials Card -->
                <ProjectFinancialsCard
                    v-if="canViewProjectServicesAndPayments && canViewProjectTransactions"
                    :project-id="projectId"
                    :can-view-project-services-and-payments="canViewProjectServicesAndPayments"
                    :can-view-project-transactions="canViewProjectTransactions"
                />

                <!-- Project Financials, Clients, Users, Current User Earning -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">


                    <!-- Project Clients Card -->
                    <ProjectClientsCard
                        v-if="canViewClientContacts"
                        :project-id="projectId"
                        :can-view-client-contacts="canViewClientContacts"
                    />

                    <!-- Project Team Card -->
                    <ProjectTeamCard
                        :project-id="projectId"
                        :can-view-users="true"
                    />

                    <!-- Your Financials Card -->
                    <UserFinancialsCard
                        :project-id="projectId"
                        @viewUserTransactions="handleViewUserTransactions"
                    />
                </div>
                <!-- END NEW SECTION -->

                <!-- Tab Navigation -->
                <ProjectTabsNavigation
                    v-model:selectedTab="selectedTab"
                    :can-view-emails="canViewEmails"
                    :can-view-notes="canViewNotes"
                />

                <div v-if="selectedTab === null">
                    <!-- Tasks Due Today Section (Overview) -->
                    <div v-if="tasksDueToday.length > 0" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Tasks Due Today</h4>
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
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ task.title }}</td>
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
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ task.assigned_to }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <!-- Actions handled by ProjectTasksTab. Assuming view/edit is common -->
                                        <button @click="selectedTab = 'tasks'" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
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
                            <h4 class="text-lg font-semibold text-gray-900">Tasks Due Today</h4>
                            <button @click="selectedTab = 'tasks'" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                View All Tasks →
                            </button>
                        </div>
                        <p class="text-gray-400 text-sm">No tasks due today.</p>
                    </div>

                    <!-- Latest Emails Section (Overview) -->
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
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="email in latestEmails" :key="email.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ email.subject }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ email.sender?.name || 'N/A' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ new Date(email.created_at).toLocaleDateString() }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button @click="selectedTab = 'emails'" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                            View
                                        </button>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Latest Notes Section (Overview) -->
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

                <!-- Tab Content -->
                <ProjectTasksTab
                    v-if="selectedTab === 'tasks'"
                    :project-id="projectId"
                    :project-users="project.users || []"
                    :can-manage-projects="canManageProjects"
                    @tasksUpdated="handleTasksUpdated"
                />

                <ProjectEmailsTab
                    v-if="selectedTab === 'emails'"
                    :project-id="projectId"
                    :project-clients="emailableClients || []"
                    :can-view-emails="canViewEmails"
                    :can-compose-emails="canComposeEmails"
                    :can-approve-emails="canApproveEmails"
                    :user-project-role="userProjectRole"
                    @emailsUpdated="handleEmailsUpdated"
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
            </div>
        </div>

        <!-- Modals that apply broadly or are less section-specific -->
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

        <!-- New User Transactions Modal -->
        <UserTransactionsModal
            :show="showUserTransactionsModal"
            :project-id="projectId"
            :project-name="project.name"
            @close="showUserTransactionsModal = false"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom styles for subtle enhancements */
th, td {
    min-width: 120px;
}
</style>
