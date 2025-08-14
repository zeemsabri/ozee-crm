<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, computed, reactive } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { usePermissions } from "@/Directives/permissions.js";

// Import HeroIcons
import {
    UsersIcon,
    BriefcaseIcon,
    MagnifyingGlassIcon,
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    ArchiveBoxIcon,
    ArrowUturnUpIcon
} from '@heroicons/vue/24/outline';

// Access authenticated user
const authUser = computed(() => usePage().props.auth.user);
const { canDo, canView, canManage } = usePermissions();

// Reactive state
const users = ref([]);
const loading = ref(true);
const errors = ref({});
const generalError = ref('');

// Search, filter and status state
const searchQuery = ref('');
const selectedRole = ref('');
const selectedProject = ref('');
const selectedStatus = ref('active'); // active | archived | all

// Modals state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);

// Form state for creating/editing
const userForm = reactive({
    id: null,
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'contractor', // String role for backward compatibility
    role_id: 4, // Default role_id for contractor - will be updated when roles are fetched
    user_type: 'contractor', // Default user type
    timezone: '',
});

// State for user being deleted
const userToDelete = ref(null);

// Role options for dropdown
const roleOptions = ref([]);

// All unique projects for the filter dropdown
const projectOptions = computed(() => {
    const allProjects = users.value.flatMap(user => user.projects || []);
    const uniqueProjects = [...new Set(allProjects.map(p => p.id))];
    return uniqueProjects.map(id => {
        const project = allProjects.find(p => p.id === id);
        return { value: project.id, label: project.name };
    });
});

// --- COMPUTED PROPERTIES FOR STATS ---
const totalUsers = computed(() => users.value.length);
const totalManagers = computed(() => users.value.filter(user =>
    user.role_data?.slug === 'manager' ||
    user.role === 'manager' ||
    user.role === 'manager-role' ||
    user.role === 'manager_role'
).length);
const totalEmployees = computed(() => users.value.filter(user =>
    user.role_data?.slug === 'employee' ||
    user.role === 'employee'
).length);
const totalContractors = computed(() => users.value.filter(user =>
    user.role_data?.slug === 'contractor' ||
    user.role === 'contractor'
).length);

// Filtered and searched users
const filteredUsers = computed(() => {
    let filtered = users.value;

    // Filter by role
    if (selectedRole.value) {
        filtered = filtered.filter(user => {
            const userRoleId = user.role_data?.id || (roleOptions.value.find(r => r.slug === user.role)?.value);
            return userRoleId === selectedRole.value;
        });
    }

    // Filter by project
    if (selectedProject.value) {
        filtered = filtered.filter(user =>
            user.projects && user.projects.some(p => p.id === selectedProject.value)
        );
    }

    // Filter by search query
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(user =>
            user.name.toLowerCase().includes(query) ||
            user.email.toLowerCase().includes(query)
        );
    }

    return filtered;
});


// --- Fetch Roles ---
const fetchRoles = async () => {
    try {
        const response = await window.axios.get('/api/roles?type=application');
        roleOptions.value = response.data.map(role => ({
            value: role.id,
            slug: role.slug.replace(/-/g, '_'),
            label: role.name
        }));
    } catch (error) {
        console.error('Error fetching roles:', error);
        roleOptions.value = [
            { value: 1, slug: 'super_admin', label: 'Super Admin' },
            { value: 2, slug: 'manager', label: 'Manager' },
            { value: 3, slug: 'employee', label: 'Employee' },
            { value: 4, slug: 'contractor', label: 'Contractor' },
        ];
    }
};

// Permission checks
const isSuperAdmin = computed(() => {
    if (!authUser.value) return false;
    return authUser.value.role_data?.slug === 'super-admin' ||
        authUser.value.role === 'super_admin' ||
        authUser.value.role === 'super-admin';
});
const isManager = computed(() => {
    if (!authUser.value) return false;
    return authUser.value.role_data?.slug === 'manager' ||
        authUser.value.role === 'manager' ||
        authUser.value.role === 'manager-role' ||
        authUser.value.role === 'manager_role';
});

const canDeleteUsers = canDo('delete_users');
const canCreateUsers = canDo('create_users');

