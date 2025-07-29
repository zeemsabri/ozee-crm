<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
import ServicesAndPaymentForm from '@/Components/ServicesAndPaymentForm.vue';
import ProjectTransactions from '@/Components/ProjectTransactions.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useAuthUser, useProjectRole, usePermissions, useProjectPermissions, fetchProjectPermissions } from '@/Directives/permissions';
import { success, error, warning, info } from '@/Utils/notification';

// Import new sub-components
import ProjectFormBasicInfo from '@/Components/ProjectForm/ProjectFormBasicInfo.vue';
import ProjectFormClientsUsers from '@/Components/ProjectForm/ProjectFormClientsUsers.vue';
import ProjectFormDocuments from '@/Components/ProjectForm/ProjectFormDocuments.vue';
import ProjectFormNotes from '@/Components/ProjectForm/ProjectFormNotes.vue';

// Assume these come from a new composable for data fetching
import { fetchRoles, fetchClients, fetchUsers, fetchProjectSectionData } from '@/Components/ProjectForm/useProjectData';

// Use the permission utilities
const authUser = useAuthUser();
// 'project' ref is used by useProjectRole and useProjectPermissions to derive project-specific context
const project = ref({});

// Define props for the main ProjectForm component
const props = defineProps({
    show: { type: Boolean, required: true },
    project: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    // clientRoleOptions and userRoleOptions from props will be overridden by fetched roles if available
    clientRoleOptions: { type: Array, default: () => [] },
    userRoleOptions: { type: Array, default: () => [] },
    paymentTypeOptions: { type: Array, required: true },
});

// Watch for changes in the incoming 'project' prop to update the local 'project' ref
watch(() => props.project, (newProject) => {
    project.value = newProject || {};
}, { immediate: true });

// Computed property for the current project ID
const projectId = computed(() => project.value?.id || null);

// Initialize project-specific permissions using the composable
const { permissions: projectPermissions, loading: projectPermissionsLoading, error: projectPermissionsError } = useProjectPermissions(projectId);
// Get the user's project-specific role using the composable
const userProjectRole = useProjectRole(project);

// Check if user has a specific project role
const hasProjectRole = computed(() => !!userProjectRole.value);

// Check if user is a project manager in this specific project
const isProjectManager = computed(() => {
    if (!userProjectRole.value) return false;
    const roleName = userProjectRole.value.name;
    const roleSlug = userProjectRole.value.slug; // Corrected from userProjectS.value.slug
    return roleName === 'Manager' || roleName === 'Project Manager' || roleSlug === 'manager' || roleSlug === 'project-manager';
});

// Set up permission checking functions (canDo, canView, canManage) with project ID context
const { canDo, canView, canManage } = usePermissions(projectId);

// Permission-based flags for various sections/actions
const canManageProjects = canDo('manage_projects', userProjectRole);
const canCreateProjects = canDo('create_projects', userProjectRole);
const canCreateClients = canDo('create_clients', userProjectRole);
const canUploadProjectDocuments = canDo('upload_project_documents', userProjectRole);
const canManageProjectExpenses = canManage('project_expenses', userProjectRole);
const canManageProjectIncome = canManage('project_income', userProjectRole);
const canManageProjectServicesAndPayments = canManage('project_services_and_payments', userProjectRole);
const canAddProjectNotes = canDo('add_project_notes', userProjectRole);
const canManageProjectUsers = canManage('project_users', userProjectRole);
const canManageProjectClients = canManage('project_clients', userProjectRole);
const canManageProjectBasicDetails = canDo('manage_project_basic_details', userProjectRole); // Using canDo for specific action

const canViewProjectDocuments = canView('project_documents', userProjectRole);
const canViewProjectServicesAndPayments = canView('project_services_and_payments', userProjectRole);
const canViewProjectNotes = canView('project_notes', userProjectRole);
const canViewProjectUsers = canView('project_users', userProjectRole);
const canViewProjectClients = canView('project_clients', userProjectRole);
const canViewProjectTransactions = canView('project_transactions', userProjectRole);

