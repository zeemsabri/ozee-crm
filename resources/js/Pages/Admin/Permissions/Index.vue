<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Permissions Management
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Permissions</h3>
                            <div class="flex space-x-2">
                                <Link :href="route('admin.permissions.create')" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                                    Create New Permission
                                </Link>
                                <Link :href="route('admin.permissions.bulk-create')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                    Bulk Create Permissions
                                </Link>
                            </div>
                        </div>

                        <div v-if="Object.keys(permissions).length === 0" class="text-center py-4">
                            <p>No permissions found. Create your first permission to get started.</p>
                        </div>

                        <div v-else>
                            <div v-for="(categoryPermissions, category) in permissions" :key="category" class="mb-8">
                                <h4 class="text-lg font-medium text-gray-700 mb-4">{{ category }}</h4>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Name
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Slug
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Description
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Roles
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="permission in categoryPermissions" :key="permission.id">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ permission.name }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500">{{ permission.slug }}</div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-500">{{ permission.description || 'No description' }}</div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-500">
                                                        <span v-if="!permission.roles || permission.roles.length === 0">No roles</span>
                                                        <div v-else class="flex flex-wrap gap-1">
                                                            <span
                                                                v-for="role in permission.roles.slice(0, 3)"
                                                                :key="role.id"
                                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                                            >
                                                                {{ role.name }}
                                                            </span>
                                                            <span
                                                                v-if="permission.roles && permission.roles.length > 3"
                                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                                                            >
                                                                +{{ permission.roles.length - 3 }} more
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <Link :href="route('admin.permissions.edit', permission.id)" class="text-indigo-600 hover:text-indigo-900">Edit</Link>
                                                        <button
                                                            @click="confirmPermissionDeletion(permission)"
                                                            class="text-red-600 hover:text-red-900"
                                                        >
                                                            Delete
                                                        </button>
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
            </div>
        </div>

        <!-- Delete Permission Confirmation Modal -->
        <Modal :show="confirmingPermissionDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this permission?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Once this permission is deleted, it will be removed from all roles that have it assigned.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        class="ml-3"
                        @click="deletePermission"
                    >
                        Delete Permission
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

const props = defineProps({
    permissions: Object,
});

const confirmingPermissionDeletion = ref(false);
const permissionToDelete = ref(null);

const confirmPermissionDeletion = (permission) => {
    permissionToDelete.value = permission;
    confirmingPermissionDeletion.value = true;
};

const closeModal = () => {
    confirmingPermissionDeletion.value = false;
    setTimeout(() => {
        permissionToDelete.value = null;
    }, 300);
};

const deletePermission = () => {
    if (permissionToDelete.value) {
        router.delete(route('admin.permissions.destroy', permissionToDelete.value.id), {
            onSuccess: () => {
                closeModal();
            },
        });
    }
};
</script>