// --- Fetch Users ---
const fetchUsers = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const params = {};
        if (selectedStatus.value === 'all') {
            params.with_trashed = 1;
        } else if (selectedStatus.value === 'archived') {
            params.only_trashed = 1;
        }
        const response = await window.axios.get('/api/users', { params });
        users.value = response.data;
    } catch (error) {
        generalError.value = 'Failed to fetch users.';
        console.error('Error fetching users:', error);
        if (error.response && error.response.status === 403) {
            generalError.value = 'You do not have permission to view users.';
        } else if (error.response && error.response.status === 401) {
            generalError.value = 'Session expired. Please log in again.';
            localStorage.removeItem('authToken');
            localStorage.removeItem('userRole');
            window.location.href = '/login';
        }
    } finally {
        loading.value = false;
    }
};

// --- Create User ---
const openCreateModal = () => {
    userForm.id = null;
    userForm.name = '';
    userForm.email = '';
    userForm.password = '';
    userForm.password_confirmation = '';
    const contractorRole = roleOptions.value.find(role => role.slug === 'contractor');
    userForm.role_id = contractorRole?.value ||
        (roleOptions.value.length > 0 ? roleOptions.value[0].value : 4);
    userForm.role = contractorRole?.slug || 'contractor';
    userForm.user_type = 'contractor';
    try {
        userForm.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (e) {
        userForm.timezone = '';
    }
    errors.value = {};
    generalError.value = '';
    showCreateModal.value = true;
};

const createUser = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        const response = await window.axios.post('/api/users', userForm);
        users.value.push(response.data);
        showCreateModal.value = false;
        // Use a custom message box instead of alert()
        console.log('User created successfully!');
        fetchUsers();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to create user.';
            console.error('Error creating user:', error);
        }
    }
};

// --- Edit User ---
const openEditModal = (userToEdit) => {
    userForm.id = userToEdit.id;
    userForm.name = userToEdit.name;
    userForm.email = userToEdit.email;
    userForm.timezone = userToEdit.timezone || '';

    if (userToEdit.role_data && userToEdit.role_data.id) {
        userForm.role_id = userToEdit.role_data.id;
        userForm.role = userToEdit.role_data.slug.replace(/-/g, '_');
    } else {
        const roleSlug = typeof userToEdit.role === 'string'
            ? userToEdit.role.replace(/-/g, '_')
            : 'employee';
        const matchingRole = roleOptions.value.find(role => role.slug === roleSlug);
        userForm.role_id = matchingRole?.value ||
            (roleOptions.value.length > 0 ? roleOptions.value[0].value : null);
        userForm.role = roleSlug;
    }
    userForm.user_type = userToEdit.user_type || 'employee';
    userForm.password = '';
    userForm.password_confirmation = '';
    errors.value = {};
    generalError.value = '';
    showEditModal.value = true;
};

const updateUser = async () => {
    errors.value = {};
    generalError.value = '';
    try {
        const payload = { ...userForm };
        if (!payload.password) {
            delete payload.password;
            delete payload.password_confirmation;
        }

        const response = await window.axios.put(`/api/users/${userForm.id}`, payload);
        const index = users.value.findIndex(u => u.id === userForm.id);
        if (index !== -1) {
            users.value[index] = response.data;
        }
        showEditModal.value = false;
        console.log('User updated successfully!');
        fetchUsers();
    } catch (error) {
        if (error.response && error.response.status === 422) {
            errors.value = error.response.data.errors;
        } else if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        } else {
            generalError.value = 'Failed to update user.';
            console.error('Error updating user:', error);
        }
    }
};

// --- Delete (Archive) User ---
const confirmUserDeletion = (user) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const deleteUser = async () => {
    generalError.value = '';
    try {
        await window.axios.delete(`/api/users/${userToDelete.value.id}`);
        // If we are viewing archived or all, refetch to get updated states
        await fetchUsers();
        showDeleteModal.value = false;
        userToDelete.value = null;
        console.log('User archived successfully!');
    } catch (error) {
        generalError.value = 'Failed to archive user.';
        if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        }
        console.error('Error archiving user:', error);
    }
};

// --- Restore (Unarchive) User ---
const restoreUser = async (user) => {
    generalError.value = '';
    try {
        await window.axios.post(`/api/users/${user.id}/restore`);
        await fetchUsers();
        console.log('User restored successfully!');
    } catch (error) {
        generalError.value = 'Failed to restore user.';
        if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        }
        console.error('Error restoring user:', error);
    }
};

// Function to update the role string when role_id changes
const updateRoleString = () => {
    const selectedRole = roleOptions.value.find(role => role.value === userForm.role_id);
    if (selectedRole) {
        userForm.role = selectedRole.slug;
    }
};

