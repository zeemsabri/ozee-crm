<script setup>
import { ref, reactive, watch, computed, onMounted } from 'vue';
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
const canViewProjectTransactions = canView('view_project_transactions', userProjectRole);
// Tab management
const activeTab = ref('basic');

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
                if (canManageProjectExpenses.value || canManageProjectIncome.value) {
                    fetchTransactionsData(projectForm.id).finally(() => {
                        loading.value = false;
                    });
                } else {
                    loading.value = false;
                }
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
        // If we have a project ID, use the project-specific endpoint
        if (projectId.value) {
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
        // If we have a project ID, use the project-specific endpoint
        if (projectId.value) {
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

// Function to fetch transactions data
const fetchTransactionsData = async (projectId) => {
    try {
        const response = await window.axios.get(`/api/projects/${projectId}/sections/transactions`);
        const transactions = response.data;

        // Update transactions
        projectForm.transactions = transactions.map(transaction => ({
            description: transaction.description,
            amount: transaction.amount,
            user_id: transaction.user_id,
            hours_spent: transaction.hours_spent,
            type: transaction.type || 'expense',
        }));

        return transactions;
    } catch (error) {
        console.error('Error fetching transactions data:', error);
        generalError.value = 'Failed to fetch transactions data.';
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
            case 'transactions':
                if (canManageProjectExpenses.value || canManageProjectIncome.value) {
                    await fetchTransactionsData(projectId);
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

// These arrays are no longer needed with the MultiSelectWithRoles component
// const selectedClientIds = ref([]);
// const selectedUserIds = ref([]);


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
    services: [], // Array of service IDs (renamed from departments)
    service_details: [], // Array of objects with service_id, amount, frequency, start_date, and payment_breakdown
    source: '',
    total_amount: '',
    contract_details: '',
    google_drive_link: '',
    payment_type: 'one_off',
    user_ids: [],
    transactions: [], // Renamed from expenses
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

    projectForm.transactions = newProject.transactions ? newProject.transactions.map(transaction => ({
        description: transaction.description,
        amount: transaction.amount,
        user_id: transaction.user_id,
        hours_spent: transaction.hours_spent,
        type: transaction.type || 'expense',
    })) : [];
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
        alert('Basic information updated successfully!');
    } catch (error) {
        handleError(error, 'Failed to update basic information.');
    }
};

// Function to update services and payment
const updateServicesAndPayment = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        // Create a clean copy of the form data with only services and payment information
        const formData = {
            services: projectForm.services,
            service_details: projectForm.service_details,
            total_amount: projectForm.total_amount,
            payment_type: projectForm.payment_type,
        };

        // Update the project
        const response = await window.axios.put(`/api/projects/${projectForm.id}/sections/services-payment`, formData);

        // Show success message
        alert('Services and payment information updated successfully!');
    } catch (error) {
        handleError(error, 'Failed to update services and payment information.');
    }
};

// Function to update transactions
const updateTransactions = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        // Create a clean copy of the form data with only transactions
        const formData = {
            transactions: projectForm.transactions,
        };

        // Filter transactions based on permissions
        if (!canManageProjectExpenses.value) {
            formData.transactions = formData.transactions.filter(t => t.type !== 'expense');
        }
        if (!canManageProjectIncome.value) {
            formData.transactions = formData.transactions.filter(t => t.type !== 'income');
        }

        // Update the project
        const response = await window.axios.put(`/api/projects/${projectForm.id}/sections/transactions`, formData);

        // Show success message
        alert('Transactions updated successfully!');
    } catch (error) {
        handleError(error, 'Failed to update transactions.');
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
        alert('Notes updated successfully!');
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
                await updateServicesAndPayment();
                break;
            case 'transactions':
                await updateTransactions();
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

const handleServiceSelection = (serviceId, isSelected) => {
    if (isSelected) {
        // Add service to service_details if it doesn't exist
        if (!projectForm.service_details.some(detail => detail.service_id === serviceId)) {
            projectForm.service_details.push({
                service_id: serviceId,
                amount: '',
                frequency: 'one_off',
                start_date: '',
                payment_breakdown: {
                    first: 30,
                    second: 30,
                    third: 40
                }
            });
        }
    } else {
        // Remove service from service_details
        projectForm.service_details = projectForm.service_details.filter(
            detail => detail.service_id !== serviceId
        );
    }
};

const getServiceDetail = (serviceId) => {
    // Find existing detail or create a new one
    let detail = projectForm.service_details.find(detail => detail.service_id === serviceId);
    if (!detail) {
        detail = {
            service_id: serviceId,
            amount: '',
            frequency: 'one_off',
            start_date: '',
            payment_breakdown: {
                first: 30,
                second: 30,
                third: 40
            }
        };
        projectForm.service_details.push(detail);
    }
    return detail;
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
    console.log('saveUsers called, projectForm.user_ids:', projectForm.user_ids);

    if (!projectForm.id) {
        userSaveError.value = 'Please save the project first before saving users.';
        return;
    }

    if (!projectForm.user_ids || projectForm.user_ids.length === 0) {
        console.log('No users selected, projectForm.user_ids is empty or null');
        userSaveError.value = 'Please select at least one user to save.';
        return;
    }

    userSaving.value = true;
    userSaveSuccess.value = false;
    userSaveError.value = '';

    try {
        console.log('Sending user_ids to API:', JSON.stringify(projectForm.user_ids));

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
        // We assume the server response includes all documents associated with the project
        projectForm.documents = response.data.documents || [];

        // Clear the file input to allow selecting the same files again if needed
        document.getElementById('documents').value = '';

        // Show success message
        alert('Documents uploaded successfully!');

        // Don't mark the form as modified since document uploads are separate
        // This ensures document uploads don't affect the rest of the project form
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
<!--            <button-->
<!--                @click="showDebugInfo = !showDebugInfo"-->
<!--                class="text-xs text-gray-500 flex items-center"-->
<!--                type="button"-->
<!--            >-->
<!--                <span v-if="showDebugInfo">▼</span>-->
<!--                <span v-else>▶</span>-->
<!--                <span class="ml-1">Permission Debug Info</span>-->
<!--            </button>-->

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
                    Clients and Users
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
<!--                <div class="mb-4">-->
<!--                    <InputLabel for="social_media_link" value="Social Media Link" />-->
<!--                    <TextInput id="social_media_link" type="url" class="mt-1 block w-full" v-model="projectForm.social_media_link" :disabled="!canManageProjects" />-->
<!--                    <InputError :message="errors.social_media_link ? errors.social_media_link[0] : ''" class="mt-2" />-->
<!--                </div>-->
                <div class="mb-4">
                    <InputLabel for="preferred_keywords" value="Client Preferred Keywords" />
                    <TextInput id="preferred_keywords" type="text" class="mt-1 block w-full" v-model="projectForm.preferred_keywords" :disabled="!canManageProjects" />
                    <InputError :message="errors.preferred_keywords ? errors.preferred_keywords[0] : ''" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="status" value="Status" />
                    <select id="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="projectForm.status" required :disabled="!canManageProjects">
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="errors.status ? errors.status[0] : ''" class="mt-2" />
                </div>
<!--                <div class="mb-4">-->
<!--                    <InputLabel for="project_type" value="Project Type" />-->
<!--                    <TextInput id="project_type" type="text" class="mt-1 block w-full" v-model="projectForm.project_type" :disabled="!canManageProjects" />-->
<!--                    <InputError :message="errors.project_type ? errors.project_type[0] : ''" class="mt-2" />-->
<!--                </div>-->
                <div class="mb-4">
                    <InputLabel for="source" value="Source" />
                    <select id="source" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="projectForm.source" :disabled="!canManageProjects">
                        <option value="" disabled>Select a Source</option>
                        <option v-for="option in sourceOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="errors.source ? errors.source[0] : ''" class="mt-2" />
                </div>
<!--                <div class="mb-4">-->
<!--                    <InputLabel for="google_drive_link" value="Google Drive Link" />-->
<!--                    <TextInput id="google_drive_link" type="url" class="mt-1 block w-full" v-model="projectForm.google_drive_link" :disabled="!canManageProjects" />-->
<!--                    <InputError :message="errors.google_drive_link ? errors.google_drive_link[0] : ''" class="mt-2" />-->
<!--                </div>-->

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

            <!-- Client and User Selection Tab -->
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
                <div class="mb-4">
                    <InputLabel for="total_amount" value="Total Amount" />
                    <TextInput id="total_amount" type="number" step="0.01" class="mt-1 block w-full" v-model="projectForm.total_amount" :disabled="!canManageProjectServicesAndPayments" />
                    <InputError :message="errors.total_amount ? errors.total_amount[0] : ''" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="payment_type" value="Payment Type" />
                    <select id="payment_type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="projectForm.payment_type" required :disabled="!canManageProjectServicesAndPayments">
                        <option v-for="option in paymentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <InputError :message="errors.payment_type ? errors.payment_type[0] : ''" class="mt-2" />
                </div>
                <div class="mb-4">
                    <InputLabel for="services" value="Services" />
                    <div class="mt-2">
                        <div v-for="option in departmentOptions" :key="option.value" class="border p-3 mb-3 rounded">
                            <div class="flex items-center mb-2">
                                <Checkbox
                                    :id="`service_${option.value}`"
                                    :value="option.value"
                                    v-model:checked="projectForm.services"
                                    @update:checked="value => handleServiceSelection(option.value, value)"
                                    :disabled="!canManageProjectServicesAndPayments"
                                />
                                <label :for="`service_${option.value}`" class="ms-2 text-sm font-medium text-gray-700">{{ option.label }}</label>
                            </div>

                            <div v-if="(projectForm.services || []).includes(option.value)" class="pl-6">
                                <div class="grid grid-cols-2 gap-4 mb-2">
                                    <div>
                                        <InputLabel :for="`service_amount_${option.value}`" value="Amount" class="text-xs" />
                                        <TextInput
                                            :id="`service_amount_${option.value}`"
                                            type="number"
                                            step="0.01"
                                            placeholder="Amount"
                                            class="w-full"
                                            v-model="getServiceDetail(option.value).amount"
                                            :disabled="!canManageProjectServicesAndPayments"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel :for="`service_frequency_${option.value}`" value="Frequency" class="text-xs" />
                                        <select
                                            :id="`service_frequency_${option.value}`"
                                            class="border-gray-300 rounded-md w-full"
                                            v-model="getServiceDetail(option.value).frequency"
                                            :disabled="!canManageProjectServicesAndPayments"
                                        >
                                            <option value="monthly">Monthly</option>
                                            <option value="one_off">One off</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <InputLabel :for="`service_start_date_${option.value}`" value="Start Date" class="text-xs" />
                                    <TextInput
                                        :id="`service_start_date_${option.value}`"
                                        type="date"
                                        class="w-full"
                                        v-model="getServiceDetail(option.value).start_date"
                                        :disabled="!canManageProjectServicesAndPayments"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Payment Breakdown (%)" class="text-xs mb-1" />
                                    <div class="grid grid-cols-3 gap-2">
                                        <div>
                                            <InputLabel :for="`service_payment_first_${option.value}`" value="First" class="text-xs" />
                                            <TextInput
                                                :id="`service_payment_first_${option.value}`"
                                                type="text"
                                                min="0"
                                                max="100"
                                                class="w-full"
                                                :value="getServiceDetail(option.value).payment_breakdown?.first || 0"
                                                @input="e => {
                                                    if (canManageProjectServicesAndPayments) {
                                                        const detail = getServiceDetail(option.value);
                                                        if (!detail.payment_breakdown) detail.payment_breakdown = {};
                                                        detail.payment_breakdown.first = e.target.value;
                                                    }
                                                }"
                                                :disabled="!canManageProjectServicesAndPayments"
                                            />
                                        </div>
                                        <div>
                                            <InputLabel :for="`service_payment_second_${option.value}`" value="Second" class="text-xs" />
                                            <TextInput
                                                :id="`service_payment_second_${option.value}`"
                                                type="text"
                                                min="0"
                                                max="100"
                                                class="w-full"
                                                :value="getServiceDetail(option.value).payment_breakdown?.second || 0"
                                                @input="e => {
                                                    if (canManageProjectServicesAndPayments) {
                                                        const detail = getServiceDetail(option.value);
                                                        if (!detail.payment_breakdown) detail.payment_breakdown = {};
                                                        detail.payment_breakdown.second = e.target.value;
                                                    }
                                                }"
                                                :disabled="!canManageProjectServicesAndPayments"
                                            />
                                        </div>
                                        <div>
                                            <InputLabel :for="`service_payment_third_${option.value}`" value="Third" class="text-xs" />
                                            <TextInput
                                                :id="`service_payment_third_${option.value}`"
                                                type="text"
                                                min="0"
                                                max="100"
                                                class="w-full"
                                                :value="getServiceDetail(option.value).payment_breakdown?.third || 0"
                                                @input="e => {
                                                    if (canManageProjectServicesAndPayments) {
                                                        const detail = getServiceDetail(option.value);
                                                        if (!detail.payment_breakdown) detail.payment_breakdown = {};
                                                        detail.payment_breakdown.third = e.target.value;
                                                    }
                                                }"
                                                :disabled="!canManageProjectServicesAndPayments"
                                            />
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Total: {{
                                            parseInt(getServiceDetail(option.value).payment_breakdown?.first || 0) +
                                            parseInt(getServiceDetail(option.value).payment_breakdown?.second || 0) +
                                            parseInt(getServiceDetail(option.value).payment_breakdown?.third || 0)
                                        }}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <InputError :message="errors.services ? errors.services[0] : ''" class="mt-2" />
                </div>

                <div v-if="canManageProjectServicesAndPayments" class="mt-6 flex justify-end">
                    <PrimaryButton
                        @click="updateServicesAndPayment"
                        :disabled="!projectForm.id || !canManageProjectServicesAndPayments"
                    >
                        Update Services & Payment
                    </PrimaryButton>
                </div>
            </div>

            <!-- Tab 4: Transactions -->
            <div v-if="activeTab === 'transactions' && canViewProjectTransactions">
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium">Transactions</h3>
                        <div class="text-right">
                            <div class="text-lg font-medium">
                                Total Income: ${{
                                    projectForm.transactions
                                        .filter(t => t.type === 'income')
                                        .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0)
                                        .toFixed(2)
                                }}
                            </div>
                            <div class="text-lg font-medium">
                                Total Expenses: ${{
                                    projectForm.transactions
                                        .filter(t => t.type === 'expense')
                                        .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0)
                                        .toFixed(2)
                                }}
                            </div>
                            <div class="text-xl font-bold mt-1">
                                Net: ${{
                                    (
                                        projectForm.transactions
                                            .filter(t => t.type === 'income')
                                            .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0) -
                                        projectForm.transactions
                                            .filter(t => t.type === 'expense')
                                            .reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0)
                                    ).toFixed(2)
                                }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4" v-if="canViewProjectTransactions">
                        <div v-for="(transaction, index) in projectForm.transactions" :key="index" class="flex items-center mb-2 p-2 border rounded">
                            <select v-model="transaction.type" class="mr-2 border-gray-300 rounded-md">
                                <option  v-if="canManageProjectIncome"  value="income">Income</option>
                                <option v-if="canManageProjectExpenses" value="expense">Expense</option>
                            </select>
                            <TextInput v-model="transaction.description" placeholder="Description" class="mr-2 flex-grow" />
                            <TextInput v-model.number="transaction.amount" type="number" step="0.01" placeholder="Amount" class="mr-2 w-24" />
                            <select v-if="transaction.type === 'expense'" v-model="transaction.user_id" class="mr-2 border-gray-300 rounded-md">
                                <option value="" disabled>Select User (Optional)</option>
                                <option v-for="user in (Array.isArray(users) ? users.filter(u => {
                                            // Check if user is in projectForm.user_ids
                                            return projectForm.user_ids.some(selectedUser => selectedUser.id === u.id);
                                        }) : [])"
                                        :key="user.id"
                                        :value="user.id">{{ user.name }}</option>
                            </select>
                            <TextInput v-if="transaction.type === 'expense'" v-model.number="transaction.hours_spent" type="number" step="0.01" placeholder="Hours" class="mr-2 w-20" />
                            <button type="button" class="text-red-600" @click="projectForm.transactions.splice(index, 1)">
                                Remove
                            </button>
                        </div>
                        <div class="flex mt-2">
                            <SecondaryButton @click="projectForm.transactions.push({ description: '', amount: '', user_id: null, hours_spent: '', type: 'expense' })">
                                Add Transaction
                            </SecondaryButton>
                        </div>
                    </div>
                    <InputError :message="errors.transactions ? errors.transactions[0] : ''" class="mt-2" />
                </div>

                <div v-if="canManageProjectExpenses || canManageProjectIncome" class="mt-6 flex justify-end">
                    <PrimaryButton
                        @click="updateTransactions"
                        :disabled="!projectForm.id || (!canManageProjectExpenses && !canManageProjectIncome)"
                    >
                        Update Transactions
                    </PrimaryButton>
                </div>
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
                                    console.log('Files selected:', files);
                                    console.log('Are files File objects?', files.some(f => typeof f === 'object' && f !== null && 'name' in f && 'size' in f && 'type' in f));
                                    console.log('Updated documents array:', projectForm.documents);
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
                        <div v-for="(note, index) in projectForm.notes" :key="index" class="mb-4">
                            <div class="flex items-start mb-2">
                                <textarea
                                    v-model="note.content"
                                    :readonly="!canAddProjectNotes"
                                    placeholder="Note Content"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full h-32"
                                ></textarea>
                            </div>
<!--                            <div class="flex justify-end">-->
<!--                                <SecondaryButton  v-if="canAddProjectNotes"  @click="projectForm.notes.splice(index, 1)">Remove Note</SecondaryButton>-->
<!--                            </div>-->
                        </div>
<!--                        <SecondaryButton  v-if="canAddProjectNotes"  @click="projectForm.notes.push({ content: '' })">Add Note</SecondaryButton>-->
                    </div>
                    <InputError :message="errors.notes ? errors.notes[0] : ''" class="mt-2" />
                </div>

<!--                <div v-if="canAddProjectNotes" class="mt-6 flex justify-end">
                    <PrimaryButton
                        @click="updateNotes"
                        :disabled="!projectForm.id || !canAddProjectNotes"
                    >
                        Update Notes
                    </PrimaryButton>
                </div>-->
            </div>

            <div class="mt-6 flex justify-end">
                <SecondaryButton @click="closeModal">Close</SecondaryButton>
            </div>
        </form>
    </div>
</template>
