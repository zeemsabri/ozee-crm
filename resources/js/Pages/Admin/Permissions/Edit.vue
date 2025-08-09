<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Permission: {{ permission.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <form @submit.prevent="submit">
                            <div class="mb-6">
                                <InputLabel for="name" value="Name" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.name"
                                    required
                                    autofocus
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div class="mb-6">
                                <InputLabel for="slug" value="Slug" />
                                <TextInput
                                    id="slug"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.slug"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.slug" />
                            </div>

                            <div class="mb-6">
                                <InputLabel for="description" value="Description (optional)" />
                                <textarea
                                    id="description"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                    v-model="form.description"
                                    rows="3"
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>

                            <div class="mb-6">
                                <InputLabel for="category" value="Category" />
                                <div class="flex space-x-2">
                                    <select
                                        id="category"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        v-model="form.category"
                                        required
                                    >
                                        <option value="" disabled>Select a category</option>
                                        <option v-for="category in categories" :key="category" :value="category">
                                            {{ category }}
                                        </option>
                                        <option value="new">+ Add New Category</option>
                                    </select>
                                    <TextInput
                                        v-if="form.category === 'new'"
                                        type="text"
                                        class="mt-1 block w-full"
                                        v-model="newCategory"
                                        placeholder="Enter new category name"
                                        required
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.category" />
                            </div>

                            <!-- Roles Section -->
                            <div class="mb-6">
                                <InputLabel for="roles" value="Assign to Roles" />
                                <div class="mt-2 max-h-60 overflow-y-auto p-2 border border-gray-300 rounded-md">
                                    <div v-if="!roles || roles.length === 0" class="text-gray-500 text-sm">
                                        No roles available.
                                    </div>
                                    <div v-else class="space-y-2">
                                        <div v-for="role in roles" :key="role.id" class="flex items-start">
                                            <div class="flex items-center h-5">
                                                <input
                                                    :id="`role-${role.id}`"
                                                    type="checkbox"
                                                    :value="role.id"
                                                    v-model="form.roles"
                                                    class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                />
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label :for="`role-${role.id}`" class="font-medium text-gray-700">
                                                    {{ role.name }}
                                                    <span class="text-xs text-gray-500">({{ role.type }})</span>
                                                </label>
                                                <p v-if="role.description" class="text-gray-500">{{ role.description }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    Select roles to assign this permission to them.
                                </p>
                                <InputError class="mt-2" :message="form.errors.roles" />
                            </div>

                            <!-- Users with this Permission Section -->
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Users with this Permission</h3>

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
                                                        Role
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
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-500">
                                                            {{ roles.find(r => r.id === user.role_id)?.name || 'Unknown Role' }}
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <button
                                                            @click="revokePermission(user.id, 'application')"
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
                                                        Role
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
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-500">
                                                            {{ roles.find(r => r.id === user.role_id)?.name || 'Unknown Role' }}
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <button
                                                            @click="revokePermission(user.id, 'project', user.project_id)"
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
                                    No users have been assigned this permission.
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-4">
                                <Link
                                    :href="route('admin.permissions.index')"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                                >
                                    Cancel
                                </Link>

                                <PrimaryButton
                                    class="ml-4"
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Update Permission
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    permission: Object,
    categories: Array,
    roles: Array,
    permissionRoles: Array,
    applicationUsers: Array,
    projectUsers: Array,
});

const newCategory = ref('');
const form = useForm({
    name: props.permission.name,
    slug: props.permission.slug,
    description: props.permission.description || '',
    category: props.permission.category,
    roles: props.permissionRoles || [],
});

// Watch for changes to the category selection
watch(() => form.category, (value) => {
    if (value !== 'new') {
        newCategory.value = '';
    }
});

// Loading state for revoke actions
const isRevoking = ref(false);

// Function to revoke a permission from a user
const revokePermission = (userId, roleType, projectId = null) => {
    if (isRevoking.value) return;

    isRevoking.value = true;

    const data = {
        user_id: userId,
        permission_id: props.permission.id,
        role_type: roleType
    };

    if (projectId) {
        data.project_id = projectId;
    }

    router.post(route('admin.permissions.revoke-user'), data, {
        preserveScroll: true,
        onSuccess: () => {
            isRevoking.value = false;
        },
        onError: () => {
            isRevoking.value = false;
        }
    });
};

const submit = () => {
    // If a new category is being added, use that value
    if (form.category === 'new' && newCategory.value) {
        form.category = newCategory.value;
    }

    form.put(route('admin.permissions.update', props.permission.id), {
        onSuccess: () => {
            // Form is automatically reset on success
        },
    });
};
</script>
