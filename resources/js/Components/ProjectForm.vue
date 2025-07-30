<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
import ServicesAndPaymentForm from '@/Components/ServicesAndPaymentForm.vue';
import ProjectTransactions from '@/Components/ProjectTransactions.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useAuthUser, useProjectRole, usePermissions, useProjectPermissions, fetchProjectPermissions } from '@/Directives/permissions';
import { success, error, warning, info } from '@/Utils/notification';

// Import sub-components
import ProjectFormBasicInfo from '@/Components/ProjectForm/ProjectFormBasicInfo.vue';
import ProjectFormClientsUsers from '@/Components/ProjectForm/ProjectFormClientsUsers.vue';
import ProjectFormDocuments from '@/Components/ProjectForm/ProjectFormDocuments.vue';
import ProjectFormNotes from '@/Components/ProjectForm/ProjectFormNotes.vue';

// Assume these come from a composable for data fetching (though children now call them)
import { fetchRoles, fetchClients, fetchUsers } from '@/Components/ProjectForm/useProjectData';

// Use the permission utilities
const authUser = useAuthUser();
const project = ref({}); // 'project' ref is used by useProjectRole and useProjectPermissions

// Define props for the main ProjectForm component
const props = defineProps({
    project: { type: Object, default: () => ({}) }, // The project object to edit
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    clientRoleOptions: { type: Array, default: () => [] },
    userRoleOptions: { type: Array, default: () => [] },
    paymentTypeOptions: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) }, // Pass errors down from parent (Create/Edit page)
    generalError: { type: String, default: '' }, // Pass general error down from parent
    loading: { type: Boolean, default: false }, // Overall loading for ProjectForm's initial data fetch (from parent)
    isSaving: { type: Boolean, default: false }, // Overall saving state for the page (from parent)
});

// Watch for changes in the incoming 'project' prop to update the local 'project' ref
watch(() => props.project, (newProject) => {
    project.value = newProject || {};
}, { immediate: true, deep: true });

// Computed property for the current project ID
const projectId = computed(() => project.value?.id || null);

// Initialize project-specific permissions using the composable
const { permissions: projectPermissions, loading: projectPermissionsLoading, error: projectPermissionsError } = useProjectPermissions(projectId);
// Get the user's project-specific role using the composable
const userProjectRole = useProjectRole(project);

// Set up permission checking functions (canDo, canView, canManage) with project ID context
const { canDo, canView, canManage } = usePermissions(projectId);

// Permission-based flags for various sections/actions
const canManageProjects = canDo('manage_projects', userProjectRole);
const canCreateClients = canDo('create_clients', userProjectRole);
const canUploadProjectDocuments = canDo('upload_project_documents', userProjectRole);
const canManageProjectServicesAndPayments = canManage('project_services_and_payments', userProjectRole);
const canAddProjectNotes = canDo('add_project_notes', userProjectRole);
const canManageProjectUsers = canManage('project_users', userProjectRole);
const canManageProjectClients = canManage('project_clients', userProjectRole);
const canManageProjectBasicDetails = canDo('manage_project_basic_details', userProjectRole);

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
// These are now primarily for passing to sub-components for their dropdowns/multiselects
const dbClientRoles = ref([]);
const dbUserRoles = ref([]);
const clients = ref([]);
const users = ref([]);

// Main reactive state for the project form data (synced with props.project)
// This object will be passed down to child components via v-model.
// Child components will update their specific sections within this object.
const projectForm = reactive({
    id: null,
    name: '',
    description: '',
    website: '',
    social_media_link: '',
    preferred_keywords: '',
    google_chat_id: '',
    logo: null,
    documents: [],
    client_ids: [], // These will be populated by ProjectFormClientsUsers
    status: 'active',
    project_type: '',
    services: [], // Populated by ServicesAndPaymentForm
    service_details: [], // Populated by ServicesAndPaymentForm
    source: '',
    total_amount: '', // Populated by ServicesAndPaymentForm
    contract_details: '', // Populated by ProjectFormClientsUsers
    google_drive_link: '',
    payment_type: 'one_off', // Populated by ServicesAndPaymentForm
    user_ids: [], // Populated by ProjectFormClientsUsers
    notes: [], // Populated by ProjectFormNotes
    tags: [],
    tags_data: [],
    timezone: null
});

