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
import NotesModal from '@/Components/NotesModal.vue';
import ProjectMagicLinkModal from '@/Components/ProjectMagicLinkModal.vue';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/20/solid';

import ProjectGeneralInfoCard from '@/Components/ProjectGeneralInfoCard.vue';
import ProjectStatsCards from '@/Components/ProjectStatsCards.vue';
import ProjectTabsNavigation from '@/Components/ProjectTabsNavigation.vue';
import ProjectTasksTab from '@/Components/ProjectTasks/ProjectTasksTab.vue';
import ProjectEmailsTab from '@/Components/ProjectEmailsTab.vue';
import ProjectNotesTab from '@/Components/ProjectNotesTab.vue';
import ProjectFinancialsCard from '@/Components/ProjectOverviewCards/ProjectFinancialsCard.vue';
import ProjectClientsCard from '@/Components/ProjectOverviewCards/ProjectClientsCard.vue';
import ProjectTeamCard from '@/Components/ProjectOverviewCards/ProjectTeamCard.vue';
import UserFinancialsCard from '@/Components/ProjectOverviewCards/UserFinancialCard.vue';
import UserTransactionsModal from '@/Components/ProjectFinancials/UserTransactionsModal.vue';

import ComposeEmailModal from '@/Components/ProjectsEmails/ComponseEmailModal.vue';
import CreateTaskModal from '@/Components/ProjectTasks/CreateTaskModal.vue';
import SeoReportTab from '@/Components/ProjectsSeoReports/SeoReportTab.vue';
import CreateSeoReportModal from "@/Components/ProjectsSeoReports/CreateSeoReportModal.vue";

import ProjectTaskNotificationPrompt from '@/Components/ProjectTaskNotificationPrompt.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { fetchCurrencyRates, displayCurrency } from '@/Utils/currency';
import { useAuthUser, useProjectRole, usePermissions, fetchProjectPermissions } from '@/Directives/permissions';
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import TaskList from '@/Components/TaskList.vue';
import { sidebarState, openTaskDetailSidebar, closeTaskDetailSidebar } from '@/Utils/sidebar';
import * as taskState from '@/Utils/taskState.js';

// New import for the deliverables overview card
import ProjectDeliverablesOverviewCard from '@/Components/ProjectDashboard/ProjectDeliverablesOverviewCard.vue';

const authUser = useAuthUser();
const projectId = usePage().props.id;

const project = ref({
    clients: [],
    users: [],
    notes: [],
    transactions: [],
    documents: [],
    meetings: [],
    tasks: [],
    emails: [],
    deliverables: [],
});

// State for Project Deliverables panel
const hasDeliverables = ref(false);
const isDeliverablesCollapsed = ref(false);
const deliverablesWidth = ref(25); // Default width percentage
const isResizing = ref(false);
const loading = ref(true);
const generalError = ref('');

const showEditModal = ref(false);
const showMeetingModal = ref(false);
const showStandupModal = ref(false);
const showAddNoteModal = ref(false);
const showMagicLinkModal = ref(false);
const showUserTransactionsModal = ref(false);
const showComposeEmailModal = ref(false);

const showGlobalCreateTaskModal = ref(false);
const showCreateSeoReportModal = ref(false);
const selectedSeoReportInitialData = ref(null);

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'on_hold', label: 'On Hold' },
    { value: 'archived', label: 'Archived' },
];

const meetingsListComponent = ref(null);
const selectedTab = ref(null);
const userProjectRole = useProjectRole(project);
const { canDo, canView } = usePermissions(projectId, userProjectRole);

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
const canViewDeliverables = computed(() => canView('deliverables').value);
const canCreateDeliverables = computed(() => canDo('create_deliverables').value);
const canViewSeoReports = computed(() => canView('seo_reports').value);
const canCreateSeoReports = computed(() => canDo('create_seo_reports').value);
const canViewClientContacts = computed(() => canView('client_contacts').value);
const canViewUsers = computed(() => canView('users').value);
const canViewProjectServicesAndPayments = computed(() => canView('project_financial', userProjectRole).value);
const canViewProjectTransactions = computed(() => canView('project_transactions').value);
const canViewClientFinancial = computed(() => canView('client_financial').value);

