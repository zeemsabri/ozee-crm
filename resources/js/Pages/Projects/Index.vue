<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProjectForm from '@/Components/ProjectForm.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed, reactive } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import NotesModal from '@/Components/NotesModal.vue';
import { useAuthUser, usePermissions, useProjectRole, vPermission, registerPermissionDirective } from '@/Directives/permissions';

// Access user from permissions utility
const authUser = useAuthUser();

// Reactive state
const projects = ref([]);
const clients = ref([]);
const users = ref([]);
const projectUsers = ref([]); // Users specific to the current project
const loading = ref(true);
const errors = ref({});
const generalError = ref('');

// Modals state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showAddTransactionModal = ref(false);
const showAddNoteModal = ref(false);
const showConvertPaymentModal = ref(false);

// Form state for editing project (passed to ProjectForm)
const selectedProject = ref({});

// Form state for adding transactions
const transactionForm = reactive({
    project_id: null,
    description: '',
    amount: '',
    user_id: null,
    hours_spent: '',
    type: 'expense',
});

// Form state for adding notes
const noteForm = reactive({
    project_id: null,
    content: '',
});

// Form state for converting payment type
const convertForm = reactive({
    project_id: null,
    payment_type: 'one_off',
});

// State for project being deleted or converted
const projectToDelete = ref(null);
const projectToConvert = ref(null);

// Set up permission checking functions for global permissions
const { canDo, canView, canManage } = usePermissions();

// Using only global permissions as per requirements

// Legacy role-based checks (kept for backward compatibility)
const isSuperAdmin = computed(() => {
    if (!authUser.value) return false;
    return (authUser.value.role_data && authUser.value.role_data.slug === 'super-admin') ||
           authUser.value.role === 'super_admin' ||
           authUser.value.role === 'super-admin';
});

// Permission-based checks using the permission utilities
// Global permission checks (for actions that apply to all projects)
const canCreateProjects = canDo('create_projects');
const hasAccessToProjects = computed(() => {
    // The permission system already handles super admin permissions
    return canView('projects').value;
});

// Project-specific permission checks
const getProjectRole = (project) => {
    // Create a ref to hold the project for useProjectRole
    const projectRef = ref(project);
    return useProjectRole(projectRef);
};

// Check if user can manage a specific project - using global permissions
const canManageProject = (project) => {
    // Using global permissions as per requirements
    return canDo('manage_projects');
};

// Check if user can delete a specific project - using global permissions
const canDeleteProject = (project) => {
    // Using global permissions as per requirements
    return canDo('delete_projects');
};

// For backward compatibility with existing code
const canManageProjects = canDo('manage_projects');

// Options
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'completed', label: 'Completed' },
    { value: 'on_hold', label: 'On Hold' },
    { value: 'archived', label: 'Archived' },
];
const departmentOptions = [
    { value: 'Website Designing', label: 'Website Designing' },
    { value: 'SEO', label: 'SEO' },
    { value: 'Social Media', label: 'Social Media' },
    { value: 'Content Writing', label: 'Content Writing' },
    { value: 'Graphic Design', label: 'Graphic Design' },
];
const sourceOptions = [
    { value: 'UpWork', label: 'UpWork' },
    { value: 'Direct', label: 'Direct Client' },
    { value: 'Wix Marketplace', label: 'Wix Marketplace' },
    { value: 'Referral', label: 'Referral' },
];
// Dynamic role options fetched from API
const clientRoleOptions = ref([]);
const userRoleOptions = ref([]);

