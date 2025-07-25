<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
import ServicesAndPaymentForm from '@/Components/ServicesAndPaymentForm.vue';
import ProjectTransactions from '@/Components/ProjectTransactions.vue';
/**
 * Debounce utility function to delay execution of a function
 * This helps improve user experience by preventing rapid, repeated function calls
 *
 * @param {Function} fn - The function to debounce
 * @param {number} delay - The delay in milliseconds
 * @returns {Function} - A debounced version of the function
 */
const debounce = (fn, delay) => {
    let timeoutId;
    return (...args) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
};
import { usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import MultiSelectWithRoles from '@/Components/MultiSelectWithRoles.vue';
import axios from 'axios';
import { useAuthUser, useProjectRole, usePermissions, useProjectPermissions, fetchProjectPermissions } from '@/Directives/permissions';
import { success, error, warning, info } from '@/Utils/notification';

// Use the permission utilities
const authUser = useAuthUser();
const project = ref({});

// Define props before using them
const props = defineProps({
    show: { type: Boolean, required: true },
    project: { type: Object, default: () => ({}) },
    statusOptions: { type: Array, required: true },
    departmentOptions: { type: Array, required: true },
    sourceOptions: { type: Array, required: true },
    clientRoleOptions: { type: Array, default: () => [] },
    userRoleOptions: { type: Array, default: () => [] },
    paymentTypeOptions: { type: Array, required: true },
});

// Set up project reference for the permission utilities
watch(() => props.project, (newProject) => {
    project.value = newProject || {};
}, { immediate: true });

// Get project ID from the project object
const projectId = computed(() => project.value?.id || null);

// Use project-specific permissions if project ID is available
const { permissions: projectPermissions, loading: projectPermissionsLoading, error: projectPermissionsError } = useProjectPermissions(projectId);

// Get the user's project-specific role
const userProjectRole = useProjectRole(project);

// Check if user has a specific project role
const hasProjectRole = computed(() => {
    return !!userProjectRole.value;
});

// Check if user is a project manager in this specific project
const isProjectManager = computed(() => {
    if (!userProjectRole.value) return false;

    // Check if the project-specific role is a manager role
    const roleName = userProjectRole.value.name;
    const roleSlug = userProjectRole.value.slug;

    return roleName === 'Manager' ||
        roleName === 'Project Manager' ||
        roleSlug === 'manager' ||
        roleSlug === 'project-manager';
});

// Set up permission checking functions with project ID
const { canDo, canView, canManage } = usePermissions(projectId);

// Permission-based checks using the permission utilities
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
const canManageProjectBasicDetails = canManage('project_basic_details', userProjectRole);

const canViewProjectDocuments = canView('project_documents', userProjectRole);
const canViewProjectServicesAndPayments = canView('project_services_and_payments', userProjectRole);
const canViewProjectNotes = canView('project_notes', userProjectRole);
const canViewProjectUsers = canView('project_users', userProjectRole);
const canViewProjectClients = canView('project_clients', userProjectRole);
const canViewProjectTransactions = canView('project_transactions', userProjectRole);

// Tab management
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
    return 'Clients and Users'; // Fallback
});

// Function to switch tabs safely
const switchTab = (tabName) => {
    // If trying to access documents tab but no project ID exists, don't switch
    if (tabName === 'documents' && !projectForm.id) {
        return;
    }

    // Set the active tab
    activeTab.value = tabName;

    // If we have a project ID, fetch data for the selected tab
    if (projectForm.id) {
        // Show loading indicator
        loading.value = true;

        // Fetch data based on the selected tab
        switch (tabName) {
            case 'basic':
                fetchBasicData(projectForm.id).finally(() => {
                    loading.value = false;
                });
                break;
            case 'client':
                if (canViewProjectClients.value || canManageProjectClients.value ||
                    canViewProjectUsers.value || canManageProjectUsers.value) {
                    fetchClientsAndUsersData(projectForm.id).finally(() => {
                        loading.value = false;
                    });
                } else {
                    loading.value = false;
                }
                break;
            case 'services':
                if (canViewProjectServicesAndPayments.value || canManageProjectServicesAndPayments.value) {
                    fetchServicesAndPaymentData(projectForm.id).finally(() => {
                        loading.value = false;
                    });
                } else {
                    loading.value = false;
                }
                break;
            case 'transactions':
                // Transactions are handled by ProjectTransactions component
                loading.value = false;
                break;
            case 'documents':
                if (canViewProjectDocuments.value) {
                    fetchDocumentsData(projectForm.id).finally(() => {
                        loading.value = false;
                    });
                } else {
                    loading.value = false;
                }
                break;
            case 'notes':
                if (canViewProjectNotes.value || canAddProjectNotes.value) {
                    fetchNotesData(projectForm.id).finally(() => {
                        loading.value = false;
                    });
                } else {
                    loading.value = false;
                }
                break;
            default:
                loading.value = false;
                break;
        }
    }
};