// Function to get the collapsed project summary string
const getProjectSummary = (userProjects) => {
    if (!userProjects || userProjects.length === 0) {
        return 'None';
    }
    const projectNames = userProjects.map(p => p.name);
    if (projectNames.length <= 2) {
        return projectNames.join(', ');
    }
    const remainingCount = projectNames.length - 2;
    return `${projectNames[0]}, ${projectNames[1]} and ${remainingCount} more`;
};


// Fetch users and roles when component is mounted
onMounted(() => {
    fetchUsers();
    fetchRoles();
});

// Helper function to get role badge color
const getRoleColor = (role) => {
    const roleSlug = typeof role === 'string' ? role.toLowerCase() : (role?.slug || 'employee');
    switch (roleSlug) {
        case 'super_admin':
        case 'super-admin':
            return 'bg-red-50 text-red-700 ring-red-600/20';
        case 'manager':
            return 'bg-indigo-50 text-indigo-700 ring-indigo-600/20';
        case 'employee':
            return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
        case 'contractor':
            return 'bg-yellow-50 text-yellow-700 ring-yellow-600/20';
        default:
            return 'bg-gray-50 text-gray-600 ring-gray-500/10';
    }
};

// Helper function to get user avatar background
const getAvatarColor = (name) => {
    const hash = name.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
    const colors = [
        'bg-blue-500', 'bg-indigo-500', 'bg-purple-500', 'bg-pink-500', 'bg-rose-500',
        'bg-orange-500', 'bg-amber-500', 'bg-lime-500', 'bg-emerald-500', 'bg-cyan-500'
    ];
    return colors[hash % colors.length];
};
</script>