const currencyOptions = [
    { value: 'PKR', label: 'PKR' },
    { value: 'AUD', label: 'AUD' },
    { value: 'INR', label: 'INR' },
    { value: 'USD', label: 'USD' },
    { value: 'EUR', label: 'EUR' },
    { value: 'GBP', label: 'GBP' },
];

const tasksDueToday = ref([]);
const tasksTabRef = ref(null);
const navigationRef = ref(null);
const tasksFilter = ref('all');

const fetchDueAndOverdueTasks = async () => {
    try {
        tasksDueToday.value = await taskState.fetchDueAndOverdueTasks(projectId);
    } catch (error) {
        console.error('Error fetching due and overdue tasks:', error);
    }
};

const handleViewDueAndOverdueTasks = () => {
    selectedTab.value = 'tasks';
    tasksFilter.value = 'due-overdue';
    setTimeout(() => {
        if (navigationRef.value && navigationRef.value.scrollIntoView) {
            navigationRef.value.scrollIntoView({ behavior: 'smooth' });
        }
    }, 100);
};

const handleDueTaskUpdated = async () => {
    await fetchDueAndOverdueTasks();
};

const latestNotes = computed(() => {
    if (!project.value.notes) return [];
    return [...project.value.notes]
        .filter(note => note.type !== 'standup')
        .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
        .slice(0, 3);
});

const latestEmails = ref([]);