// Tab management state
const activeTab = ref('basic');

// Computed property for clients/users tab name based on permissions
const clientsUsersTabName = computed(() => {
    if (canViewProjectClients.value && canViewProjectUsers.value) {
        return 'Clients and Users';
    } else if (canViewProjectClients.value) {
        return 'Clients';
    } else if (canViewProjectUsers.value) {
        return 'Users';
    }
    return 'Clients and Users'; // Fallback if no specific view permission
});

// Reactive refs for roles and entities fetched from API, to be passed to sub-components
const dbClientRoles = ref([]);
const dbUserRoles = ref([]);
const clients = ref([]);
const users = ref([]);

// Main reactive state for the project form data
const projectForm = reactive({
    id: null,
    name: '',
    description: '',
    website: '',
    social_media_link: '',
    preferred_keywords: '',
    google_chat_id: '',
    logo: null, // Will be handled by ProjectFormBasicInfo
    documents: [], // Will be handled by ProjectFormDocuments
    client_ids: [], // Will be handled by ProjectFormClientsUsers
    status: 'active',
    project_type: '',
    services: [], // Handled by ServicesAndPaymentForm component
    service_details: [], // Handled by ServicesAndPaymentForm component
    source: '',
    total_amount: '', // Handled by ServicesAndPaymentForm component
    contract_details: '', // Will be handled by ProjectFormClientsUsers
    google_drive_link: '',
    payment_type: 'one_off', // Handled by ServicesAndPaymentForm component
    user_ids: [], // Will be handled by ProjectFormClientsUsers
    notes: [], // Will be handled by ProjectFormNotes
    tags: [],
    tags_data: [],
    timezone: null
});

const errors = ref({}); // Centralized error messages
const generalError = ref(''); // General error message display
const loading = ref(false); // Loading state for data fetching
const showDebugInfo = ref(false); // Controls visibility of debugging information

// States for saving clients and users, used by ProjectFormClientsUsers
const clientSaving = ref(false);
const clientSaveSuccess = ref(false);
const clientSaveError = ref('');
const userSaving = ref(false);
const userSaveSuccess = ref(false);
const userSaveError = ref('');

// Function to switch tabs safely and trigger data fetching for the new tab
const switchTab = async (tabName) => {
    // Prevent switching to document tab if project isn't saved yet
    if (tabName === 'documents' && !projectForm.id) {
        warning('Please create the project first before managing documents.');
        return;
    }

    activeTab.value = tabName; // Update active tab

    // If project exists, fetch data for the selected tab
    if (projectForm.id) {
        loading.value = true;
        try {
            const data = await fetchProjectSectionData(projectForm.id, tabName, {
                canViewProjectClients: canViewProjectClients.value,
                canManageProjectClients: canManageProjectClients.value,
                canViewProjectUsers: canViewProjectUsers.value,
                canManageProjectUsers: canManageProjectUsers.value,
                canViewProjectServicesAndPayments: canViewProjectServicesAndPayments.value,
                canManageProjectServicesAndPayments: canManageProjectServicesAndPayments.value,
                canViewProjectDocuments: canViewProjectDocuments.value,
                canViewProjectNotes: canViewProjectNotes.value,
                canAddProjectNotes: canAddProjectNotes.value,
            });

            // Update projectForm based on fetched data for the current tab
            // This ensures data integrity when switching tabs
            if (data) {
                if (tabName === 'basic') {
                    // CRITICAL FIX: Ensure 'logo' is explicitly assigned here from the fetched data
                    Object.assign(projectForm, {
                        name: data.name || '',
                        description: data.description || '',
                        website: data.website || '',
                        social_media_link: data.social_media_link || '',
                        preferred_keywords: data.preferred_keywords || '',
                        google_chat_id: data.google_chat_id || '',
                        status: data.status || 'active',
                        project_type: data.project_type || '',
                        source: data.source || '',
                        google_drive_link: data.google_drive_link || '',
                        logo: data.logo || null, //
                        tags: data.tags_data || [],
                        tags_data: data.tags_data || [],
                        timezone: data.timezone || null
                    });
                } else if (tabName === 'client') {
                    // Update client_ids and user_ids arrays for MultiSelectWithRoles
                    projectForm.client_ids = data.clients ? data.clients.map(client => ({
                        id: client.id,
                        role_id: client.pivot?.role_id || (dbClientRoles.value.length > 0 ? dbClientRoles.value[0].value : 1)
                    })) : [];
                    projectForm.user_ids = data.users ? data.users.map(user => ({
                        id: user.id,
                        role_id: user.pivot?.role_id || (dbUserRoles.value.length > 0 ? dbUserRoles.value[0].value : 2)
                    })) : [];
                    projectForm.contract_details = data.contract_details || '';
                } else if (tabName === 'services') {
                    // ServicesAndPaymentForm will fetch its own data, but we keep these for general form structure
                    projectForm.services = data.services || [];
                    projectForm.service_details = data.service_details || [];
                    projectForm.total_amount = data.total_amount || '';
                    projectForm.payment_type = data.payment_type || 'one_off';
                } else if (tabName === 'documents') {
                    projectForm.documents = data || [];
                } else if (tabName === 'notes') {
                    projectForm.notes = data.map(note => ({ id: note.id, content: note.content })) || [];
                }
            }
        } catch (err) {
            generalError.value = `Failed to fetch ${tabName} data. Please try again.`;
            console.error(`Error fetching ${tabName} data:`, err);
        } finally {
            loading.value = false;
        }
    }
};