// Define reactive refs for roles
const dbClientRoles = ref([]);
const dbUserRoles = ref([]);

// Internal state for clients and users
const clients = ref([]);
const users = ref([]);

// Fetch roles from the database
const fetchRoles = async () => {
    try {
        // Fetch client roles
        const clientResponse = await axios.get('/api/roles?type=client');
        const clientRoles = clientResponse.data;

        // Map client roles to the format expected by the dropdowns
        dbClientRoles.value = clientRoles.map(role => ({
            value: role.id,
            label: role.name
        }));

        // Fetch project roles
        const projectResponse = await axios.get('/api/roles?type=project');
        const projectRoles = projectResponse.data;

        // Map project roles to the format expected by the dropdowns
        dbUserRoles.value = projectRoles.map(role => ({
            value: role.id,
            label: role.name
        }));
    } catch (error) {
        console.error('Error fetching roles:', error);
    }
};

// Fetch clients from the database
const fetchClients = async () => {
    try {
        // If user has create_clients permission, always fetch all clients
        if (canCreateClients.value) {
            const response = await window.axios.get('/api/clients');
            clients.value = response.data.data || response.data;
        }
        // Otherwise, if we have a project ID, use the project-specific endpoint
        else if (projectId.value) {
            const response = await window.axios.get(`/api/projects/${projectId.value}/clients`);
            clients.value = response.data;
        } else {
            // Fall back to the global endpoint if no project ID is available (e.g., when creating a new project)
            const response = await window.axios.get('/api/clients');
            clients.value = response.data.data || response.data;
        }
    } catch (error) {
        console.error('Error fetching clients:', error);
        generalError.value = 'Failed to load clients.';
    }
};

// Fetch users from the database
const fetchUsers = async () => {
    try {
        // If user has create_projects permission, always fetch all users
        if (canCreateProjects.value) {
            const response = await window.axios.get('/api/users');
            users.value = response.data;
        }
        // Otherwise, if we have a project ID, use the project-specific endpoint
        else if (projectId.value) {
            const response = await window.axios.get(`/api/projects/${projectId.value}/users`);
            users.value = response.data;
        } else {
            // Fall back to the global endpoint if no project ID is available (e.g., when creating a new project)
            const response = await window.axios.get('/api/users');
            users.value = response.data;
        }
    } catch (error) {
        console.error('Error fetching users:', error);
        generalError.value = 'Failed to load users.';
    }
};

// Computed properties to use either props or fetched roles
const clientRoleOptionsComputed = computed(() => {
    return dbClientRoles.value.length > 0 ? dbClientRoles.value : props.clientRoleOptions;
});

const userRoleOptionsComputed = computed(() => {
    return dbUserRoles.value.length > 0 ? dbUserRoles.value : props.userRoleOptions;
});

// Function to fetch basic project data
const fetchBasicData = async (projectId) => {
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/sections/basic`);
        const basicData = response.data;

        // Update basic project information
        projectForm.name = basicData.name || '';
        projectForm.description = basicData.description || '';
        projectForm.website = basicData.website || '';
        projectForm.social_media_link = basicData.social_media_link || '';
        projectForm.preferred_keywords = basicData.preferred_keywords || '';
        projectForm.google_chat_id = basicData.google_chat_id || '';
        projectForm.status = basicData.status || 'active';
        projectForm.project_type = basicData.project_type || '';
        projectForm.source = basicData.source || '';
        projectForm.google_drive_link = basicData.google_drive_link || '';

        return basicData;
    } catch (error) {
        console.error('Error fetching basic project data:', error);
        generalError.value = 'Failed to fetch basic project data.';
        return null;
    }
};

// Function to fetch clients and users data
const fetchClientsAndUsersData = async (projectId) => {
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/sections/clients-users`);
        const data = response.data;

        // Update client_ids with the latest data
        if (data.clients && data.clients.length > 0) {
            projectForm.client_ids = data.clients.map(client => {
                let role_id = client.pivot?.role_id ||
                    (dbClientRoles.value.length > 0 ? dbClientRoles.value[0].value : 1);
                return { id: client.id, role_id };
            });
        }

        // Update user_ids with the latest data
        if (data.users && data.users.length > 0) {
            projectForm.user_ids = data.users.map(user => {
                let role_id = user.pivot?.role_id ||
                    (dbUserRoles.value.length > 0 ? dbUserRoles.value[0].value : 2);
                return { id: user.id, role_id };
            });
        }

        // Update contract details if available
        if (data.contract_details !== undefined) {
            projectForm.contract_details = data.contract_details || '';
        }

        return data;
    } catch (error) {
        console.error('Error fetching clients and users data:', error);
        generalError.value = 'Failed to fetch clients and users data.';
        return null;
    }
};