// Fetch roles from the database
const fetchRoles = async () => {
    try {
        // Fetch client roles
        const clientResponse = await axios.get('/api/roles?type=client');
        clientRoleOptions.value = clientResponse.data.map(role => ({
            value: role.id,
            label: role.name
        }));

        // Fetch project roles
        const projectResponse = await axios.get('/api/roles?type=project');
        userRoleOptions.value = projectResponse.data.map(role => ({
            value: role.id,
            label: role.name
        }));
    } catch (error) {
        console.error('Error fetching roles:', error);
        // Fallback to hardcoded roles if API fails
        clientRoleOptions.value = [
            { value: 1, label: 'Client Admin' },
            { value: 2, label: 'Client User' },
            { value: 3, label: 'Client Viewer' },
        ];
        userRoleOptions.value = [
            { value: 1, label: 'Project Manager' },
            { value: 2, label: 'Project Member' },
            { value: 3, label: 'Project Viewer' },
        ];
    }
};
const paymentTypeOptions = [
    { value: 'one_off', label: 'One-Off' },
    { value: 'monthly', label: 'Monthly' },
];

const serviceOptions = [
    { value: 'website_design', label: 'Website Design' },
    { value: 'seo', label: 'SEO' },
    { value: 'social_media', label: 'Social Media' },
    { value: 'content_writing', label: 'Content Writing' },
    { value: 'graphic_design', label: 'Graphic Design' },
];

// --- Fetch Initial Data ---
const fetchInitialData = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const projectsResponse = await window.axios.get('/api/projects');
        projects.value = projectsResponse.data;

        if (canManageProjects.value) {
            const clientsResponse = await window.axios.get('/api/clients');

            clients.value = clientsResponse.data.data;
            const usersResponse = await window.axios.get('/api/users');
            users.value = usersResponse.data;
        }
    } catch (error) {
        generalError.value = 'Failed to load data.';
        console.error('Error fetching initial data:', error);
        if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            generalError.value = 'You are not authorized to view this content or your session expired. Please log in.';
        }
    } finally {
        loading.value = false;
    }
};

// --- Create Project ---
const openCreateModal = () => {
    // Check if user has permission to create projects
    if (!canDo('create_projects').value) {
        alert('You do not have permission to create projects.');
        return;
    }

    selectedProject.value = {};
    showCreateModal.value = true;
};

// --- Edit Project ---
const openEditModal = (project) => {
    // Check if user has permission to manage projects (using global permissions)
    if (!canDo('manage_projects').value) {
        alert('You do not have permission to edit this project.');
        return;
    }

    selectedProject.value = project;
    showEditModal.value = true;
};

// --- Handle Project Submission ---
const handleProjectSubmit = (project) => {
    const index = projects.value.findIndex(p => p.id === project.id);
    if (index !== -1) {
        projects.value[index] = project;
    } else {
        projects.value.push(project);
    }
    showCreateModal.value = false;
    showEditModal.value = false;
    alert(project.id ? 'Project updated successfully!' : 'Project created successfully!');
    fetchInitialData();
};

// --- Delete Project ---
const confirmProjectDeletion = (project) => {
    // Check if user has permission to delete projects (using global permissions)
    if (!canDo('delete_projects').value) {
        alert('You do not have permission to delete this project.');
        return;
    }

    projectToDelete.value = project;
    showDeleteModal.value = true;
};

const deleteProject = async () => {
    generalError.value = '';

    // Check if project is valid
    const projectId = projectToDelete.value?.id;
    if (!projectId) {
        generalError.value = 'Invalid project.';
        return;
    }

    // Check if user has permission to delete projects (using global permissions)
    if (!canDo('delete_projects').value) {
        generalError.value = 'You do not have permission to delete this project.';
        return;
    }

    try {
        await window.axios.delete(`/api/projects/${projectId}`);
        projects.value = projects.value.filter(p => p.id !== projectId);
        showDeleteModal.value = false;
        projectToDelete.value = null;
        alert('Project deleted successfully!');
    } catch (error) {
        generalError.value = 'Failed to delete project.';
        if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        }
        console.error('Error deleting project:', error);
    }
};