const emit = defineEmits(['close', 'submit']);

// New variable to track if initial data has been loaded for the current project
const hasLoadedInitialData = ref(false);

// This consolidated watch handles the main initialization when the modal opens or project prop changes
watch([() => props.show, () => props.project.id], async ([newShow, newProjectId], [oldShow, oldProjectId]) => {
    // Only run if the modal is becoming visible OR if the project ID explicitly changes
    // (and we haven't loaded initial data for the current project yet to prevent redundant calls)
    if (newShow && (newProjectId !== oldProjectId || !hasLoadedInitialData.value)) {
        // Reset errors and loading state
        errors.value = {};
        generalError.value = '';
        loading.value = true;
        hasLoadedInitialData.value = true; // Mark as started loading

        // Clear existing form data and populate with new props to ensure fresh state
        // Using Object.assign to maintain reactivity of the projectForm object itself
        Object.assign(projectForm, {
            id: props.project.id || null,
            name: props.project.name || '',
            description: props.project.description || '',
            website: props.project.website || '',
            social_media_link: props.project.social_media_link || '',
            preferred_keywords: props.project.preferred_keywords || '',
            google_chat_id: props.project.google_chat_id || '',
            logo: props.project.logo || null, // <--- ALSO ENSURE INITIAL LOGO FROM PROPS IS SET HERE
            // Clear arrays explicitly as they will be fetched by switchTab
            documents: [],
            client_ids: [],
            status: props.project.status || 'active',
            project_type: props.project.project_type || '',
            services: [],
            service_details: [],
            source: props.project.source || '',
            total_amount: props.project.total_amount || '',
            contract_details: props.project.contract_details || '',
            google_drive_link: props.project.google_drive_link || '',
            payment_type: props.project.payment_type || 'one_off',
            user_ids: [],
            notes: [],
            timezone: props.project.timezone || null
        });

        // If switching from an existing project to a new (empty) one,
        // and the current tab requires a project ID, switch back to 'basic'.
        const tabsRequiringId = ['documents', 'services', 'transactions', 'notes'];
        if (oldProjectId && !newProjectId && tabsRequiringId.includes(activeTab.value)) {
            activeTab.value = 'basic'; // Force switch to basic
        }

        try {
            // Fetch global roles, clients, and users once on load/project change
            dbClientRoles.value = await fetchRoles('client');
            dbUserRoles.value = await fetchRoles('project');
            clients.value = await fetchClients(canCreateClients.value, projectId.value);
            users.value = await fetchUsers(canCreateProjects.value, projectId.value);

            // Fetch project-specific permissions if project ID is available
            if (projectId.value) {
                await fetchProjectPermissions(projectId.value);
            }

            // Now, fetch data for the currently active tab
            await switchTab(activeTab.value);

        } catch (err) {
            generalError.value = 'Failed to load project data. Please check your connection.';
            console.error('Initial data load error:', err);
        } finally {
            loading.value = false;
        }
    } else if (!newShow) {
        // Reset hasLoadedInitialData when modal closes to allow re-initialization next time
        hasLoadedInitialData.value = false;
        // Reset projectForm completely when modal closes for a clean state
        Object.assign(projectForm, {
            id: null, name: '', description: '', website: '', social_media_link: '',
            preferred_keywords: '', google_chat_id: '', logo: null, documents: [],
            client_ids: [], status: 'active', project_type: '', services: [],
            service_details: [], source: '', total_amount: '', contract_details: '',
            google_drive_link: '', payment_type: 'one_off', user_ids: [], notes: [],
        });
        activeTab.value = 'basic'; // Reset to basic tab for next open
    }
}, { immediate: true }); // Run immediately on component creation

