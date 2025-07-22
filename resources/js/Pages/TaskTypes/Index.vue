<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { useAuthUser, usePermissions, vPermission } from '@/Directives/permissions';

// Access user from permissions utility
const authUser = useAuthUser();

// Reactive state
const taskTypes = ref([]);
const loading = ref(true);
const errors = ref({});
const generalError = ref('');

// Modals state
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);

// Form state
const taskTypeForm = ref({
    id: null,
    name: '',
    description: '',
});

// State for task type being deleted
const taskTypeToDelete = ref(null);

// Set up permission checking functions for global permissions
const { canDo, canView, canManage } = usePermissions();

// Legacy role-based checks (kept for backward compatibility)
const isSuperAdmin = computed(() => {
    if (!authUser.value) return false;
    return (authUser.value.role_data && authUser.value.role_data.slug === 'super-admin') ||
           authUser.value.role === 'super_admin' ||
           authUser.value.role === 'super-admin';
});

// Permission-based checks using the permission utilities
// For simplicity, we'll use the same permissions as for projects
const canCreateTaskTypes = computed(() => {
    return canDo('create_projects').value || isSuperAdmin.value;
});

const canManageTaskTypes = computed(() => {
    return canDo('manage_projects').value || isSuperAdmin.value;
});

const canDeleteTaskTypes = computed(() => {
    return canDo('delete_projects').value || isSuperAdmin.value;
});

// Fetch task types from the API
const fetchTaskTypes = async () => {
    loading.value = true;
    generalError.value = '';
    try {
        const response = await window.axios.get('/api/task-types');
        taskTypes.value = response.data;
    } catch (error) {
        generalError.value = 'Failed to load task types.';
        console.error('Error fetching task types:', error);
        if (error.response && (error.response.status === 401 || error.response.status === 403)) {
            generalError.value = 'You are not authorized to view this content or your session expired. Please log in.';
        }
    } finally {
        loading.value = false;
    }
};

// --- Create Task Type ---
const openCreateModal = () => {
    // Check if user has permission to create task types
    if (!canCreateTaskTypes.value) {
        alert('You do not have permission to create task types.');
        return;
    }

    taskTypeForm.value = {
        id: null,
        name: '',
        description: '',
    };
    errors.value = {};
    showCreateModal.value = true;
};

// --- Edit Task Type ---
const openEditModal = (taskType) => {
    // Check if user has permission to manage task types
    if (!canManageTaskTypes.value) {
        alert('You do not have permission to edit task types.');
        return;
    }

    taskTypeForm.value = {
        id: taskType.id,
        name: taskType.name,
        description: taskType.description || '',
    };
    errors.value = {};
    showEditModal.value = true;
};

// --- Submit Task Type Form ---
const submitTaskTypeForm = async () => {
    errors.value = {};
    generalError.value = '';

    try {
        let response;
        if (taskTypeForm.value.id) {
            // Update existing task type
            response = await window.axios.put(`/api/task-types/${taskTypeForm.value.id}`, taskTypeForm.value);

            // Update the task type in the list
            const index = taskTypes.value.findIndex(t => t.id === taskTypeForm.value.id);
            if (index !== -1) {
                taskTypes.value[index] = response.data;
            }

            showEditModal.value = false;
            alert('Task type updated successfully!');
        } else {
            // Create new task type
            response = await window.axios.post('/api/task-types', taskTypeForm.value);

            // Add the new task type to the list
            taskTypes.value.push(response.data);

            showCreateModal.value = false;
            alert('Task type created successfully!');
        }
    } catch (error) {
        console.error('Error submitting task type form:', error);

        if (error.response && error.response.data && error.response.data.errors) {
            errors.value = error.response.data.errors;
        } else {
            generalError.value = 'Failed to save task type. Please try again.';
        }
    }
};

// --- Delete Task Type ---
const confirmTaskTypeDeletion = (taskType) => {
    // Check if user has permission to delete task types
    if (!canDeleteTaskTypes.value) {
        alert('You do not have permission to delete task types.');
        return;
    }

    taskTypeToDelete.value = taskType;
    showDeleteModal.value = true;
};