// --- Add Transaction ---
const openAddTransactionModal = async (project) => {
    // Check if user has permission to manage transactions (using global permissions)
    if (!canDo('manage_project_transactions').value) {
        alert('You do not have permission to add transactions to this project.');
        return;
    }

    // Check if project is valid
    const projectId = project.id;
    if (!projectId) {
        console.error('No project ID found in project object:', project);
        return;
    }

    transactionForm.project_id = projectId;
    transactionForm.description = '';
    transactionForm.amount = '';
    transactionForm.user_id = null;
    transactionForm.hours_spent = '';
    transactionForm.type = 'expense'; // Default to expense type
    errors.value = {};
    generalError.value = '';

    // Fetch project data to get users assigned to this project
    try {
        const response = await window.axios.get(`/api/projects/${projectId}`);
        projectUsers.value = response.data.users || [];
    } catch (error) {
        console.error('Error fetching project users:', error);
        projectUsers.value = [];
    }

    showAddTransactionModal.value = true;
};

const addTransaction = async () => {
    errors.value = {};
    generalError.value = '';

    // Check if project is valid
    const projectId = transactionForm.project_id;
    if (!projectId) {
        generalError.value = 'Invalid project.';
        return;
    }

    // Check if user has permission to manage transactions (using global permissions)
    if (!canDo('manage_project_transactions').value) {
        generalError.value = 'You do not have permission to add transactions to this project.';
        return;
    }

    try {
        await window.axios.post(`/api/projects/${projectId}/expenses`, { expenses: [transactionForm] });
        showAddTransactionModal.value = false;
        alert('Transaction added successfully!');
        fetchInitialData();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to add transaction.';
            console.error('Error adding transaction:', error);
        }
    }
};

// --- Add Note ---
const openAddNoteModal = (project) => {
    // Check if user has permission to add notes (using global permissions)
    if (!canDo('add_project_notes').value) {
        alert('You do not have permission to add notes to this project.');
        return;
    }

    // Check if project is valid
    const projectId = project.id;
    if (!projectId) {
        console.error('No project ID found in project object:', project);
        return;
    }

    noteForm.project_id = projectId;
    noteForm.content = '';
    errors.value = {};
    generalError.value = '';
    showAddNoteModal.value = true;
};

const addNote = async () => {
    errors.value = {};
    generalError.value = '';

    // Check if project is valid
    const projectId = noteForm.project_id;
    if (!projectId) {
        generalError.value = 'Invalid project.';
        return;
    }

    // Check if user has permission to add notes (using global permissions)
    if (!canDo('add_project_notes').value) {
        generalError.value = 'You do not have permission to add notes to this project.';
        return;
    }

    try {
        await window.axios.post(`/api/projects/${projectId}/notes`, { notes: [{ content: noteForm.content }] });
        showAddNoteModal.value = false;
        alert('Note added successfully!');
        fetchInitialData();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to add note.';
            console.error('Error adding note:', error);
        }
    }
};

// --- Convert Payment Type ---
const openConvertPaymentModal = (project) => {
    // Check if user has permission to manage services and payments (using global permissions)
    if (!canDo('manage_project_services_and_payments').value) {
        alert('You do not have permission to convert payment type for this project.');
        return;
    }

    // Check if project is valid
    const projectId = project.id;
    if (!projectId) {
        console.error('No project ID found in project object:', project);
        return;
    }

    projectToConvert.value = project;
    convertForm.project_id = projectId;
    convertForm.payment_type = project.payment_type;
    errors.value = {};
    generalError.value = '';
    showConvertPaymentModal.value = true;
};

const convertPaymentType = async () => {
    generalError.value = '';

    // Check if project is valid
    const projectId = convertForm.project_id;
    if (!projectId) {
        generalError.value = 'Invalid project.';
        return;
    }

    // Check if user has permission to manage services and payments (using global permissions)
    if (!canDo('manage_project_services_and_payments').value) {
        generalError.value = 'You do not have permission to convert payment type for this project.';
        return;
    }

    try {
        await window.axios.post(`/api/projects/${projectId}/convert-payment-type`, { payment_type: convertForm.payment_type });
        const index = projects.value.findIndex(p => p.id === projectId);
        if (index !== -1) {
            projects.value[index].payment_type = convertForm.payment_type;
        }
        showConvertPaymentModal.value = false;
        projectToConvert.value = null;
        alert('Payment type converted successfully!');
        fetchInitialData();
    } catch (error) {
        generalError.value = 'Failed to convert payment type.';
        if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        }
        console.error('Error converting payment type:', error);
    }
};