// Centralized error handler for API responses
const handleError = (err, defaultMessage) => {
    if (err.response && err.response.status === 422) {
        errors.value = err.response.data.errors; // Validation errors
        generalError.value = 'Please correct the highlighted fields.';
    } else if (err.response && err.response.data.message) {
        generalError.value = err.response.data.message; // API-specific error message
    } else {
        generalError.value = defaultMessage; // Generic error
        console.error('API Error:', err);
    }
};

// Handlers for events emitted by sub-components

// Handles creation of new project or update of basic info
const handleBasicInfoSubmit = async (formData, isNewProject) => {
    errors.value = {}; // Clear errors before new submission
    generalError.value = '';
    try {
        let response;
        if (isNewProject) {
            response = await window.axios.post('/api/projects', formData);
            projectForm.id = response.data.id; // Update project ID for subsequent operations
            emit('submit', response.data); // Emit to parent, e.g., to close modal or redirect
            success('Project created successfully!');
        } else {
            response = await window.axios.put(`/api/projects/${projectForm.id}/sections/basic`, formData);
            success('Basic information updated successfully!');
        }
        // If a logo file was part of the submission and project ID exists, upload it
        // (Note: ProjectFormBasicInfo is designed to handle logo upload directly after its own save now)
    } catch (err) {
        handleError(err, `Failed to ${isNewProject ? 'create' : 'update'} project.`);
    }
};

// Handles saving clients for the project
const handleSaveClients = async (clientIds) => {
    if (!projectForm.id) {
        clientSaveError.value = 'Please save the project first before saving clients.';
        return;
    }
    if (!clientIds || clientIds.length === 0) {
        clientSaveError.value = 'Please select at least one client to save.';
        return;
    }

    clientSaving.value = true;
    clientSaveSuccess.value = false;
    clientSaveError.value = ''; // Clear previous client save error

    try {
        await window.axios.post(`/api/projects/${projectForm.id}/attach-clients`, { client_ids: clientIds });
        clientSaveSuccess.value = true;
        success('Clients saved successfully!');
        setTimeout(() => { clientSaveSuccess.value = false; }, 3000); // Hide success message after 3 seconds
    } catch (err) {
        handleError(err, 'Failed to save clients.');
        clientSaveError.value = generalError.value; // Use the general error logic to set specific error
    } finally {
        clientSaving.value = false;
    }
};