// Function to switch tabs. No data fetching here, children handle it.
const switchTab = (tabName) => {
    // Prevent switching to other tabs if project isn't saved yet (only basic info is available)
    if (tabName !== 'basic' && !projectForm.id) {
        warning('Please create the project first before managing this section.');
        activeTab.value = 'basic'; // Force back to basic
        return;
    }
    activeTab.value = tabName; // Update active tab
};

// Watch props.project and populate local projectForm
// This is for initial load of the main project data (ID, name, etc.)
watch(() => props.project, async (newProject) => {
    if (newProject) {
        Object.assign(projectForm, {
            id: newProject.id || null,
            name: newProject.name || '',
            description: newProject.description || '',
            website: newProject.website || '',
            social_media_link: newProject.social_media_link || '',
            preferred_keywords: newProject.preferred_keywords || '',
            google_chat_id: newProject.google_chat_id || '',
            google_drive_link: newProject.google_drive_link || '',
            logo: newProject.logo || null,
            status: newProject.status || 'active',
            project_type: newProject.project_type || '',
            source: newProject.source || '',
            total_amount: newProject.total_amount || '', // Still here for initial prop consistency
            payment_type: newProject.payment_type || 'one_off', // Still here for initial prop consistency
            contract_details: newProject.contract_details || '', // Still here for initial prop consistency
            timezone: newProject.timezone || null,
            // Initialize arrays from props.project if they contain initial data,
            // otherwise, child components will fetch/populate them.
            documents: newProject.documents || [],
            client_ids: newProject.clients ? newProject.clients.map(client => ({
                id: client.id,
                role_id: client.pivot?.role_id || (dbClientRoles.value.length > 0 ? dbClientRoles.value[0].value : null)
            })) : [],
            user_ids: newProject.users ? newProject.users.map(user => ({
                id: user.id,
                role_id: user.pivot?.role_id || (dbUserRoles.value.length > 0 ? dbUserRoles.value[0].value : null)
            })) : [],
            notes: newProject.notes ? newProject.notes.map(note => ({
                id: note.id,
                content: note.content,
                created_at: note.created_at,
                creator_name: note.creator_name || note.user?.name || note.creator?.name || 'Unknown'
            })) : [],
            services: newProject.services || [],
            service_details: newProject.service_details || [],
            tags: newProject.tags || [],
            tags_data: newProject.tags_data || [],
        });
        // If project ID is available, fetch project-specific permissions
        if (projectId.value) {
            await fetchProjectPermissions(projectId.value); // Await permissions fetch
        }
        // No need to call switchTab('basic') here to fetch data,
        // ProjectFormBasicInfo will fetch its own data on mount.
    }
}, { immediate: true, deep: true });


const emit = defineEmits(['close']); // Only 'close' event remains, 'submit' is gone

// Close form function (now navigates back, emitted to parent)
const closeForm = () => {
    emit('close'); // Emit to parent (Edit.vue) to handle navigation
};

// Fetch global roles, clients, and users once on component mount
onMounted(async () => {
    try {
        dbClientRoles.value = await fetchRoles('client');
        dbUserRoles.value = await fetchRoles('project');
        // Fetch all clients/users for selection, regardless of project ID initially
        clients.value = await fetchClients(true, null); // Pass true for canCreateClientsPermission to fetch all
        users.value = await fetchUsers(true, null); // Pass true for canManageProjects to fetch all
    } catch (err) {
        console.error('Error fetching global data for ProjectForm:', err);
    }
});
</script>

