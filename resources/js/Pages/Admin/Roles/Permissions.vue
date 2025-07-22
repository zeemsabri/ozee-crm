<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Manage Permissions for Role: {{ role.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <form @submit.prevent="submit">
                            <div class="mb-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Role Permissions</h3>
                                    <div class="flex space-x-2">
                                        <button
                                            type="button"
                                            @click="selectAll"
                                            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                                        >
                                            Select All
                                        </button>
                                        <button
                                            type="button"
                                            @click="deselectAll"
                                            class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-400 active:bg-gray-600 focus:outline-none focus:border-gray-600 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                                        >
                                            Deselect All
                                        </button>
                                    </div>
                                </div>

                                <div v-if="Object.keys(permissions).length === 0" class="text-sm text-gray-500 p-4 bg-gray-50 rounded-lg">
                                    No permissions available. Please create some permissions first.
                                </div>

                                <div v-else>
                                    <div v-for="(categoryPermissions, category) in permissions" :key="category" class="mb-6">
                                        <div class="flex justify-between items-center mb-2">
                                            <h4 class="font-medium text-gray-700">{{ category }}</h4>
                                            <div class="flex space-x-2">
                                                <button
                                                    type="button"
                                                    @click="selectCategory(category)"
                                                    class="text-xs text-indigo-600 hover:text-indigo-900"
                                                >
                                                    Select All
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="deselectCategory(category)"
                                                    class="text-xs text-gray-600 hover:text-gray-900"
                                                >
                                                    Deselect All
                                                </button>
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                                <div v-for="permission in categoryPermissions" :key="permission.id" class="flex items-start">
                                                    <div class="flex items-center h-5">
                                                        <input
                                                            :id="`permission-${permission.id}`"
                                                            type="checkbox"
                                                            :value="permission.id"
                                                            v-model="form.permissions"
                                                            class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                                        />
                                                    </div>
                                                    <div class="ml-3 text-sm">
                                                        <label :for="`permission-${permission.id}`" class="font-medium text-gray-700">{{ permission.name }}</label>
                                                        <p v-if="permission.description" class="text-gray-500">{{ permission.description }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <InputError class="mt-2" :message="form.errors.permissions" />
                            </div>

                            <div class="flex items-center justify-end mt-4">
                                <Link
                                    :href="route('admin.roles.show', role.id)"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                                >
                                    Cancel
                                </Link>

                                <PrimaryButton
                                    class="ml-4"
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Update Permissions
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
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    role: Object,
    permissions: Object,
    rolePermissions: Array,
});

const form = useForm({
    permissions: props.rolePermissions || [],
});

// Get all permission IDs
const allPermissionIds = Object.values(props.permissions)
    .flat()
    .map(permission => permission.id);

// Select all permissions
const selectAll = () => {
    form.permissions = [...allPermissionIds];
};

// Deselect all permissions
const deselectAll = () => {
    form.permissions = [];
};

// Select all permissions in a category
const selectCategory = (category) => {
    const categoryPermissionIds = props.permissions[category].map(permission => permission.id);
    const currentPermissions = new Set(form.permissions);

    categoryPermissionIds.forEach(id => {
        currentPermissions.add(id);
    });

    form.permissions = Array.from(currentPermissions);
};

// Deselect all permissions in a category
const deselectCategory = (category) => {
    const categoryPermissionIds = new Set(props.permissions[category].map(permission => permission.id));
    form.permissions = form.permissions.filter(id => !categoryPermissionIds.has(id));
};

const submit = () => {
    form.post(route('admin.roles.updatePermissions', props.role.id), {
        onSuccess: () => {
            // Form is automatically reset on success
        },
    });
};
</script>