// Handles saving users for the project
const handleSaveUsers = async (userIds) => {
    if (!projectForm.id) {
        userSaveError.value = 'Please save the project first before saving users.';
        return;
    }
    if (!userIds || userIds.length === 0) {
        userSaveError.value = 'Please select at least one user to save.';
        return;
    }

    userSaving.value = true;
    userSaveSuccess.value = false;
    userSaveError.value = ''; // Clear previous user save error

    try {
        await window.axios.post(`/api/projects/${projectForm.id}/attach-users`, { user_ids: userIds });
        userSaveSuccess.value = true;
        success('Users saved successfully!');
        setTimeout(() => { userSaveSuccess.value = false; }, 3000); // Hide success message after 3 seconds
    } catch (err) {
        handleError(err, 'Failed to save users.');
        userSaveError.value = generalError.value; // Use the general error logic to set specific error
    } finally {
        userSaving.value = false;
    }
};

// Handles uploading documents for the project
const handleUploadDocuments = async (filesToUpload) => {
    if (!projectForm.id) {
        generalError.value = 'Please save the project first before uploading documents.';
        return;
    }
    if (!filesToUpload || filesToUpload.length === 0) {
        generalError.value = 'Please select documents to upload.';
        return;
    }

    try {
        const formData = new FormData();
        filesToUpload.forEach((file, index) => {
            formData.append(`documents[${index}]`, file);
        });

        const response = await window.axios.post(
            `/api/projects/${projectForm.id}/documents`,
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        );

        // Update the documents array in projectForm with the server response
        projectForm.documents = response.data.documents || [];
        success('Documents uploaded successfully!');
        // Clear the file input manually if needed (controlled by the child component itself)
    } catch (err) {
        handleError(err, 'Failed to upload documents.');
    }
};

// Handles updating notes for the project
const handleUpdateNotes = async (notesContent) => {
    errors.value = {}; // Clear errors before new submission
    generalError.value = '';
    loading.value = true;
    try {
        await window.axios.put(`/api/projects/${projectForm.id}/sections/notes?type=private`, { notes: notesContent });
        success('Notes updated successfully!');
        loading.value = false;
    } catch (err) {
        loading.value = false;
        handleError(err, 'Failed to update notes.');
    }
};

// Close modal function
const closeModal = () => {
    // In a real application, you might want a confirmation dialog here
    // if there are unsaved changes, but we avoid native 'confirm()'.
    // A custom modal component would be used for such a feature.
    emit('close');
};
</script>