// --- Lifecycle Hook ---
onMounted(() => {
    // Check if user has access to projects page
    if (!hasAccessToProjects.value) {
        // Redirect to dashboard if user doesn't have access
        window.location.href = route('dashboard');
        return;
    }

    fetchInitialData();
    fetchRoles(); // Fetch dynamic role options

    // Register the v-permission directive
    const app = document.querySelector('#app').__vue_app__;
    if (app) {
        registerPermissionDirective(app);
    }
});
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Projects</h2>
        </template>

        <div class="py-12">
            <div class="max-w-12xl mx-auto sm:px-6 lg:px-12">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-2xl font-bold mb-4">Project List</h3>

                        <div v-permission="'create_projects'" class="mb-6">
                            <PrimaryButton @click="openCreateModal">
                                Create New Project
                            </PrimaryButton>
                        </div>

                        <div v-if="loading" class="text-gray-600">Loading projects...</div>
                        <div v-else-if="generalError" class="text-red-600">{{ generalError }}</div>
                        <div v-else-if="projects.length === 0" class="text-gray-600">No projects found.</div>
                        <div v-else>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clients</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Users</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="project in projects" :key="project.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ project.name }}</td>
                                    <td class="px-6 py-4">
                                        <span v-if="project.clients && project.clients.length">
                                            {{ project.clients.map(client => {
                                                const roleId = client.pivot.role_id;
                                                const roleOption = clientRoleOptions.find(option => option.value === roleId);
                                                const roleName = roleOption ? roleOption.label : 'Unknown Role';
                                                return `${client.name} (${roleName})`;
                                            }).join(', ') }}
                                        </span>
                                        <span v-else class="text-gray-400">None</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap capitalize">{{ project.status.replace('_', ' ') }}</td>
                                    <td class="px-6 py-4">
                                        <span v-if="project.users && project.users.length">
                                            {{ project.users.map(user => {
                                                const roleId = user.pivot.role_id;
                                                const roleOption = userRoleOptions.find(option => option.value === roleId);
                                                const roleName = roleOption ? roleOption.label : 'Unknown Role';
                                                return `${user.name} (${roleName})`;
                                            }).join(', ') }}
                                        </span>
                                        <span v-else class="text-gray-400">None</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <PrimaryButton v-permission="'manage_projects'" @click="openEditModal(project)">Edit</PrimaryButton>
                                            <PrimaryButton v-permission="'manage_project_transactions'" @click="openAddTransactionModal(project)">Add Transactions</PrimaryButton>
                                            <PrimaryButton v-permission="'add_project_notes'" @click="openAddNoteModal(project)">Add Note</PrimaryButton>
