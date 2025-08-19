<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Roles Management
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Roles</h3>
                            <Link :href="route('admin.roles.create')" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                                Create New Role
                            </Link>
                        </div>

                        <div v-if="!roles || roles.length === 0" class="text-center py-4">
                            <p>No roles found. Create your first role to get started.</p>
                        </div>

                        <div v-else class="overflow-x-auto">
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
                                            Permissions
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="role in roles" :key="role.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ role.name }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ role.slug }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500">{{ role.description }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-500">
                                                <span v-if="!role.permissions || role.permissions.length === 0">No permissions</span>
                                                <div v-else class="flex flex-wrap gap-1">
                                                    <span
                                                        v-for="permission in role.permissions.slice(0, 3)"
                                                        :key="permission.id"
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                                    >
                                                        {{ permission.name }}
                                                    </span>
                                                    <span
                                                        v-if="role.permissions && role.permissions.length > 3"
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                                                    >
                                                        +{{ role.permissions.length - 3 }} more
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <Link :href="route('admin.roles.edit', role.id)" class="text-indigo-600 hover:text-indigo-900">Edit</Link>
                                                <Link :href="route('admin.roles.show', role.id)" class="text-blue-600 hover:text-blue-900">View</Link>
                                                <Link :href="route('admin.roles.permissions', role.id)" class="text-green-600 hover:text-green-900">Permissions</Link>
                                                <Link :href="route('admin.roles.duplicate', role.id)" class="text-purple-600 hover:text-purple-900">Duplicate</Link>
                                                <button
                                                    @click="confirmRoleDeletion(role)"
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

        <!-- Delete Role Confirmation Modal -->
        <Modal :show="confirmingRoleDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Are you sure you want to delete this role?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Once this role is deleted, all users assigned to this role will lose the associated permissions.
                </p>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        Cancel
                    </SecondaryButton>

                    <DangerButton
                        class="ml-3"
                        @click="deleteRole"
                    >
                        Delete Role
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
    roles: Array,
});

const confirmingRoleDeletion = ref(false);
const roleToDelete = ref(null);

const confirmRoleDeletion = (role) => {
    roleToDelete.value = role;
    confirmingRoleDeletion.value = true;
};

const closeModal = () => {
    confirmingRoleDeletion.value = false;
    setTimeout(() => {
        roleToDelete.value = null;
    }, 300);
};

const deleteRole = () => {
    if (roleToDelete.value) {
        router.delete(route('admin.roles.destroy', roleToDelete.value.id), {
            onSuccess: () => {
                closeModal();
            },
        });
    }
};
</script>