// Function to fetch services and payment data
const fetchServicesAndPaymentData = async (projectId) => {
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/sections/services-payment`);
        const data = response.data;

        // Update services and payment information
        projectForm.services = data.services || [];
        projectForm.service_details = data.service_details || [];
        projectForm.total_amount = data.total_amount || '';
        projectForm.payment_type = data.payment_type || 'one_off';

        return data;
    } catch (error) {
        console.error('Error fetching services and payment data:', error);
        generalError.value = 'Failed to fetch services and payment data.';
        return null;
    }
};

// Function to fetch documents data
const fetchDocumentsData = async (projectId) => {
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/sections/documents`);
        const data = response.data;

        // Update documents
        projectForm.documents = data.documents || [];

        return data;
    } catch (error) {
        console.error('Error fetching documents data:', error);
        generalError.value = 'Failed to fetch documents data.';
        return null;
    }
};

// Function to fetch notes data
const fetchNotesData = async (projectId) => {
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/sections/notes`);
        const notes = response.data;

        // Update notes
        projectForm.notes = notes.map(note => ({ content: note.content }));

        return notes;
    } catch (error) {
        console.error('Error fetching notes data:', error);
        generalError.value = 'Failed to fetch notes data.';
        return null;
    }
};

// Function to fetch project data based on the active tab
const fetchProjectData = async (projectId) => {
    try {
        // Always fetch basic data first
        await fetchBasicData(projectId);

        // Fetch data for the active tab
        switch (activeTab.value) {
            case 'client':
                if (canViewProjectClients.value || canManageProjectClients.value ||
                    canViewProjectUsers.value || canManageProjectUsers.value) {
                    await fetchClientsAndUsersData(projectId);
                }
                break;
            case 'services':
                if (canViewProjectServicesAndPayments.value || canManageProjectServicesAndPayments.value) {
                    await fetchServicesAndPaymentData(projectId);
                }
                break;
            case 'documents':
                if (canViewProjectDocuments.value) {
                    await fetchDocumentsData(projectId);
                }
                break;
            case 'notes':
                if (canViewProjectNotes.value || canAddProjectNotes.value) {
                    await fetchNotesData(projectId);
                }
                break;
        }
    } catch (error) {
        console.error('Error fetching project data:', error);
        generalError.value = 'Failed to fetch project data.';
    }
};

// Fetch data when component is mounted
onMounted(() => {
    fetchRoles();
    fetchClients();
    fetchUsers();

    // Fetch project-specific permissions if project ID is available
    if (projectId.value) {
        fetchProjectPermissions(projectId.value)
            .then(permissions => {
                // Success - no logging needed
            })
            .catch(error => {
                // Error handled by the permissions utility
            });
    }
});

// Watch for changes to project ID and fetch project-specific permissions, users, and clients
watch(projectId, (newProjectId, oldProjectId) => {
    if (newProjectId && newProjectId !== oldProjectId) {
        // Fetch project-specific permissions
        fetchProjectPermissions(newProjectId)
            .then(permissions => {
                // Success - no logging needed
            })
            .catch(error => {
                // Error handled by the permissions utility
            });

        // Fetch users based on the new project ID and permissions
        fetchUsers();

        // Fetch clients based on the new project ID and permissions
        fetchClients();
    }
});

const emit = defineEmits(['close', 'submit']);

const errors = ref({});
const generalError = ref('');
const loading = ref(false); // Loading state for data fetching
const showDebugInfo = ref(false); // Controls visibility of debugging information

// State for saving clients and users
const clientSaving = ref(false);
const clientSaveSuccess = ref(false);
const clientSaveError = ref('');
const userSaving = ref(false);
const userSaveSuccess = ref(false);
const userSaveError = ref('');

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
    client_ids: [],
    status: 'active',
    project_type: '',
    services: [],
    service_details: [],
    source: '',
    total_amount: '',
    contract_details: '',
    google_drive_link: '',
    payment_type: 'one_off',
    user_ids: [],
    notes: [],
});

// Ensure arrays are initialized and fetch data when modal is opened
watch(() => props.show, (isVisible) => {
    if (isVisible) {
        // Initialize arrays if they're undefined
        if (!projectForm.client_ids) projectForm.client_ids = [];
        if (!projectForm.user_ids) projectForm.user_ids = [];

        // If editing an existing project, fetch the latest data
        if (projectForm.id) {
            fetchProjectData(projectForm.id);
        }
    }
}, { immediate: true });

// Initialize form with project data
watch(() => props.project, (newProject) => {
    const previousId = projectForm.id;
    projectForm.id = newProject.id || null;

    // If we're switching from an existing project to a new project
    // and we're on the documents tab, switch to basic tab
    if (previousId && !projectForm.id && activeTab.value === 'documents') {
        switchTab('basic');
    }

    projectForm.name = newProject.name || '';
    projectForm.description = newProject.description || '';
    projectForm.website = newProject.website || '';
    projectForm.social_media_link = newProject.social_media_link || '';
    projectForm.preferred_keywords = newProject.preferred_keywords || '';
    projectForm.google_chat_id = newProject.google_chat_id || '';
    projectForm.logo = newProject.logo || null;
    projectForm.documents = newProject.documents || [];

    // Don't initialize client_ids from project prop
    // We'll fetch this data when the client tab is opened
    projectForm.client_ids = [];

    projectForm.status = newProject.status || 'active';
    projectForm.project_type = newProject.project_type || '';
    projectForm.services = newProject.services || [];
    projectForm.service_details = newProject.service_details || [];
    projectForm.source = newProject.source || '';
    projectForm.total_amount = newProject.total_amount || '';
    projectForm.contract_details = newProject.contract_details || '';
    projectForm.google_drive_link = newProject.google_drive_link || '';
    projectForm.payment_type = newProject.payment_type || 'one_off';

    // Don't initialize user_ids from project prop
    // We'll fetch this data when the client tab is opened
    projectForm.user_ids = [];

    projectForm.notes = newProject.notes ? newProject.notes.map(note => ({ content: note.content })) : [];
    errors.value = {};
    generalError.value = '';
}, { immediate: true });

// Function to create a new project
const createProject = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        // Create a clean copy of the form data with basic information and user_ids
        const formData = {
            name: projectForm.name,
            description: projectForm.description,
            website: projectForm.website,
            social_media_link: projectForm.social_media_link,
            preferred_keywords: projectForm.preferred_keywords,
            google_chat_id: projectForm.google_chat_id,
            status: projectForm.status,
            project_type: projectForm.project_type,
            source: projectForm.source,
            google_drive_link: projectForm.google_drive_link,
            user_ids: projectForm.user_ids,
        };

        // Store the current logo value
        const currentLogo = projectForm.logo;

        // If logo is a File object, remove it from the JSON submission
        if (typeof currentLogo === 'object' && currentLogo !== null && 'name' in currentLogo) {
            delete formData.logo;
        }

        // Create the project
        const response = await window.axios.post('/api/projects', formData);

        // Update the project ID
        projectForm.id = response.data.id;

        // If there was a logo file, upload it separately after the project is saved
        if (typeof currentLogo === 'object' && currentLogo !== null && 'name' in currentLogo) {
            await uploadLogo(currentLogo, projectForm.id);
        }

        emit('submit', response.data);
    } catch (error) {
        handleError(error, 'Failed to create project.');
    }
};

// Function to update basic information
const updateBasicInfo = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        // Create a clean copy of the form data with only basic information
        const formData = {
            name: projectForm.name,
            description: projectForm.description,
            website: projectForm.website,
            social_media_link: projectForm.social_media_link,
            preferred_keywords: projectForm.preferred_keywords,
            google_chat_id: projectForm.google_chat_id,
            status: projectForm.status,
            project_type: projectForm.project_type,
            source: projectForm.source,
            google_drive_link: projectForm.google_drive_link,
        };

        // Store the current logo value
        const currentLogo = projectForm.logo;

        // If logo is a File object, remove it from the JSON submission
        if (typeof currentLogo === 'object' && currentLogo !== null && 'name' in currentLogo) {
            delete formData.logo;
        }

        // Update the project
        const response = await window.axios.put(`/api/projects/${projectForm.id}/sections/basic`, formData);

        // If there was a logo file, upload it separately
        if (typeof currentLogo === 'object' && currentLogo !== null && 'name' in currentLogo) {
            await uploadLogo(currentLogo, projectForm.id);
        }

        // Show success message
        success('Basic information updated successfully!');
    } catch (error) {
        handleError(error, 'Failed to update basic information.');
    }
};

// Function to update notes
const updateNotes = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        // Create a clean copy of the form data with only notes
        const formData = {
            notes: projectForm.notes,
        };

        // Update the project
        const response = await window.axios.put(`/api/projects/${projectForm.id}/sections/notes`, formData);

        // Show success message
        success('Notes updated successfully!');
    } catch (error) {
        handleError(error, 'Failed to update notes.');
    }
};

// Helper function to handle errors
const handleError = (error, defaultMessage) => {
    if (error.response && error.response.status === 422) {
        errors.value = error.response.data.errors;
    } else if (error.response && error.response.data.message) {
        generalError.value = error.response.data.message;
    } else {
        generalError.value = defaultMessage;
        console.error('Error:', error);
    }
};

// Legacy function for backward compatibility
const submitForm = async () => {
    if (!projectForm.id) {
        await createProject();
    } else {
        // Determine which update function to call based on the active tab
        switch (activeTab.value) {
            case 'basic':
                await updateBasicInfo();
                break;
            case 'services':
                // Services & Payment is now handled by the ServicesAndPaymentForm component
                warning('Please use the Update Services & Payment button in the Services tab.');
                break;
            case 'notes':
                await updateNotes();
                break;
            default:
                // For other tabs, just update basic information
                await updateBasicInfo();
                break;
        }
    }
};

// Track if form has been modified
const formModified = ref(false);
const originalForm = ref({});

// Watch for changes to the form
watch(projectForm, () => {
    if (Object.keys(originalForm.value).length > 0) {
        formModified.value = true;
    }
}, { deep: true });

// Store original form state after initial load
watch(() => props.show, (isVisible) => {
    if (isVisible) {
        // Wait for the form to be populated with project data
        setTimeout(() => {
            originalForm.value = JSON.parse(JSON.stringify(projectForm));
            formModified.value = false;
        }, 100);
    }
});

const closeModal = () => {
    if (formModified.value) {
        if (confirm('You have unsaved changes. Are you sure you want to close this form?')) {
            emit('close');
        }
    } else {
        emit('close');
    }
};

// Function to upload logo separately
const uploadLogo = async (logoFile, projectId) => {
    try {
        const formData = new FormData();
        formData.append('logo', logoFile);

        // Call the logo upload API endpoint
        const response = await window.axios.post(
            `/api/projects/${projectId}/logo`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        );

        // Update the logo in the form with the response from the server
        if (response.data && response.data.logo) {
            projectForm.logo = response.data.logo;
        }

        return response;
    } catch (error) {
        console.error('Error uploading logo:', error);
        // Don't show error to user as this is a background operation
        // The project was already saved successfully
    }
};

// Function to upload documents separately
const saveClients = async () => {
    if (!projectForm.id) {
        clientSaveError.value = 'Please save the project first before saving clients.';
        return;
    }

    if (!projectForm.client_ids || projectForm.client_ids.length === 0) {
        clientSaveError.value = 'Please select at least one client to save.';
        return;
    }

    clientSaving.value = true;
    clientSaveSuccess.value = false;
    clientSaveError.value = '';

    try {
        // Call the attach-clients API endpoint
        const response = await window.axios.post(
            `/api/projects/${projectForm.id}/attach-clients`,
            { client_ids: projectForm.client_ids }
        );

        // Show success message
        clientSaveSuccess.value = true;

        // Hide success message after 3 seconds
        setTimeout(() => {
            clientSaveSuccess.value = false;
        }, 3000);
    } catch (error) {
        if (error.response && error.response.status === 422) {
            clientSaveError.value = 'Validation error. Please check your input.';
        } else if (error.response && error.response.data.message) {
            clientSaveError.value = error.response.data.message;
        } else {
            clientSaveError.value = 'Failed to save clients.';
            console.error('Error saving clients:', error);
        }
    } finally {
        clientSaving.value = false;
    }
};

const saveUsers = async () => {
    if (!projectForm.id) {
        userSaveError.value = 'Please save the project first before saving users.';
        return;
    }

    if (!projectForm.user_ids || projectForm.user_ids.length === 0) {
        userSaveError.value = 'Please select at least one user to save.';
        return;
    }

    userSaving.value = true;
    userSaveSuccess.value = false;
    userSaveError.value = '';

    try {
        // Call the attach-users API endpoint
        const response = await window.axios.post(
            `/api/projects/${projectForm.id}/attach-users`,
            { user_ids: projectForm.user_ids }
        );

        // Show success message
        userSaveSuccess.value = true;

        // Hide success message after 3 seconds
        setTimeout(() => {
            userSaveSuccess.value = false;
        }, 3000);
    } catch (error) {
        if (error.response && error.response.status === 422) {
            userSaveError.value = 'Validation error. Please check your input.';
        } else if (error.response && error.response.data.message) {
            userSaveError.value = error.response.data.message;
        } else {
            userSaveError.value = 'Failed to save users.';
            console.error('Error saving users:', error);
        }
    } finally {
        userSaving.value = false;
    }
};

const uploadDocuments = async () => {
    if (!projectForm.id) {
        generalError.value = 'Please save the project first before uploading documents.';
        return;
    }

    if (!projectForm.documents || !projectForm.documents.some(doc => typeof doc === 'object' && doc !== null && 'name' in doc && 'size' in doc && 'type' in doc)) {
        generalError.value = 'Please select documents to upload.';
        return;
    }

    try {
        const formData = new FormData();

        // Filter out only File objects and add them to FormData
        const filesToUpload = projectForm.documents.filter(doc => typeof doc === 'object' && doc !== null && 'name' in doc && 'size' in doc && 'type' in doc);
        filesToUpload.forEach((file, index) => {
            formData.append(`documents[${index}]`, file);
        });

        // Call the documents upload API endpoint
        const response = await window.axios.post(
            `/api/projects/${projectForm.id}/documents`,
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }
        );

        // Update the documents in the form with the response from the server
        projectForm.documents = response.data.documents || [];

        // Clear the file input to allow selecting the same files again if needed
        document.getElementById('documents').value = '';

        // Show success message
        alert('Documents uploaded successfully!');

        // Don't mark the form as modified since document uploads are separate
        const currentFormState = JSON.parse(JSON.stringify(projectForm));
        originalForm.value = currentFormState;
        formModified.value = false;
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to upload documents.';
            console.error('Error uploading documents:', error);
        }
    }
};
</script>

<template>
    <div class="p-6 w-full max-w-6xl mx-auto bg-white rounded-lg shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-medium text-gray-900">{{ projectForm.id ? 'Edit Project' : 'Create New Project' }}</h2>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>

        <!-- Debug Information (Collapsible) -->
        <div v-if="projectId" class="mb-4">
            <div v-if="showDebugInfo" class="text-xs text-gray-500 mt-2 p-2 bg-gray-100 rounded">
                <div class="font-bold">Project ID: {{ projectId }}</div>
                <div>{{ userProjectRole ? 'Project Role: ' + (userProjectRole.value?.name || 'None') : 'No Project Role' }}</div>

                <!-- Project Permissions -->
                <div class="mt-1">
                    <div class="font-semibold">Project Permissions:</div>
                    <div v-if="projectPermissionsLoading">Loading project permissions...</div>
                    <div v-else-if="projectPermissionsError">Error loading project permissions</div>
                    <div v-else-if="projectPermissions">
                        Count: {{ projectPermissions.permissions ? projectPermissions.permissions.length : 0 }}
                        <div v-if="projectPermissions.permissions && projectPermissions.permissions.length > 0">
                            <div class="font-semibold">Permissions:</div>
                            <ul class="list-disc ml-4">
                                <li v-for="perm in projectPermissions.permissions.slice(0, 5)" :key="perm.slug">
                                    {{ perm.name }} ({{ perm.source }})
                                </li>
                                <li v-if="projectPermissions.permissions.length > 5">
                                    ... and {{ projectPermissions.permissions.length - 5 }} more
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mt-1">Can manage projects: {{ canManageProjects ? 'Yes' : 'No' }}</div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex -mb-px">
                <button
                    v-if="canManageProjects"
                    @click="switchTab('basic')"
                    :class="[
                        'py-2 px-4 text-center border-b-2 font-medium text-sm',
                        activeTab === 'basic'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]"
                >
                    Basic Information
                </button>
                <button
                    v-if="canViewProjectClients || canViewProjectUsers"
                    @click="switchTab('client')"
                    :class="[
                        'py-2 px-4 text-center border-b-2 font-medium text-sm',
                        activeTab === 'client'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]"
                >
                    {{ clientsUsersTabName }}
                </button>
                <button
                    v-if="(projectForm.id && (canManageProjectServicesAndPayments || canViewProjectServicesAndPayments))"
                    @click="switchTab('services')"
                    :class="[
                        'py-2 px-4 text-center border-b-2 font-medium text-sm',
                        activeTab === 'services'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]"
                >
                    Services & Payment
                </button>
                <button
                    v-if="projectForm.id && canViewProjectTransactions"
                    @click="switchTab('transactions')"
                    :class="[
                        'py-2 px-4 text-center border-b-2 font-medium text-sm',
                        activeTab === 'transactions'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]"
                >
                    Transactions
                </button>
                <button
                    v-if="projectForm.id && canViewProjectDocuments"
                    @click="switchTab('documents')"
                    :class="[
                        'py-2 px-4 text-center border-b-2 font-medium text-sm',
                        activeTab === 'documents'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]"
                >
                    Documents
                </button>
                <button
                    v-if="projectForm.id && (canAddProjectNotes || canViewProjectNotes)"
                    @click="switchTab('notes')"
                    :class="[
                        'py-2 px-4 text-center border-b-2 font-medium text-sm',
                        activeTab === 'notes'
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                    ]"
                >
                    Notes
                </button>
            </nav>
        </div>

        <form @submit.prevent="">
            <!-- Tab 1: Basic Information -->
            <div v-if="activeTab === 'basic'">
                <div class="mb-4">
                    <InputLabel for="name" value="Project Name" />
                    <TextInput id="name" type="text" class="mt-1 block w-full" v-model="projectForm.name" required autofocus :disabled="!canManageProjects" />
                    <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="description" value="Description" />
                    <textarea id="description" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="projectForm.description" :disabled="!canManageProjects"></textarea>
                    <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="website" value="Website" />
                    <TextInput id="website" type="url" class="mt-1 block w-full" v-model="projectForm.website" :disabled="!canManageProjects" />
                    <InputError :message="errors.website ? errors.website[0] : ''" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="preferred_keywords" value="Client Preferred Keywords" />
                    <TextInput id="preferred_keywords" type="text" class="mt-1 block w-full" v-model="projectForm.preferred_keywords" :disabled="!canManageProjects" />
                    <InputError :message="errors.preferred_keywords ? errors.preferred_keywords[0] : ''" class="mt-2" />
                </div>

                <div class="mb-4" v-if="canManageProjectBasicDetails">
                    <InputLabel for="google_chat_id" value="Google Chat ID" />
                    <TextInput id="google_chat_id" type="text" class="mt-1 block w-full" v-model="projectForm.google_chat_id" :disabled="!canManageProjects" />
                    <InputError :message="errors.google_chat_id ? errors.google_chat_id[0] : ''" class="mt-2" />
                </div>

                <div class="mb-4" v-if="canManageProjectBasicDetails">
                    <InputLabel for="google_drive_link" value="Google Drive Link" />
                    <TextInput id="google_drive_link" type="text" class="mt-1 block w-full" v-model="projectForm.google_drive_link" :disabled="!canManageProjects" />
                    <InputError :message="errors.google_chat_id ? errors.google_chat_id[0] : ''" class="mt-2" />
                </div>

                <div class="mb-4">
                    <InputLabel for="status" value="Status" />
                    <select id="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="projectForm.status" required :disabled="!canManageProjects">
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="errors.status ? errors.status[0] : ''" class="mt-2" />
                </div>


                <div class="mb-4">
                    <InputLabel for="source" value="Source" />
                    <select id="source" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="projectForm.source" :disabled="!canManageProjects">
                        <option value="" disabled>Select a Source</option>
                        <option v-for="option in sourceOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="errors.source ? errors.source[0] : ''" class="mt-2" />
                </div>

                <div v-if="canManageProjects" class="mt-6 flex justify-end">
                    <PrimaryButton
                        @click="projectForm.id ? updateBasicInfo() : createProject()"
                        :disabled="!canManageProjects"
                        v-if="(projectForm.id && canManageProjectBasicDetails) || !projectForm.id"
                    >
                        {{ projectForm.id ? 'Update Basic Information' : 'Create Project' }}
                    </PrimaryButton>
                </div>
            </div>

            <!-- Tab 2: Client, Contract Details, and Contractors -->
            <div v-if="activeTab === 'client'">
                <div class="mb-4" v-if="canViewProjectClients">
                    <MultiSelectWithRoles
                        label="Clients"
                        :items="clients"
                        v-model:selectedItems="projectForm.client_ids"
                        :roleOptions="clientRoleOptionsComputed"
                        roleType="client"
                        :error="errors.client_ids ? errors.client_ids[0] : ''"
                        placeholder="Select a client to add"
                        :disabled="!canManageProjectClients"
                        :readonly="!canManageProjectClients && canViewProjectClients"
                        :showRemoveButton="canManageProjectClients"
                    />
                    <div v-if="canManageProjectClients" class="mt-2 flex justify-end">
                        <PrimaryButton @click="saveClients" :disabled="clientSaving">
                            {{ clientSaving ? 'Saving...' : 'Save Clients' }}
                        </PrimaryButton>
                    </div>
                    <div v-if="clientSaveSuccess" class="mt-2 text-green-600 text-sm">
                        Clients saved successfully!
                    </div>
                    <div v-if="clientSaveError" class="mt-2 text-red-600 text-sm">
                        {{ clientSaveError }}
                    </div>
                </div>
                <div class="mb-4">
                    <InputLabel for="contract_details" value="Contract Details" />
                    <textarea id="contract_details" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="projectForm.contract_details" :disabled="!canManageProjects"></textarea>
                    <InputError :message="errors.contract_details ? errors.contract_details[0] : ''" class="mt-2" />
                </div>
                <div class="mb-4" v-if="canViewProjectUsers">
                    <MultiSelectWithRoles
                        label="Assign Users"
                        :items="users"
                        v-model:selectedItems="projectForm.user_ids"
                        :roleOptions="userRoleOptionsComputed"
                        roleType="project"
                        :defaultRoleId="2"
                        :error="errors.user_ids ? errors.user_ids[0] : ''"
                        placeholder="Select a user to add"
                        :disabled="!canManageProjectUsers"
                        :readonly="!canManageProjectUsers && canViewProjectUsers"
                        :showRemoveButton="canManageProjectUsers"
                    />
                    <div v-if="canManageProjectUsers" class="mt-2 flex justify-end">
                        <PrimaryButton @click="saveUsers" :disabled="userSaving">
                            {{ userSaving ? 'Saving...' : 'Save Users' }}
                        </PrimaryButton>
                    </div>
                    <div v-if="userSaveSuccess" class="mt-2 text-green-600 text-sm">
                        Users saved successfully!
                    </div>
                    <div v-if="userSaveError" class="mt-2 text-red-600 text-sm">
                        {{ userSaveError }}
                    </div>
                </div>
            </div>

            <!-- Tab 3: Services & Payment -->
            <div v-if="activeTab === 'services'">
                <ServicesAndPaymentForm
                    :projectId="projectForm.id"
                    :departmentOptions="departmentOptions"
                    :paymentTypeOptions="paymentTypeOptions"
                    :canManageProjectServicesAndPayments="canManageProjectServicesAndPayments"
                    :canViewProjectServicesAndPayments="canViewProjectServicesAndPayments"
                    @updated="fetchServicesAndPaymentData(projectForm.id)"
                />
            </div>

            <!-- Tab 4: Transactions -->
            <div v-if="activeTab === 'transactions' && canViewProjectTransactions">
                <ProjectTransactions
                    :projectId="projectForm.id"
                    :userProjectRole="userProjectRole.value"
                />
            </div>

            <!-- Tab 5: Documents -->
            <div v-if="activeTab === 'documents'">
                <div class="mb-4">
                    <InputLabel value="Project Documents" />
                    <div class="mt-2">
                        <!-- Only show upload section if user has upload_documents permission -->
                        <div v-if="canUploadProjectDocuments" class="mb-4">
                            <InputLabel for="documents" value="Upload Documents" />
                            <input type="file" id="documents" @change="e => {
                                const files = Array.from(e.target.files);
                                if (files.length > 0) {
                                    // Keep existing document objects and add new files
                                    const existingDocs = Array.isArray(projectForm.documents)
                                        ? projectForm.documents.filter(doc => typeof doc === 'object' && 'path' in doc)
                                        : [];
                                    projectForm.documents = [...existingDocs, ...files];
                                }
                            }" class="mt-1 block w-full" multiple accept=".pdf,.doc,.docx,.jpg,.png" />
                            <p class="text-sm text-gray-500 mt-1">Supported formats: PDF, DOC, DOCX, JPG, PNG</p>
                            <InputError :message="errors.documents ? errors.documents[0] : ''" class="mt-2" />

                            <div class="mt-4">
                                <PrimaryButton
                                    type="button"
                                    @click="uploadDocuments"
                                    :disabled="!projectForm.id || !projectForm.documents || !projectForm.documents.some(doc => typeof doc === 'object' && doc !== null && 'name' in doc && 'size' in doc && 'type' in doc)"
                                >
                                    Upload Documents
                                </PrimaryButton>
                                <p v-if="!projectForm.id" class="text-sm text-red-500 mt-2">
                                    Please save the project first before uploading documents.
                                </p>
                            </div>
                        </div>

                        <div v-if="projectForm.documents && projectForm.documents.length > 0 && typeof projectForm.documents[0] === 'object' && 'path' in projectForm.documents[0]" class="mt-4">
                            <h3 class="font-medium text-gray-700 mb-2">Existing Documents</h3>
                            <div v-for="(doc, index) in projectForm.documents" :key="index" class="flex items-center mb-2 p-2 border rounded">
                                <div class="flex-grow">
                                    <a :href="'/storage/' + doc.path" target="_blank" class="text-blue-600 hover:underline">{{ doc.filename }}</a>
                                </div>
                                <!-- Only show remove button if user has upload_documents permission -->
                                <button
                                    v-if="canUploadProjectDocuments.value"
                                    type="button"
                                    class="ml-2 text-red-600"
                                    @click="() => {
                                        projectForm.documents = projectForm.documents.filter((_, i) => i !== index);
                                    }"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="text-sm text-gray-500">
                                Documents will be uploaded to the project's Google Drive folder.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 6: Notes -->
            <div v-if="activeTab === 'notes'">
                <div class="mb-4">
                    <InputLabel value="Notes" />
                    <div class="mt-2">
                        <div v-if="projectForm.notes && projectForm.notes.length > 0">
                            <div v-for="(note, index) in projectForm.notes" :key="index" class="mb-4">
                                <div class="flex items-start mb-2">
                                    <textarea
                                        v-model="note.content"
                                        :readonly="!canAddProjectNotes"
                                        placeholder="Note Content"
                                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full h-32"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-4 bg-gray-50 rounded-md text-gray-600 text-center">
                            No notes found for this project.
                        </div>
                    </div>
                    <InputError :message="errors.notes ? errors.notes[0] : ''" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal">Close</SecondaryButton>
            </div>
        </form>
    </div>
</template>