const deleteTaskType = async () => {
    generalError.value = '';

    // Check if task type is valid
    const taskTypeId = taskTypeToDelete.value?.id;
    if (!taskTypeId) {
        generalError.value = 'Invalid task type.';
        return;
    }

    // Check if user has permission to delete task types
    if (!canDeleteTaskTypes.value) {
        generalError.value = 'You do not have permission to delete task types.';
        return;
    }

    try {
        await window.axios.delete(`/api/task-types/${taskTypeId}`);
        taskTypes.value = taskTypes.value.filter(t => t.id !== taskTypeId);
        showDeleteModal.value = false;
        taskTypeToDelete.value = null;
        alert('Task type deleted successfully!');
    } catch (error) {
        generalError.value = 'Failed to delete task type.';
        if (error.response && error.response.data.message) {
            generalError.value = error.response.data.message;
        }
        console.error('Error deleting task type:', error);
    }
};

// Fetch task types when the component is mounted
onMounted(() => {
    fetchTaskTypes();
});
</script>

<template>
    <Head title="Task Types" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Task Types</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-2xl font-bold mb-4">Task Types Management</h3>

                        <div v-if="canCreateTaskTypes" class="mb-6">
                            <PrimaryButton @click="openCreateModal">
                                Create New Task Type
                            </PrimaryButton>
                        </div>

                        <div v-if="loading" class="text-gray-600">Loading task types...</div>
                        <div v-else-if="generalError" class="text-red-600">{{ generalError }}</div>
                        <div v-else-if="taskTypes.length === 0" class="text-gray-600">No task types found.</div>
                        <div v-else>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="taskType in taskTypes" :key="taskType.id">
                                    <td class="px-6 py-4 whitespace-nowrap">{{ taskType.name }}</td>
                                    <td class="px-6 py-4">{{ taskType.description || 'No description' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ taskType.created_by_user ? taskType.created_by_user.name : 'Unknown' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ new Date(taskType.created_at).toLocaleDateString() }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <PrimaryButton v-if="canManageTaskTypes" @click="openEditModal(taskType)">Edit</PrimaryButton>
                                            <DangerButton v-if="canDeleteTaskTypes" @click="confirmTaskTypeDeletion(taskType)">Delete</DangerButton>
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

        <!-- Create Task Type Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Create New Task Type
                </h2>
                <div class="mt-6">
                    <div class="mb-4">
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="taskTypeForm.name"
                            required
                            autofocus
                        />
                        <InputError v-if="errors.name" :message="errors.name[0]" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            v-model="taskTypeForm.description"
                            rows="3"
                        ></textarea>
                        <InputError v-if="errors.description" :message="errors.description[0]" class="mt-2" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showCreateModal = false" class="mr-2">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="submitTaskTypeForm">
                        Create
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Edit Task Type Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Edit Task Type
                </h2>
                <div class="mt-6">
                    <div class="mb-4">
                        <InputLabel for="edit-name" value="Name" />
                        <TextInput
                            id="edit-name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="taskTypeForm.name"
                            required
                            autofocus
                        />
                        <InputError v-if="errors.name" :message="errors.name[0]" class="mt-2" />
                    </div>
                    <div class="mb-4">
                        <InputLabel for="edit-description" value="Description" />
                        <textarea
                            id="edit-description"
                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            v-model="taskTypeForm.description"
                            rows="3"
                        ></textarea>
                        <InputError v-if="errors.description" :message="errors.description[0]" class="mt-2" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showEditModal = false" class="mr-2">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="submitTaskTypeForm">
                        Update
                    </PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- Delete Task Type Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this task type?
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    This action cannot be undone. If tasks are using this task type, the deletion will fail.
                </p>
                <div v-if="taskTypeToDelete" class="mt-4 text-gray-800">
                    <strong>Task Type:</strong> {{ taskTypeToDelete.name }}
                </div>
                <div v-if="generalError" class="mt-4 text-red-600">
                    {{ generalError }}
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showDeleteModal = false" class="mr-2">
                        Cancel
                    </SecondaryButton>
                    <DangerButton @click="deleteTaskType">
                        Delete
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
