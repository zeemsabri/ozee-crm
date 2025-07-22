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
// No specific SelectInput component needed if using native <select> for simplicity

// Access authenticated user
const authUser = computed(() => usePage().props.auth.user);

// Reactive state
const users = ref([]);
const loading = ref(true);
const errors = ref({});
const generalError = ref('');

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
});

// State for user being deleted
const userToDelete = ref(null);

// Role options for dropdown
const roleOptions = ref([]);

// Fetch roles from the database
const fetchRoles = async () => {
    try {
        // Specify 'application' role type for user management
        const response = await window.axios.get('/api/roles?type=application');
        roleOptions.value = response.data.map(role => ({
            // Use role_id as the value for the new role system
            value: role.id,
            // Keep the slug (converted to underscore) as a data attribute for backward compatibility
            slug: role.slug.replace(/-/g, '_'),
            label: role.name
        }));
    } catch (error) {
        console.error('Error fetching roles:', error);
        // Fallback to hardcoded roles if API fails
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
const canCreateUsers = computed(() => isSuperAdmin.value || isManager.value);
const canDeleteUsers = computed(() => isSuperAdmin.value);


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
    // Set default role to contractor or first available role from roleOptions
    // Find contractor role by slug for backward compatibility
    const contractorRole = roleOptions.value.find(role => role.slug === 'contractor');
    userForm.role_id = contractorRole?.value ||
                      (roleOptions.value.length > 0 ? roleOptions.value[0].value : 4); // 4 is fallback contractor ID
    userForm.role = contractorRole?.slug || 'contractor'; // Keep role string for backward compatibility
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
        alert('User created successfully!');
        fetchUsers(); // Refresh the list to ensure correct permissions/relationships are shown
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

    // Set role_id from user's role_data if available
    if (userToEdit.role_data && userToEdit.role_data.id) {
        userForm.role_id = userToEdit.role_data.id;

        // Set role string from role_data.slug for backward compatibility
        userForm.role = userToEdit.role_data.slug.replace(/-/g, '_');
    } else {
        // Fallback: find role by slug for backward compatibility
        // Make sure userToEdit.role is a string before using replace
        const roleSlug = typeof userToEdit.role === 'string'
            ? userToEdit.role.replace(/-/g, '_')
            : 'employee'; // Default to employee if role is not a string

        const matchingRole = roleOptions.value.find(role => role.slug === roleSlug);
        userForm.role_id = matchingRole?.value ||
                          (roleOptions.value.length > 0 ? roleOptions.value[0].value : null);

        // Keep role string for backward compatibility
        userForm.role = roleSlug;
    }

    userForm.password = ''; // Clear passwords for edit
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
        // Don't send password if empty (no change)
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
        alert('User updated successfully!');
        fetchUsers(); // Refresh the list to ensure correct permissions/relationships are shown
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
        alert('User deleted successfully!');
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

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-2xl font-bold mb-4">Manage Users</h3>

                        <div v-if="canCreateUsers" class="mb-6">
                            <PrimaryButton @click="openCreateModal">
                                Create New User
                            </PrimaryButton>
                        </div>

                        <div v-if="loading" class="text-gray-600">Loading users...</div>
                        <div v-else-if="generalError" class="text-red-600">{{ generalError }}</div>
                        <div v-else-if="users.length === 0" class="text-gray-600">No users found.</div>
                        <div v-else>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Projects</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="userItem in users" :key="userItem.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ userItem.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ userItem.email }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap capitalize">{{ userItem.role_data?.name || (typeof userItem.role === 'string' ? userItem.role.replace(/_|-/g, ' ') : 'Employee') }}</td>
                                    <td class="px-6 py-4">
                                            <span v-if="userItem.projects && userItem.projects.length">
                                                {{ userItem.projects.map(p => p.name).join(', ') }}
                                            </span>
                                        <span v-else class="text-gray-400">None</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <PrimaryButton
                                                v-if="(isSuperAdmin ||
                                                      (isManager &&
                                                        ((userItem.role_data?.slug === 'employee' || userItem.role_data?.slug === 'contractor') ||
                                                         (typeof userItem.role === 'string' &&
                                                          (userItem.role.replace(/-/g, '_') === 'employee' || userItem.role.replace(/-/g, '_') === 'contractor')))) ||
                                                      authUser.id === userItem.id)"
                                                @click="openEditModal(userItem)">
                                                Edit
                                            </PrimaryButton>
                                            <DangerButton
                                                v-if="isSuperAdmin && userItem.id !== authUser.id"
                                                @click="confirmUserDeletion(userItem)">
                                                Delete
                                            </DangerButton>
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
                                :disabled="!isSuperAdmin && userForm.id !== authUser.id">
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value"
                                    :disabled="!isSuperAdmin && (option.slug === 'super_admin' || option.slug === 'manager') && userForm.id !== authUser.id">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="errors.role_id ? errors.role_id[0] : (errors.role ? errors.role[0] : '')" class="mt-2" />
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