<template>
    <div class="p-6 w-full max-w-6xl mx-auto bg-white rounded-xl shadow-2xl transition-all duration-300 transform scale-100 opacity-100">
        <!-- Modal Header -->
        <div class="flex justify-between items-center pb-4 mb-6 border-b border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800">{{ projectForm.id ? 'Edit Project' : 'Create New Project' }}</h2>
            <button @click="closeModal" class="p-2 rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- General Error Display -->
        <div v-if="generalError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4" role="alert">
            <span class="block sm:inline">{{ generalError }}</span>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex -mb-px space-x-4">
                <button
                    v-if="canManageProjects || !projectForm.id"
                    @click="switchTab('basic')"
                    :class="[
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200',
                        activeTab === 'basic'
                            ? 'border-indigo-600 text-indigo-700 bg-indigo-50'
                            : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    Basic Information
                </button>
                <button
                    v-if="(projectForm.id && (canViewProjectClients || canViewProjectUsers)) || !projectForm.id"
                    @click="switchTab('client')"
                    :class="[
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200',
                        activeTab === 'client'
                            ? 'border-indigo-600 text-indigo-700 bg-indigo-50'
                            : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    {{ clientsUsersTabName }}
                </button>
                <button
                    v-if="projectForm.id && (canManageProjectServicesAndPayments || canViewProjectServicesAndPayments)"
                    @click="switchTab('services')"
                    :class="[
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200',
                        activeTab === 'services'
                            ? 'border-indigo-600 text-indigo-700 bg-indigo-50'
                            : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    Services & Payment
                </button>
                <button
                    v-if="projectForm.id && canViewProjectTransactions"
                    @click="switchTab('transactions')"
                    :class="[
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200',
                        activeTab === 'transactions'
                            ? 'border-indigo-600 text-indigo-700 bg-indigo-50'
                            : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    Transactions
                </button>
                <button
                    v-if="projectForm.id && canViewProjectDocuments"
                    @click="switchTab('documents')"
                    :class="[
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200',
                        activeTab === 'documents'
                            ? 'border-indigo-600 text-indigo-700 bg-indigo-50'
                            : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    Documents
                </button>
                <button
                    v-if="projectForm.id && (canAddProjectNotes || canViewProjectNotes)"
                    @click="switchTab('notes')"
                    :class="[
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200',
                        activeTab === 'notes'
                            ? 'border-indigo-600 text-indigo-700 bg-indigo-50'
                            : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    Notes
                </button>
            </nav>
        </div>

        <!-- Loading Indicator -->
        <div v-if="loading" class="text-center py-8 text-gray-500 text-lg">
            <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading project data...
        </div>

        <!-- Render active tab component based on activeTab state -->
        <div v-show="!loading" class="py-4">
            <!-- Tab 1: Basic Information -->
            <ProjectFormBasicInfo
                v-if="activeTab === 'basic'"
                v-model:projectForm="projectForm"
                :errors="errors"
                :statusOptions="statusOptions"
                :sourceOptions="sourceOptions"
                :canManageProjects="canManageProjects"
                :canManageProjectBasicDetails="canManageProjectBasicDetails"
                @submit="handleBasicInfoSubmit"
            />

            <!-- Tab 2: Client, Contract Details, and Users -->
            <ProjectFormClientsUsers
                v-if="activeTab === 'client'"
                v-model:projectForm="projectForm"
                :errors="errors"
                :clientRoleOptions="dbClientRoles"
                :userRoleOptions="dbUserRoles"
                :clients="clients"
                :users="users"
                :canViewProjectClients="canViewProjectClients"
                :canManageProjectClients="canManageProjectClients"
                :canViewProjectUsers="canViewProjectUsers"
                :canManageProjectUsers="canManageProjectUsers"
                :clientSaving="clientSaving"
                :clientSaveSuccess="clientSaveSuccess"
                :clientSaveError="clientSaveError"
                :userSaving="userSaving"
                :userSaveSuccess="userSaveSuccess"
                :userSaveError="userSaveError"
                @saveClients="handleSaveClients"
                @saveUsers="handleSaveUsers"
            />

            <!-- Tab 3: Services & Payment -->
            <ServicesAndPaymentForm
                v-if="activeTab === 'services'"
                :projectId="projectForm.id"
                :departmentOptions="departmentOptions"
                :paymentTypeOptions="paymentTypeOptions"
                :canManageProjectServicesAndPayments="canManageProjectServicesAndPayments"
                :canViewProjectServicesAndPayments="canViewProjectServicesAndPayments"
                @updated="switchTab('services')"
            />

            <!-- Tab 4: Transactions -->
            <ProjectTransactions
                v-if="activeTab === 'transactions' && canViewProjectTransactions"
                :projectId="projectForm.id"
                :userProjectRole="userProjectRole.value"
            />

            <!-- Tab 5: Documents -->
            <ProjectFormDocuments
                v-if="activeTab === 'documents'"
                v-model:projectForm="projectForm"
                :errors="errors"
                :canUploadProjectDocuments="canUploadProjectDocuments"
                :canViewProjectDocuments="canViewProjectDocuments"
                @uploadDocuments="handleUploadDocuments"
            />

            <!-- Tab 6: Notes -->
            <ProjectFormNotes
                v-if="activeTab === 'notes'"
                v-model:projectForm="projectForm"
                :errors="errors"
                :canAddProjectNotes="canAddProjectNotes"
                :canViewProjectNotes="canViewProjectNotes"
                @updateNotes="handleUpdateNotes"
                :project-id="projectId"
                :is-saving="loading"
            />
        </div>

        <!-- Close Button at the bottom -->
        <div class="mt-8 flex justify-end pt-4 border-t border-gray-200">
            <SecondaryButton @click="closeModal" class="px-6 py-2 rounded-lg text-lg">Close</SecondaryButton>
        </div>
    </div>
</template>