<!--                                            <PrimaryButton v-permission="'manage_project_services_and_payments'" @click="openConvertPaymentModal(project)">Convert Payment</PrimaryButton>-->
                                            <DangerButton v-permission="'delete_projects'" @click="confirmProjectDeletion(project)">Delete</DangerButton>
                                        </div>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <ProjectForm
                :show="showCreateModal"
                :project="selectedProject"
                :statusOptions="statusOptions"
                :serviceOptions="serviceOptions"
                :departmentOptions="departmentOptions"
                :sourceOptions="sourceOptions"
                :clientRoleOptions="clientRoleOptions"
                :userRoleOptions="userRoleOptions"
                :paymentTypeOptions="paymentTypeOptions"
                @close="showCreateModal = false"
                @submit="handleProjectSubmit"
            />
        </Modal>

        <Modal :show="showEditModal" @close="showEditModal = false">
            <ProjectForm
                :show="showEditModal"
                :project="selectedProject"
                :statusOptions="statusOptions"
                :serviceOptions="serviceOptions"
                :departmentOptions="departmentOptions"
                :sourceOptions="sourceOptions"
                :clientRoleOptions="clientRoleOptions"
                :userRoleOptions="userRoleOptions"
                :paymentTypeOptions="paymentTypeOptions"
                @close="showEditModal = false"
                @submit="handleProjectSubmit"
            />
        </Modal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this project?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    This action cannot be undone. All associated conversations and emails will also be deleted.
                </p>
                <div v-if="projectToDelete" class="mt-4 text-gray-800">
                    <strong>Project:</strong> {{ projectToDelete.name }}
                    <span v-if="projectToDelete.clients && projectToDelete.clients.length">
                        (Clients: {{ projectToDelete.clients.map(client => {
                            const roleId = client.pivot.role_id;
                            const roleOption = clientRoleOptions.find(option => option.value === roleId);
                            const roleName = roleOption ? roleOption.label : 'Unknown Role';
                            return `${client.name} (${roleName})`;
                        }).join(', ') }})
                    </span>
                </div>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteProject">Delete Project</DangerButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showAddTransactionModal" @close="showAddTransactionModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Add Transactions</h2>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <form @submit.prevent="addTransaction">
                    <div class="mb-4">
                        <InputLabel for="transaction_type" value="Type" />
                        <select id="transaction_type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="transactionForm.type">
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                        <InputError :message="errors['expenses.0.type'] ? errors['expenses.0.type'][0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="transaction_description" value="Description" />
                        <TextInput id="transaction_description" type="text" class="mt-1 block w-full" v-model="transactionForm.description" required />
                        <InputError :message="errors['expenses.0.description'] ? errors['expenses.0.description'][0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="transaction_amount" value="Amount" />
                        <TextInput id="transaction_amount" type="number" step="0.01" class="mt-1 block w-full" v-model="transactionForm.amount" required />
                        <InputError :message="errors['expenses.0.amount'] ? errors['expenses.0.amount'][0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4" v-if="transactionForm.type === 'expense'">
                        <InputLabel for="transaction_user_id" value="User (Optional)" />
                        <select id="transaction_user_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="transactionForm.user_id">
                            <option value="" disabled>Select User</option>
                            <option v-for="user in projectUsers" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                        <InputError :message="errors['expenses.0.user_id'] ? errors['expenses.0.user_id'][0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4" v-if="transactionForm.type === 'expense'">
                        <InputLabel for="transaction_hours_spent" value="Hours Spent (Optional)" />
                        <TextInput id="transaction_hours_spent" type="number" step="0.01" class="mt-1 block w-full" v-model="transactionForm.hours_spent" />
                        <InputError :message="errors['expenses.0.hours_spent'] ? errors['expenses.0.hours_spent'][0] : ''" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showAddTransactionModal = false">Cancel</SecondaryButton>
                        <PrimaryButton class="ms-3" type="submit">Add Transaction</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <NotesModal
            :show="showAddNoteModal"
            :project-id="noteForm.project_id"
            @close="showAddNoteModal = false"
            @note-added="fetchInitialData"
        />

        <Modal :show="showConvertPaymentModal" @close="showConvertPaymentModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Convert Payment Type</h2>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <form @submit.prevent="convertPaymentType">
                    <div class="mb-4">
                        <InputLabel for="convert_payment_type" value="Payment Type" />
                        <select id="convert_payment_type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="convertForm.payment_type" required>
                            <option v-for="option in paymentTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <InputError :message="errors.payment_type ? errors.payment_type[0] : ''" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showConvertPaymentModal = false">Cancel</SecondaryButton>
                        <PrimaryButton class="ms-3" type="submit">Convert</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
