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

// Access authenticated user
const authUser = computed(() => usePage().props.auth.user);
const { canDo, canView, canManage } = usePermissions();

// Reactive state
const users = ref([]);
const loading = ref(true);
const errors = ref({});
const generalError = ref('');

// Search and filter state
const searchQuery = ref('');
const selectedRole = ref('');
const selectedProject = ref('');

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

const canDeleteUsers = computed(() => isSuperAdmin.value);
const canCreateUsers = canDo('create_users');

// --- Fetch Users ---
const fetchUsers = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const response = await window.axios.get('/api/users');
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

// --- Delete User ---
const confirmUserDeletion = (user) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const deleteUser = async () => {
    generalError.value = '';
    try {
        await window.axios.delete(`/api/users/${userToDelete.value.id}`);
        users.value = users.value.filter(u => u.id !== userToDelete.value.id);
        showDeleteModal.value = false;
        userToDelete.value = null;
        console.log('User deleted successfully!');
    } catch (error) {
        generalError.value = 'Failed to delete user.';
        if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        }
        console.error('Error deleting user:', error);
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
                    <div class="bg-white rounded-lg shadow p-6 flex items-center justify-between">
                        <div>
                            <h4 class="text-gray-500 font-medium">Total Users</h4>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ totalUsers }}</p>
                        </div>
                        <svg class="h-10 w-10 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M12 4.354a4 4 0 100 5.292m7 12.247A8.998 8.998 0 0112 21c-2.404 0-4.639-.906-6.364-2.482M12 21a8.998 8.998 0 006.364-2.482M12 21a8.998 8.998 0 01-6.364-2.482m-4.062-8.083A8.998 8.998 0 0112 12a8.998 8.998 0 018.126 4.917m-16.252 0C3.766 12.391 7.29 9 12 9s8.234 3.391 8.126 8.917"></path>
                        </svg>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6 flex items-center justify-between">
                        <div>
                            <h4 class="text-gray-500 font-medium">Managers</h4>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ totalManagers }}</p>
                        </div>
                        <svg class="h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h-6a1 1 0 01-1-1v-4a1 1 0 011-1h6a1 1 0 011 1v4a1 1 0 01-1 1zm0 0l2 2m-2-2l-2 2"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9a2 2 0 11-4 0 2 2 0 014 0zm0 0a2 2 0 10-4 0m8 0a2 2 0 11-4 0 2 2 0 014 0zm0 0a2 2 0 10-4 0m-4 12v-1a4 4 0 014-4h4a4 4 0 014 4v1"></path>
                        </svg>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6 flex items-center justify-between">
                        <div>
                            <h4 class="text-gray-500 font-medium">Employees</h4>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ totalEmployees }}</p>
                        </div>
                        <svg class="h-10 w-10 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.5a2.25 2.25 0 00-2.25-2.25H15m0-1.5v3.75m0-3.75a3.75 3.75 0 00-3.75-3.75H8.25m0 0a3.75 3.75 0 013.75 3.75M8.25 9.75v3.75m-4.5 0a3.75 3.75 0 013.75-3.75H12a3.75 3.75 0 013.75 3.75m-4.5 0h4.5m-4.5 0a3.75 3.75 0 01-3.75-3.75H8.25m-3.75 3.75H4.5M12 18a2.25 2.25 0 002.25-2.25V15m0 0h3.75m-3.75 0v3.75m0-3.75a2.25 2.25 0 00-2.25-2.25H9.75m-3.75 2.25H6m0 0a2.25 2.25 0 00-2.25-2.25h-1.5m3.75 2.25v3.75m0 0h-1.5M6 21a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v1.5m1.5-1.5h1.5M6 21v1.5"></path>
                        </svg>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6 flex items-center justify-between">
                        <div>
                            <h4 class="text-gray-500 font-medium">Contractors</h4>
                            <p class="text-3xl font-bold text-gray-900 mt-1">{{ totalContractors }}</p>
                        </div>
                        <svg class="h-10 w-10 text-pink-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v2a2 2 0 01-2 2m-4-2H8m4 0h.01M17 11v-4a2 2 0 00-2-2m2 5v2m-4-2h-.01M3 20.25a2.25 2.25 0 01-2.25-2.25V8.25a2.25 2.25 0 012.25-2.25H18.75A2.25 2.25 0 0121 8.25v9.75a2.25 2.25 0 01-2.25 2.25H3.75z"></path>
                        </svg>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6">
                        <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
                            <h3 class="text-2xl font-bold text-gray-900">Manage Users</h3>
                            <div v-if="canCreateUsers" class="mt-4 sm:mt-0">
                                <PrimaryButton @click="openCreateModal">
                                    Create New User
                                </PrimaryButton>
                            </div>
                        </div>

                        <!-- Search and Filter Section -->
                        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="col-span-1 lg:col-span-2">
                                <TextInput
                                    id="search"
                                    type="text"
                                    class="w-full"
                                    placeholder="Search by name or email..."
                                    v-model="searchQuery"
                                />
                            </div>
                            <select v-model="selectedRole" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full">
                                <option value="">All Roles</option>
                                <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <select v-model="selectedProject" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full">
                                <option value="">All Projects</option>
                                <option v-for="option in projectOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <div v-if="loading" class="text-center p-8">
                            <p class="text-gray-600">Loading users...</p>
                        </div>
                        <div v-else-if="generalError" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded" role="alert">
                            <p>{{ generalError }}</p>
                        </div>
                        <div v-else-if="filteredUsers.length === 0" class="text-center p-8">
                            <p class="text-gray-600">No users found matching your criteria.</p>
                        </div>
                        <div v-else>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <div v-for="userItem in filteredUsers" :key="userItem.id" class="bg-gray-50 rounded-lg shadow-sm p-6 flex flex-col justify-between relative overflow-hidden">
                                    <div>
                                        <div class="flex items-center space-x-4 mb-4">
                                            <div class="flex-shrink-0">
                                                <div class="h-10 w-10 bg-indigo-500 rounded-full flex items-center justify-center text-white font-bold">{{ userItem.name.charAt(0) }}</div>
                                            </div>
                                            <div>
                                                <p class="text-lg font-semibold text-gray-900">{{ userItem.name }}</p>
                                                <p class="text-sm text-gray-500">{{ userItem.email }}</p>
                                            </div>
                                        </div>

                                        <div class="text-sm mb-4 flex space-x-4">
                                            <p><span class="font-medium text-gray-800">Role:</span> <span class="capitalize">{{ userItem.role_data?.name || (typeof userItem.role === 'string' ? userItem.role.replace(/_|-/g, ' ') : 'Employee') }}</span></p>
                                            <p><span class="font-medium text-gray-800">Type:</span> <span class="capitalize">{{ userItem.user_type || 'Employee' }}</span></p>
                                        </div>

                                        <!-- Projects with hover summary -->
                                        <div class="text-sm text-gray-600">
                                            <p class="font-medium text-gray-800 inline">Projects: <span
                                                class="font-normal">{{ getProjectSummary(userItem.projects) }}</span>
                                            </p>
                                            <div class="group relative inline-block ml-1">
                                                <div v-if="userItem.projects && userItem.projects.length > 2"
                                                     class="absolute z-10 bottom-full left-0 mb-2 w-full p-2 bg-gray-800 text-white text-xs rounded-md shadow-lg opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <ul class="list-none p-0 m-0">
                                                        <li v-for="project in userItem.projects" :key="project.id"
                                                            class="whitespace-nowrap">{{ project.name }}
                                                        </li>
                                                    </ul>
                                                    <div
                                                        class="absolute w-3 h-3 bg-gray-800 transform rotate-45 -bottom-1 left-4"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action icons top right corner -->
                                    <div class="absolute top-4 right-4 flex space-x-2">
                                        <button
                                            v-if="(isSuperAdmin ||
                                                  (isManager &&
                                                    ((userItem.role_data?.slug === 'employee' || userItem.role_data?.slug === 'contractor') ||
                                                     (typeof userItem.role === 'string' &&
                                                      (userItem.role.replace(/-/g, '_') === 'employee' || userItem.role.replace(/-/g, '_') === 'contractor')))) ||
                                                  authUser.id === userItem.id)"
                                            @click="openEditModal(userItem)"
                                            class="p-1 rounded-full text-gray-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                            title="Edit User">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.794.793-2.828-2.828.794-.793zm-5.646 12.016v.538h.538l8.53-8.529-1.39-1.389-8.53 8.529z"></path>
                                            </svg>
                                        </button>
                                        <button
                                            v-if="isSuperAdmin && userItem.id !== authUser.id"
                                            @click="confirmUserDeletion(userItem)"
                                            class="p-1 rounded-full text-gray-400 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                            title="Delete User">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
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
                    Are you sure you want to delete this user?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    This action cannot be undone. All data associated with this user will be removed.
                </p>
                <div v-if="userToDelete" class="mt-4 text-gray-800">
                    <strong>User:</strong> {{ userToDelete.name }} ({{ userToDelete.email }}) - Role: {{ userToDelete.role_data?.name || (typeof userToDelete.role === 'string' ? userToDelete.role : 'Employee') }}
                </div>
                <div v-if="generalError" class="text-red-600 text-sm mb-4">{{ generalError }}</div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <DangerButton class="ms-3" @click="deleteUser">Delete User</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
