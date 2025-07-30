<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useAuthUser, useProjectRole, usePermissions, useProjectPermissions, fetchProjectPermissions } from '@/Directives/permissions';
import { success, error, warning, info } from '@/Utils/notification';
import { fetchRoles, fetchClients, fetchUsers } from '@/Components/ProjectForm/useProjectData';

// Import sub-components (renamed basic info)
import ProjectEditBasicInfo from '@/Components/ProjectForm/ProjectEditBasicInfo.vue'; // Renamed
import ProjectFormClientsUsers from '@/Components/ProjectForm/ProjectFormClientsUsers.vue';
import ProjectFormDocuments from '@/Components/ProjectForm/ProjectFormDocuments.vue';
import ProjectFormNotes from '@/Components/ProjectForm/ProjectFormNotes.vue';
import ServicesAndPaymentForm from '@/Components/ServicesAndPaymentForm.vue'; // Assuming this component exists
import ProjectTransactions from '@/Components/ProjectTransactions.vue'; // Assuming this component exists

// Use the permission utilities
const authUser = useAuthUser();

// Define props for the main ProjectForm component
const props = defineProps({
    projectId: { // Now explicitly projectId
        type: [Number, String],
        default: null, // Can be null for new projects (though this component is for editing)
    },
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    clientRoleOptions: { type: Array, default: () => [] }, // These will be fetched here and passed
    userRoleOptions: { type: Array, default: () => [] }, // These will be fetched here and passed
    paymentTypeOptions: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) }, // Pass Inertia errors down
    isSaving: { type: Boolean, default: false }, // Overall page saving state (for disabling inputs)
});

// Reactive ref for the project ID to be used by permission composables
const currentProjectId = ref(props.projectId);


// Watch for changes in the incoming 'projectId' prop to update the local ref
watch(() => props.projectId, (newId) => {
    currentProjectId.value = newId;
    if (newId) {
        // Re-fetch project-specific permissions if project ID changes
        fetchProjectPermissions(newId);
    }
}, { immediate: true }); // Immediate ensures it runs on initial mount

// Initialize project-specific permissions using the composable
const { permissions: projectPermissions, loading: projectPermissionsLoading, error: projectPermissionsError } = useProjectPermissions(currentProjectId);
// Get the user's project-specific role using the composable
const userProjectRole = useProjectRole(currentProjectId); // Pass ref directly

// Set up permission checking functions (canDo, canView, canManage) with project ID context
const { canDo, canView, canManage } = usePermissions(currentProjectId);

// Permission-based flags for various sections/actions
const canManageProjects = canDo('manage_projects', userProjectRole);
const canCreateClients = canDo('create_clients', userProjectRole); // Used for fetching all clients
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
// These are global lists needed for selection in MultiSelectWithRoles
const dbClientRoles = ref([]);
const dbUserRoles = ref([]);
const allClients = ref([]); // Renamed from 'clients' to avoid confusion with project-specific clients
const allUsers = ref([]); // Renamed from 'users' to avoid confusion with project-specific users

// Function to switch tabs. No data fetching here, children handle it.
const switchTab = (tabName) => {
    // Prevent switching to other tabs if project isn't saved yet (only basic info is available)
    if (tabName !== 'basic' && !props.projectId) {
        warning('Please create the project first before managing this section.');
        activeTab.value = 'basic'; // Force back to basic
        return;
    }
    activeTab.value = tabName; // Update active tab
};

const emit = defineEmits(['close']); // Only 'close' event remains

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
        // Pass true for permissions to fetch all for selection dropdowns
        allClients.value = await fetchClients(true, null);
        allUsers.value = await fetchUsers(true, null);
    } catch (err) {
        console.error('Error fetching global data for ProjectForm:', err);
    }
});
</script>

<template>
    <div class="p-6 w-full mx-auto bg-white rounded-xl shadow-2xl transition-all duration-300">
        <!-- Form Header -->
        <div class="flex justify-between items-center pb-4 mb-6 border-b border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800">Edit Project</h2>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex -mb-px space-x-4 overflow-x-auto pb-2">
                <button
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
                    v-if="projectId && (canViewProjectClients || canViewProjectUsers)"
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
                    v-if="projectId && (canManageProjectServicesAndPayments || canViewProjectServicesAndPayments)"
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
                    v-if="projectId && canViewProjectTransactions"
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
                    v-if="projectId && canViewProjectDocuments"
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
                    v-if="projectId && (canAddProjectNotes || canViewProjectNotes)"
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
            <!-- Tab 1: Basic Information (Edit) -->
            <ProjectEditBasicInfo
                v-if="activeTab === 'basic'"
                :projectId="projectId"
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
                :projectId="projectId"
                :errors="props.errors"
                :clientRoleOptions="dbClientRoles"
                :userRoleOptions="dbUserRoles"
                :clients="allClients"
                :users="allUsers"
                :canViewProjectClients="canViewProjectClients"
                :canManageProjectClients="canManageProjectClients"
                :canViewProjectUsers="canViewProjectUsers"
                :canManageProjectUsers="canManageProjectUsers"
                :isSaving="props.isSaving"
            />

            <!-- Tab 3: Services & Payment -->
            <ServicesAndPaymentForm
                v-if="activeTab === 'services'"
                :projectId="projectId"
                :departmentOptions="departmentOptions"
                :paymentTypeOptions="paymentTypeOptions"
                :canManageProjectServicesAndPayments="canManageProjectServicesAndPayments"
                :canViewProjectServicesAndPayments="canViewProjectServicesAndPayments"
                :isSaving="props.isSaving"
            />

            <!-- Tab 4: Transactions -->
            <ProjectTransactions
                v-if="activeTab === 'transactions' && canViewProjectTransactions"
                :projectId="projectId"
                :userProjectRole="userProjectRole"
                :isSaving="props.isSaving"
            />

            <!-- Tab 5: Documents -->
            <ProjectFormDocuments
                v-if="activeTab === 'documents'"
                :projectId="projectId"
                :errors="props.errors"
                :canUploadProjectDocuments="canUploadProjectDocuments"
                :canViewProjectDocuments="canViewProjectDocuments"
                :isSaving="props.isSaving"
            />

            <!-- Tab 6: Notes -->
            <ProjectFormNotes
                v-if="activeTab === 'notes'"
                :projectId="projectId"
                :errors="props.errors"
                :canAddProjectNotes="canAddProjectNotes"
                :canViewProjectNotes="canViewProjectNotes"
                :is-saving="props.isSaving"
            />
        </div>

    </div>
</template>