<template>
    <div class="p-6 w-full mx-auto bg-white rounded-xl shadow-2xl transition-all duration-300">
        <!-- Form Header - Removed modal specific close button -->
        <div class="flex justify-between items-center pb-4 mb-6 border-b border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800">{{ projectForm.id ? 'Edit Project' : 'Create New Project' }}</h2>
            <!-- No close button here, parent handles navigation -->
        </div>

        <!-- General Error Display (from parent) -->
        <div v-if="props.generalError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md relative mb-4" role="alert">
            <span class="block sm:inline">{{ props.generalError }}</span>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex -mb-px space-x-4 overflow-x-auto pb-2">
                <button
                    v-if="canManageProjects || !projectForm.id"
                    @click="switchTab('basic')"
                    :class="[
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200 whitespace-nowrap',
                        activeTab === 'basic'
                            ? 'border-indigo-600 text-indigo-700 bg-indigo-50'
                            : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    Basic Information
                </button>
                <button
                    v-if="projectForm.id && (canViewProjectClients || canViewProjectUsers)"
                    @click="switchTab('client')"
                    :class="[
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200 whitespace-nowrap',
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
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200 whitespace-nowrap',
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
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200 whitespace-nowrap',
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
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200 whitespace-nowrap',
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
                        'py-3 px-5 text-center border-b-2 font-medium text-base rounded-t-lg transition-colors duration-200 whitespace-nowrap',
                        activeTab === 'notes'
                            ? 'border-indigo-600 text-indigo-700 bg-indigo-50'
                            : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 hover:bg-gray-50'
                    ]"
                >
                    Notes
                </button>
            </nav>
        </div>

        <!-- Render active tab component based on activeTab state -->
        <div class="py-4">
            <!-- Tab 1: Basic Information -->
            <ProjectFormBasicInfo
                v-if="activeTab === 'basic'"
                v-model:projectForm="projectForm"
                :errors="props.errors"
                :statusOptions="statusOptions"
                :sourceOptions="sourceOptions"
                :canManageProjects="canManageProjects"
                :canManageProjectBasicDetails="canManageProjectBasicDetails"
                :isSaving="props.isSaving"
            />

            <!-- Tab 2: Client, Contract Details, and Users -->
            <ProjectFormClientsUsers
                v-if="activeTab === 'client'"
                v-model:projectForm="projectForm"
                :errors="props.errors"
                :clientRoleOptions="dbClientRoles"
                :userRoleOptions="dbUserRoles"
                :clients="clients"
                :users="users"
                :canViewProjectClients="canViewProjectClients"
                :canManageProjectClients="canManageProjectClients"
                :canViewProjectUsers="canViewProjectUsers"
                :canManageProjectUsers="canManageProjectUsers"
                :isSaving="props.isSaving"

            />

            <!-- Tab 3: Services & Payment -->
            <ServicesAndPaymentForm
                v-if="activeTab === 'services'"
                :projectId="projectForm.id"
                :departmentOptions="departmentOptions"
                :paymentTypeOptions="paymentTypeOptions"
                :canManageProjectServicesAndPayments="canManageProjectServicesAndPayments"
                :canViewProjectServicesAndPayments="canViewProjectServicesAndPayments"
                :isSaving="props.isSaving"
            />

            <!-- Tab 4: Transactions -->
            <ProjectTransactions
                v-if="activeTab === 'transactions' && canViewProjectTransactions"
                :projectId="projectForm.id"
                :userProjectRole="userProjectRole.value"
                :isSaving="props.isSaving"
            />

            <!-- Tab 5: Documents -->
            <ProjectFormDocuments
                v-if="activeTab === 'documents'"
                v-model:projectForm="projectForm"
                :errors="props.errors"
                :canUploadProjectDocuments="canUploadProjectDocuments"
                :canViewProjectDocuments="canViewProjectDocuments"
                :isSaving="props.isSaving"
            />

            <!-- Tab 6: Notes -->
            <ProjectFormNotes
                v-if="activeTab === 'notes'"
                v-model:projectForm="projectForm"
                :errors="props.errors"
                :canAddProjectNotes="canAddProjectNotes"
                :canViewProjectNotes="canViewProjectNotes"
                :projectId="projectId"
                :is-saving="props.isSaving"
            />
        </div>

        <!-- Close Button at the bottom -->
        <div class="mt-8 flex justify-end pt-4 border-t border-gray-200">
            <SecondaryButton @click="closeForm" class="px-6 py-2 rounded-lg text-lg">Close</SecondaryButton>
        </div>
    </div>
</template>