<template>
    <Head title="Users" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Users</h2>
        </template>

        <div class="py-6 sm:py-12">
            <div class="px-4 sm:px-6 lg:px-8">
                <!-- User Statistics Section -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-sm p-6 flex items-center justify-between transition-all duration-300 hover:shadow-md hover:scale-105">
                        <div>
                            <h4 class="text-gray-500 font-medium text-sm">Total Users</h4>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ totalUsers }}</p>
                        </div>
                        <UsersIcon class="h-12 w-12 text-indigo-500 opacity-80" />
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm p-6 flex items-center justify-between transition-all duration-300 hover:shadow-md hover:scale-105">
                        <div>
                            <h4 class="text-gray-500 font-medium text-sm">Managers</h4>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ totalManagers }}</p>
                        </div>
                        <UsersIcon class="h-12 w-12 text-green-500 opacity-80" />
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm p-6 flex items-center justify-between transition-all duration-300 hover:shadow-md hover:scale-105">
                        <div>
                            <h4 class="text-gray-500 font-medium text-sm">Employees</h4>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ totalEmployees }}</p>
                        </div>
                        <UsersIcon class="h-12 w-12 text-yellow-500 opacity-80" />
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm p-6 flex items-center justify-between transition-all duration-300 hover:shadow-md hover:scale-105">
                        <div>
                            <h4 class="text-gray-500 font-medium text-sm">Contractors</h4>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ totalContractors }}</p>
                        </div>
                        <BriefcaseIcon class="h-12 w-12 text-pink-500 opacity-80" />
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="p-6">
                        <!-- User Management and Filter Section -->
                        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                            <h3 class="text-2xl font-bold text-gray-900">User Management</h3>
                            <div class="w-full md:w-auto flex flex-col sm:flex-row gap-4 items-center">
                                <div class="relative w-full">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <MagnifyingGlassIcon class="w-5 h-5 text-gray-400" />
                                    </div>
                                    <TextInput
                                        id="search"
                                        type="text"
                                        class="w-full pl-10 pr-3 py-2 rounded-lg"
                                        placeholder="Search users..."
                                        v-model="searchQuery"
                                    />
                                </div>
                                <select v-model="selectedRole" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm w-full sm:w-40">
                                    <option value="">All Roles</option>
                                    <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <select v-model="selectedProject" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm w-full sm:w-40">
                                    <option value="">All Projects</option>
                                    <option v-for="option in projectOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <select v-model="selectedStatus" @change="fetchUsers" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm w-full sm:w-40">
                                    <option value="active">Active</option>
                                    <option value="archived">Archived</option>
                                    <option value="all">All</option>
                                </select>
                                <PrimaryButton v-if="canCreateUsers" @click="openCreateModal" class="w-full sm:w-auto">
                                    <PlusIcon class="w-4 h-4 mr-2" />
                                    Add User
                                </PrimaryButton>
                            </div>
                        </div>

                        <div v-if="loading" class="text-center p-12">
                            <svg class="animate-spin h-10 w-10 text-indigo-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-gray-600 mt-4">Loading users...</p>
                        </div>
                        <div v-else-if="generalError" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert">
                            <p>{{ generalError }}</p>
                        </div>
                        <div v-else-if="filteredUsers.length === 0" class="text-center p-12">
                            <p class="text-gray-600">No users found matching your criteria.</p>
                        </div>
                        <div v-else>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <div v-for="userItem in filteredUsers" :key="userItem.id" class="bg-white rounded-2xl shadow-sm p-6 flex flex-col justify-between relative border border-gray-100 transition-all duration-300 hover:shadow-lg">
                                    <div>
                                        <div class="flex items-center space-x-4 mb-4">
                                            <div class="flex-shrink-0">
                                                <div :class="['h-12 w-12 rounded-full flex items-center justify-center text-white font-bold text-lg', getAvatarColor(userItem.name)]">{{ userItem.name.charAt(0) }}</div>
                                            </div>
                                            <div>
                                                <p class="text-xl font-bold text-gray-900">{{ userItem.name }}</p>
                                                <p class="text-sm text-gray-500 truncate">{{ userItem.email }}</p>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-2 mb-4">
                                            <span :class="['inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset', getRoleColor(userItem.role_data?.slug || userItem.role)]">
                                                Role: {{ userItem.role_data?.name || (typeof userItem.role === 'string' ? userItem.role.replace(/_|-/g, ' ') : 'Employee') }}
                                            </span>
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-500/10">
                                                Type: {{ userItem.user_type?.toUpperCase() || 'EMPLOYEE' }}
                                            </span>
                                        </div>

                                        <div class="text-sm text-gray-600 mt-auto">
                                            <p class="font-medium text-gray-800">Projects</p>
                                            <p v-if="userItem.projects && userItem.projects.length" class="text-xs mt-1 text-gray-500">
                                                {{ getProjectSummary(userItem.projects) }}
                                                <span v-if="userItem.projects.length > 2"
                                                      class="group relative inline-block cursor-pointer text-indigo-500 hover:text-indigo-700">
                                                    (more)
                                                    <span class="absolute z-10 bottom-full left-1/2 -translate-x-1/2 mb-2 w-max p-2 bg-gray-800 text-white text-xs rounded-md shadow-lg opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <ul class="list-none p-0 m-0">
                                                            <li v-for="project in userItem.projects" :key="project.id" class="whitespace-nowrap">{{ project.name }}</li>
                                                        </ul>
                                                        <div class="absolute w-3 h-3 bg-gray-800 transform rotate-45 -bottom-1 left-1/2 -translate-x-1/2"></div>
                                                    </span>
                                                </span>
                                            </p>
                                            <p v-else class="text-xs text-gray-400 mt-1">No projects assigned.</p>
                                        </div>
                                    </div>

                                    <!-- Action icons bottom right corner -->
                                    <div class="mt-4 flex justify-end gap-2">
                                        <button
                                            v-if="(isSuperAdmin ||
                                                  (isManager &&
                                                    ((userItem.role_data?.slug === 'employee' || userItem.role_data?.slug === 'contractor') ||
                                                     (typeof userItem.role === 'string' &&
                                                      (userItem.role.replace(/-/g, '_') === 'employee' || userItem.role.replace(/-/g, '_') === 'contractor')))) ||
                                                  authUser.id === userItem.id)"
                                            @click="openEditModal(userItem)"
                                            class="p-2 rounded-full text-gray-400 hover:text-indigo-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                                            title="Edit User">
                                            <PencilSquareIcon class="h-5 w-5" />
                                        </button>
                                        <button
                                            v-if="canDeleteUsers && userItem.id !== authUser.id && !userItem.deleted_at"
                                            @click="confirmUserDeletion(userItem)"
                                            class="p-2 rounded-full text-gray-400 hover:text-red-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors"
                                            title="Archive User">
                                            <ArchiveBoxIcon class="h-5 w-5" />
                                        </button>
                                        <button
                                            v-if="canDeleteUsers && userItem.deleted_at"
                                            @click="restoreUser(userItem)"
                                            class="p-2 rounded-full text-gray-400 hover:text-green-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors"
                                            title="Unarchive User">
                                            <ArrowUturnUpIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals remain the same, but with minor class updates for consistency -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Create New User</h2>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <form @submit.prevent="createUser">
                    <div class="mb-4">
                        <InputLabel for="create_name" value="Name" />
                        <TextInput id="create_name" type="text" class="mt-1 block w-full" v-model="userForm.name" required autofocus />
                        <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="create_email" value="Email" />
                        <TextInput id="create_email" type="email" class="mt-1 block w-full" v-model="userForm.email" required />
                        <InputError :message="errors.email ? errors.email[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="create_password" value="Password" />
                        <TextInput id="create_password" type="password" class="mt-1 block w-full" v-model="userForm.password" required autocomplete="new-password" />
                        <InputError :message="errors.password ? errors.password[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="create_password_confirmation" value="Confirm Password" />
                        <TextInput id="create_password_confirmation" type="password" class="mt-1 block w-full" v-model="userForm.password_confirmation" required autocomplete="new-password" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="create_role" value="Role" />
                        <select id="create_role" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="userForm.role_id" @change="updateRoleString">
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value"
                                    :disabled="!isSuperAdmin && (option.slug === 'super_admin' || option.slug === 'manager')">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.role_id ? errors.role_id[0] : (errors.role ? errors.role[0] : '')" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="create_user_type" value="User Type" />
                        <select id="create_user_type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="userForm.user_type">
                            <option value="employee">Employee</option>
                            <option value="contractor">Contractor</option>
                        </select>
                        <InputError :message="errors.user_type ? errors.user_type[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="create_timezone" value="Timezone" />
                        <TextInput id="create_timezone" type="text" class="mt-1 block w-full" v-model="userForm.timezone" placeholder="e.g., America/New_York" />
                        <InputError :message="errors.timezone ? errors.timezone[0] : ''" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
                        <PrimaryButton class="ms-3" type="submit">Create User</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Edit User</h2>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <form @submit.prevent="updateUser">
                    <div class="mb-4">
                        <InputLabel for="edit_name" value="Name" />
                        <TextInput id="edit_name" type="text" class="mt-1 block w-full" v-model="userForm.name" required autofocus />
                        <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_email" value="Email" />
                        <TextInput id="edit_email" type="email" class="mt-1 block w-full" v-model="userForm.email" required />
                        <InputError :message="errors.email ? errors.email[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_password" value="Password (leave blank to keep current)" />
                        <TextInput id="edit_password" type="password" class="mt-1 block w-full" v-model="userForm.password" autocomplete="new-password" />
                        <InputError :message="errors.password ? errors.password[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_password_confirmation" value="Confirm Password" />
                        <TextInput id="edit_password_confirmation" type="password" class="mt-1 block w-full" v-model="userForm.password_confirmation" autocomplete="new-password" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_role" value="Role" />
                        <select id="edit_role" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="userForm.role_id" @change="updateRoleString"
                                :disabled="!isSuperAdmin && userForm.id === authUser.id">
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value"
                                    :disabled="!isSuperAdmin && (option.slug === 'super_admin' || option.slug === 'manager') && userForm.id !== authUser.id">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.role_id ? errors.role_id[0] : (errors.role ? errors.role[0] : '')" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_user_type" value="User Type" />
                        <select id="edit_user_type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full" v-model="userForm.user_type">
                            <option value="employee">Employee</option>
                            <option value="contractor">Contractor</option>
                        </select>
                        <InputError :message="errors.user_type ? errors.user_type[0] : ''" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit_timezone" value="Timezone" />
                        <TextInput id="edit_timezone" type="text" class="mt-1 block w-full" v-model="userForm.timezone" placeholder="e.g., Europe/London" />
                        <InputError :message="errors.timezone ? errors.timezone[0] : ''" class="mt-2" />
                    </div>
                    <div class="mt-6 flex justify-end">
                        <SecondaryButton @click="showEditModal = false">Cancel</SecondaryButton>
                        <PrimaryButton class="ms-3" type="submit">Update User</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Archive this user?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    The user will be archived and can be unarchived later. No data will be permanently removed.
                </p>
                <div v-if="userToDelete" class="mt-4 text-gray-800">
                    <strong>User:</strong> {{ userToDelete.name }} ({{ userToDelete.email }}) - Role: {{ userToDelete.role_data?.name || (typeof userToDelete.role === 'string' ? userToDelete.role : 'Employee') }}
                </div>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteUser">Archive User</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
