<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Role Details: {{ role.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="mb-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Role Information</h3>
                                <div class="flex space-x-2">
                                    <Link
                                        :href="route('admin.roles.edit', role.id)"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
                                    >
                                        Edit Role
                                    </Link>
                                    <Link
                                        :href="route('admin.roles.permissions', role.id)"
                                        class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring focus:ring-green-300 disabled:opacity-25 transition"
                                    >
                                        Manage Permissions
                                    </Link>
                                </div>
                            </div>

                            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Name</h4>
                                        <p class="mt-1 text-sm text-gray-900">{{ role.name }}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-500">Slug</h4>
                                        <p class="mt-1 text-sm text-gray-900">{{ role.slug }}</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <h4 class="text-sm font-medium text-gray-500">Description</h4>
                                        <p class="mt-1 text-sm text-gray-900">{{ role.description || 'No description provided' }}</p>
                                    </div>
                                </div>
                            </div>

                            <h3 class="text-lg font-medium text-gray-900 mb-4">Permissions</h3>

                            <div v-if="role.permissions.length === 0" class="text-sm text-gray-500 p-4 bg-gray-50 rounded-lg">
                                This role has no permissions assigned.
                            </div>

                            <div v-else>
                                <!-- Group permissions by category -->
                                <div v-for="(categoryPermissions, category) in groupedPermissions" :key="category" class="mb-6">
                                    <h4 class="font-medium text-gray-700 mb-2">{{ category }}</h4>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="permission in categoryPermissions"
                                                :key="permission.id"
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                            >
                                                {{ permission.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Users with this Role</h3>

                            <!-- Application Users -->
                            <div v-if="applicationUsers && applicationUsers.length > 0" class="mb-6">
                                <h4 class="font-medium text-gray-700 mb-2">Application Users</h4>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Name
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Email
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="user in applicationUsers" :key="user.id">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500">{{ user.email }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <button
                                                        @click="revokeRole(user.id, 'application')"
                                                        class="text-red-600 hover:text-red-900"
                                                    >
                                                        Revoke
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Project Users -->
                            <div v-if="projectUsers && projectUsers.length > 0" class="mb-6">
                                <h4 class="font-medium text-gray-700 mb-2">Project Users</h4>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Name
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Email
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Project
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="user in projectUsers" :key="`${user.id}-${user.project_id}`">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500">{{ user.email }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-500">{{ user.project_name }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <button
                                                        @click="revokeRole(user.id, 'project', user.project_id)"
                                                        class="text-red-600 hover:text-red-900"
                                                    >
                                                        Revoke
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div v-if="(!applicationUsers || applicationUsers.length === 0) && (!projectUsers || projectUsers.length === 0)" class="text-sm text-gray-500 p-4 bg-gray-50 rounded-lg">
                                No users have been assigned this role.
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <Link
                                :href="route('admin.roles.index')"
                                class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                            >
                                Back to Roles
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    role: Object,
    applicationUsers: Array,
    projectUsers: Array,
});

// Group permissions by category
const groupedPermissions = computed(() => {
    if (!props.role.permissions || props.role.permissions.length === 0) {
        return {};
    }

    return props.role.permissions.reduce((groups, permission) => {
        const category = permission.category || 'Uncategorized';
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push(permission);
        return groups;
    }, {});
});

// Loading state for revoke actions
const isRevoking = ref(false);

// Function to revoke a role from a user
const revokeRole = (userId, roleType, projectId = null) => {
    if (isRevoking.value) return;

    isRevoking.value = true;

    const data = {
        user_id: userId,
        role_id: props.role.id,
        role_type: roleType
    };

    if (projectId) {
        data.project_id = projectId;
    }

    router.post(route('admin.roles.revoke-user'), data, {
        preserveScroll: true,
        onSuccess: () => {
            isRevoking.value = false;
        },
        onError: () => {
            isRevoking.value = false;
        }
    });
};
</script>
