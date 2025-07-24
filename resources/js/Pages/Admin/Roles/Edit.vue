<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Edit Role: {{ role.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <form @submit.prevent="submit">
                            <!-- Basic Information Section -->
                            <div class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
                                <button
                                    @click.prevent="basicInfoExpanded = !basicInfoExpanded"
                                    class="w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 transition-colors"
                                >
                                    <h3 class="text-lg font-semibold text-gray-900">Basic Information</h3>
                                    <span class="text-gray-500">
                                        <span v-if="basicInfoExpanded">▼</span>
                                        <span v-else>▶</span>
                                    </span>
                                </button>

                                <div v-if="basicInfoExpanded" class="p-4">
                                    <div class="mb-4">
                                        <InputLabel for="name" value="Role Name" />
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

                                    <div>
                                        <InputLabel for="description" value="Role Description" />
                                        <textarea
                                            id="description"
                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                            v-model="form.description"
                                            rows="3"
                                            placeholder="Describe the purpose and scope of this role"
                                        ></textarea>
                                        <InputError class="mt-2" :message="form.errors.description" />
                                    </div>
                                </div>
                            </div>

                            <!-- Role Type Section -->
                            <div class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
                                <button
                                    @click.prevent="roleTypeExpanded = !roleTypeExpanded"
                                    class="w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 transition-colors"
                                >
                                    <h3 class="text-lg font-semibold text-gray-900">Role Type</h3>
                                    <span class="text-gray-500">
                                        <span v-if="roleTypeExpanded">▼</span>
                                        <span v-else>▶</span>
                                    </span>
                                </button>

                                <div v-if="roleTypeExpanded" class="p-4">
                                    <p class="text-sm text-gray-600 mb-2">Select the scope where this role will be applied</p>
                                    <select
                                        id="type"
                                        class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                        v-model="form.type"
                                        required
                                    >
                                        <option value="">Select a type</option>
                                        <option value="application">Application</option>
                                        <option value="client">Client</option>
                                        <option value="project">Project</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.type" />
                                </div>
                            </div>

                            <!-- Permissions Section -->
                            <div class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
                                <button
                                    @click.prevent="permissionsExpanded = !permissionsExpanded"
                                    class="w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 transition-colors"
                                >
                                    <h3 class="text-lg font-semibold text-gray-900">Permissions</h3>
                                    <span class="text-gray-500">
                                        <span v-if="permissionsExpanded">▼</span>
                                        <span v-else>▶</span>
                                    </span>
                                </button>

                                <div v-if="permissionsExpanded" class="p-4">
                                    <p class="text-sm text-gray-600 mb-4">Select the permissions to assign to this role</p>

                                    <!-- Search input for permissions -->
                                    <div class="mb-4">
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/>
                                                </svg>
                                            </div>
                                            <input
                                                type="search"
                                                v-model="searchTerm"
                                                class="block w-full p-2 pl-10 pr-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="Search permissions..."
                                            />
                                            <button
                                                v-if="searchTerm"
                                                @click="searchTerm = ''"
                                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700"
                                                type="button"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="Object.keys(permissions).length === 0" class="text-sm text-gray-500">
                                        No permissions available. Please create some permissions first.
                                    </div>

                                    <div v-else>
                                        <div v-for="(categoryPermissions, category) in permissions" :key="category" class="mb-6 border border-gray-200 rounded-lg overflow-hidden">
                                            <button
                                                @click.prevent="categoryExpanded[category] = !categoryExpanded[category]"
                                                class="w-full flex justify-between items-center p-3 bg-gray-50 hover:bg-gray-100 transition-colors"
                                            >
                                                <h4 class="font-medium text-gray-800">{{ category }}</h4>
                                                <span class="text-gray-500">
                                                    <span v-if="categoryExpanded[category]">▼</span>
                                                    <span v-else>▶</span>
                                                </span>
                                            </button>
                                            <div v-if="categoryExpanded[category]" class="p-3">
                                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
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
                                                        <div class="ml-3 text-sm" :class="{ 'bg-yellow-100 p-1 rounded': matchesSearch(permission) }">
                                                            <label :for="`permission-${permission.id}`" class="font-medium" :class="matchesSearch(permission) ? 'text-gray-900' : 'text-gray-700'">{{ permission.name }}</label>
                                                            <p v-if="permission.description" :class="matchesSearch(permission) ? 'text-gray-700' : 'text-gray-500'">{{ permission.description }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <InputError class="mt-2" :message="form.errors.permissions" />
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-4">
                                <Link
                                    :href="route('admin.roles.index')"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-800 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                                >
                                    Cancel
                                </Link>

                                <PrimaryButton
                                    class="ml-4"
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Update Role
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
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    role: Object,
    permissions: Object,
    rolePermissions: Array,
});

// Track expanded state for each section
const basicInfoExpanded = ref(true);
const roleTypeExpanded = ref(true);
const permissionsExpanded = ref(true);

// Track expanded state for each permission category
const categoryExpanded = ref({});

// Search functionality
const searchTerm = ref('');

// Initialize all permission categories as collapsed by default
const initializeCategoryExpanded = () => {
    if (props.permissions) {
        Object.keys(props.permissions).forEach(category => {
            categoryExpanded.value[category] = false;
        });
    }
};

// Call initialization function
initializeCategoryExpanded();

// Computed property to check if a permission matches the search term
const matchesSearch = (permission) => {
    if (!searchTerm.value) return false;
    const term = searchTerm.value.toLowerCase();
    return permission.name.toLowerCase().includes(term) ||
           (permission.description && permission.description.toLowerCase().includes(term));
};

// Computed property to get categories with matching permissions
const categoriesWithMatches = computed(() => {
    if (!searchTerm.value) return {};

    const result = {};
    Object.entries(props.permissions).forEach(([category, permissions]) => {
        const matchingPermissions = permissions.filter(permission => matchesSearch(permission));
        if (matchingPermissions.length > 0) {
            result[category] = matchingPermissions;
        }
    });

    return result;
});

// Watch for changes in search term to expand/collapse categories
watch(searchTerm, (newValue) => {
    if (!newValue) {
        // Reset to default collapsed state when search is cleared
        initializeCategoryExpanded();
        return;
    }

    // Expand categories with matching permissions
    Object.keys(categoriesWithMatches.value).forEach(category => {
        categoryExpanded.value[category] = true;
    });
});

const form = useForm({
    name: props.role.name,
    description: props.role.description || '',
    type: props.role.type || '',
    permissions: props.rolePermissions || [],
});

const submit = async () => {
    try {
        form.processing = true;

        // Use axios to directly call the API endpoint
        const response = await axios.put(`/api/roles/${props.role.id}`, {
            name: form.name,
            description: form.description,
            permissions: form.permissions,
            type: form.type || 'application' // Default to 'application' if type is not provided
        });

        // Reset form errors
        form.clearErrors();

        // Show success message or handle success case
        console.log('Role updated successfully', response.data);

        // Optionally, you can redirect to the roles index page
        // window.location.href = route('admin.roles.index');
    } catch (error) {
        // Handle validation errors
        if (error.response && error.response.status === 422) {
            form.setError('name', error.response.data.errors.name);
            form.setError('description', error.response.data.errors.description);
            form.setError('permissions', error.response.data.errors.permissions);
        } else {
            console.error('Error updating role:', error);
        }
    } finally {
        form.processing = false;
    }
};
</script>