const fetchLatestEmails = async () => {
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/emails-simplified`, {
            params: {
                limit: 5
            }
        });
        latestEmails.value = response.data;
    } catch (error) {
        console.error('Error fetching latest emails:', error);
    }
};

const fetchProjectData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const response = await window.axios.get(`/api/projects/${projectId}`);
        project.value = response.data;
        console.log('Full project data received (Show.vue main fetch):', project.value);

        // Check if project has deliverables
        hasDeliverables.value = project.value.deliverables && project.value.deliverables.length > 0;
        isDeliverablesCollapsed.value = !hasDeliverables.value;

        await fetchDueAndOverdueTasks();
        if (canViewEmails.value) {
            await fetchLatestEmails();
        }
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

const handleProjectSubmit = (updatedProject) => {
    project.value = updatedProject;
    showEditModal.value = false;
    fetchProjectData();
};

const handleTasksUpdated = (updatedTasks) => {
    project.value.tasks = updatedTasks;
};

const handleEmailsUpdated = (updatedEmails) => {
    project.value.emails = updatedEmails;
    if (canViewEmails.value) {
        fetchLatestEmails();
    }
};

const handleNotesUpdated = (updatedNotes) => {
    project.value.notes = updatedNotes;
};

// This handler will be triggered when a deliverable is created/updated/deleted
const handleDeliverablesUpdated = () => {
    fetchProjectData();
}

const handleMeetingSaved = () => {
    if (meetingsListComponent.value) {
        meetingsListComponent.value.fetchMeetings();
    }
};

const handleStandupAdded = () => {
    fetchProjectData();
};

const handleChangeTab = (tabName) => {
    selectedTab.value = tabName;
    closeTaskDetailSidebar();
};

const handleViewUserTransactions = () => {
    showUserTransactionsModal.value = true;
};

const handleComposeEmailAction = () => {
    showComposeEmailModal.value = true;
    selectedTab.value = 'emails';
}

const handleComposeEmailSubmitted = async () => {
    showComposeEmailModal.value = false;
    await fetchProjectData();
};

const handleComposeEmailClose = () => {
    showComposeEmailModal.value = false;
};

const toggleDeliverablesPanel = () => {
    isDeliverablesCollapsed.value = !isDeliverablesCollapsed.value;
};

const startResizing = (event) => {
    isResizing.value = true;
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', stopResizing);
    // Prevent text selection during resize
    event.preventDefault();
};

const handleMouseMove = (event) => {
    if (!isResizing.value) return;

    // Calculate width based on mouse position relative to window width
    const containerWidth = document.documentElement.clientWidth;
    const newWidth = Math.min(Math.max(10, (1 - (event.clientX / containerWidth)) * 100), 50);
    deliverablesWidth.value = newWidth;
};

const stopResizing = () => {
    isResizing.value = false;
    document.removeEventListener('mousemove', handleMouseMove);
    document.removeEventListener('mouseup', stopResizing);

    // Save the width to localStorage
    localStorage.setItem(`project_${projectId}_deliverables_width`, deliverablesWidth.value);
};

const handleOpenTaskDetailSidebar = (taskId, taskProjectId) => {
    const useProjectId = taskProjectId || projectId;
    openTaskDetailSidebar(taskId, useProjectId, project.value.users);
};

watch(sidebarState, (newState, oldState) => {
    if (oldState.taskId && !newState.taskId) {
        fetchProjectData();
    }
}, { deep: true });


const openGlobalCreateTaskModal = () => {
    showGlobalCreateTaskModal.value = true;
};

const handleGlobalCreateTaskSaved = () => {
    showGlobalCreateTaskModal.value = false;
    fetchProjectData();
};

const openCreateSeoReportModal = (initialData = null) => {
    selectedSeoReportInitialData.value = initialData;
    showCreateSeoReportModal.value = true;
};

const handleSeoReportSaved = () => {
    showCreateSeoReportModal.value = false;
};


onMounted(async () => {
    console.log('Show.vue mounted, fetching initial data...');
    try {
        await fetchProjectPermissions(projectId);
        console.log('Project permissions fetched (includes global)');
    } catch (error) {
        console.error(`Error fetching permissions for project ${projectId}:`, error);
    }

    // Load saved width from localStorage if available
    const savedWidth = localStorage.getItem(`project_${projectId}_deliverables_width`);
    if (savedWidth !== null) {
        deliverablesWidth.value = parseFloat(savedWidth);
    }

    await fetchCurrencyRates();
    await fetchProjectData();
});
</script>

<template>
    <Head :title="project.name || 'Project Details'" />

    <AuthenticatedLayout>
        <div class="py-8 max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div v-if="loading" class="flex items-center justify-center min-h-[50vh]">
                <div class="flex flex-col items-center space-y-4">
                    <svg class="animate-spin h-16 w-16 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-lg text-gray-600 font-medium">Loading project details...</p>
                </div>
            </div>
            <div v-else-if="generalError" class="text-center text-red-600 text-lg font-medium">
                {{ generalError }}
            </div>
            <div v-else class="space-y-8">
                <!-- Main Content Layout (Flexbox for dynamic resizing) -->
                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Left and middle content column -->
                    <div class="flex-1 space-y-6">

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
                        <ProjectStatsCards :tasks="project.tasks || []" :emails="project.emails || []" />

                        <div class="flex justify-end items-center">
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


                    </div>

                    <!-- Right Column for Deliverables (Dynamic width) -->
                    <div v-if="canViewDeliverables"
                         class="relative transition-all duration-300"
                         :style="!isDeliverablesCollapsed ? `width: ${deliverablesWidth}%` : ''"
                         :class="{ 'w-full lg:flex-none': !isDeliverablesCollapsed, 'w-16': isDeliverablesCollapsed }">
                        <div class="flex h-full w-full">
                            <!-- Collapsed Vertical Panel -->
                            <div v-if="isDeliverablesCollapsed"
                                 class="w-16 h-full flex items-center justify-center bg-white rounded-xl shadow-lg border border-gray-200 cursor-pointer transition-all duration-300 transform hover:scale-105"
                                 @click="toggleDeliverablesPanel">
                                <span class="font-bold text-gray-500 text-sm" style="writing-mode: vertical-rl; text-orientation: sideways;">Project Deliverables</span>
                            </div>

                            <!-- Expanded Card -->
                            <div v-else class="transition-all duration-300 w-full relative">
                                <!-- Resize Handle -->
                                <div
                                    class="absolute left-0 top-0 bottom-0 w-1 cursor-col-resize hover:bg-indigo-500 hover:w-1.5 z-10 transition-all"
                                    @mousedown="startResizing"
                                    :class="{ 'bg-indigo-500 w-1.5': isResizing, 'bg-gray-300': !isResizing }"
                                    title="Drag to adjust width"
                                ></div>

                                <ProjectDeliverablesOverviewCard
                                    :project-id="projectId"
                                    :can-view-project-deliverables="canViewDeliverables"
                                    :is-collapsed="isDeliverablesCollapsed"
                                    @update:is-collapsed="toggleDeliverablesPanel"
                                />
                            </div>

                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">

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

                <!-- Main Tabs Section -->
                <ProjectTabsNavigation
                    ref="navigationRef"
                    v-model:selectedTab="selectedTab"
                    :can-view-emails="canViewEmails"
                    :can-view-notes="canViewNotes"
                    :can-view-deliverables="canViewDeliverables"
                    :can-view-seo-reports="canViewSeoReports"
                />

                <div v-if="selectedTab === null">
                    <ProjectTaskNotificationPrompt
                        :overdue-tasks="tasksDueToday.filter(task => new Date(task.due_date) < new Date()).length"
                        :due-today-tasks="tasksDueToday.filter(task => new Date(task.due_date).toDateString() === new Date().toDateString()).length"
                        @view-tasks="handleViewDueAndOverdueTasks"
                    />

                    <div v-if="tasksDueToday.length > 0" class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Due & Overdue Tasks</h4>
                            <button @click="selectedTab = 'tasks'" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                View All Tasks →
                            </button>
                        </div>
                        <TaskList
                            :tasks="tasksDueToday"
                            :project-id="projectId"
                            :show-project-column="false"
                            @task-updated="handleDueTaskUpdated"
                            @open-task-detail="handleOpenTaskDetailSidebar"
                        />
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
                    ref="tasksTabRef"
                    :project-id="projectId"
                    :project-users="project.users || []"
                    :can-manage-projects="canManageProjects"
                    :tasks-filter="tasksFilter"
                    @tasksUpdated="handleTasksUpdated"
                    @openTaskDetailSidebar="handleOpenTaskDetailSidebar"
                    @open-create-task-modal="openGlobalCreateTaskModal"
                    @filter-changed="(newFilter) => tasksFilter = newFilter"
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

                <!--                <ProjectDeliverablesTab-->
                <!--                    v-if="selectedTab === 'deliverables'"-->
                <!--                    :project-id="projectId"-->
                <!--                    :can-create-deliverables="canCreateDeliverables"-->
                <!--                    :can-view-deliverables="canViewDeliverables"-->
                <!--                    @deliverablesUpdated="handleDeliverablesUpdated"-->
                <!--                    @openDeliverableDetailSidebar="handleOpenDeliverableDetailSidebar"-->
                <!--                />-->

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
                :statusOptions="statusOptions"
                :clientRoleOptions="[]" :userRoleOptions="[]" :paymentTypeOptions="[]"
                @close="showEditModal = false"
                @submit="handleProjectSubmit"
            />
        </Modal>

        <MeetingModal
            :show="showMeetingModal"
            :project-name="project.name"
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

        <CreateTaskModal
            :show="showGlobalCreateTaskModal"
            :project-id="projectId"
            @close="showGlobalCreateTaskModal = false"
            @saved="handleGlobalCreateTaskSaved"
        />

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
th, td {
    min-width: 120px;
}
</style>
