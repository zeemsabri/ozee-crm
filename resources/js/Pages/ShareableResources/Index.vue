<script setup>
import { ref, onMounted } from 'vue';
import CreateShareableResourceForm from '@/Components/ShareableResource/CreateForm.vue';
import EditShareableResourceForm from '@/Components/ShareableResource/EditForm.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { success, error } from '@/Utils/notification.js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Define props if any are needed for this page (e.g., initial data, permissions)
const props = defineProps({
    // Example: If you need to pass the API endpoint from a parent route/controller
    apiEndpoint: {
        type: String,
        default: '/api/shareable-resources', // Default API endpoint for resources
    },
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteConfirm = ref(false);
const resourceToEdit = ref(null);
const resourceToDelete = ref(null);
const activeTab = ref('existing'); // 'existing' or 'add-new'
const resources = ref([]); // To store the list of existing resources
const isLoadingResources = ref(false);
const fetchError = ref(null);

/**
 * Fetches existing shareable resources from the API.
 */
const fetchResources = async () => {
    isLoadingResources.value = true;
    fetchError.value = null;
    try {
        const response = await window.axios.get(props.apiEndpoint);
        resources.value = response.data.data; // Assuming API returns an array of resources
        success('Resources loaded successfully!');
    } catch (err) {
        console.error('Error fetching resources:', err);
        fetchError.value = 'Failed to load resources. Please try again.';
        error(fetchError.value);
    } finally {
        isLoadingResources.value = false;
    }
};

/**
 * Handles the event when a new resource is successfully created.
 * Refreshes the list of resources and switches to the 'existing' tab.
 */
const handleResourceCreated = (newResource) => {
    console.log('New resource created:', newResource);
    // Add the new resource to the list or re-fetch to ensure data consistency
    fetchResources();
    showCreateModal.value = false; // Close the modal
    activeTab.value = 'existing'; // Switch to existing resources tab
};

/**
 * Handles the event when a resource is successfully updated.
 * Refreshes the list of resources.
 */
const handleResourceUpdated = (updatedResource) => {
    console.log('Resource updated:', updatedResource);
    // Re-fetch to ensure data consistency
    fetchResources();
    showEditModal.value = false; // Close the modal
};

/**
 * Opens the modal for creating a new resource and sets the active tab.
 */
const openCreateModal = () => {
    showCreateModal.value = true;
    activeTab.value = 'add-new'; // Keep 'add-new' tab active visually
};

/**
 * Opens the modal for editing a resource.
 */
const openEditModal = (resource) => {
    resourceToEdit.value = resource;
    showEditModal.value = true;
};

/**
 * Opens the delete confirmation dialog.
 */
const openDeleteConfirm = (resource) => {
    resourceToDelete.value = resource;
    showDeleteConfirm.value = true;
};

/**
 * Deletes a resource.
 */
const deleteResource = async () => {
    try {
        await window.axios.delete(`${props.apiEndpoint}/${resourceToDelete.value.id}`);
        success('Resource deleted successfully!');
        fetchResources();
        showDeleteConfirm.value = false;
        resourceToDelete.value = null;
    } catch (err) {
        console.error('Error deleting resource:', err);
        error('Failed to delete resource. Please try again.');
    }
};

/**
 * Closes the create resource modal.
 */
const closeCreateModal = () => {
    showCreateModal.value = false;
    // If the modal was closed without creating, revert to existing tab
    if (activeTab.value === 'add-new') {
        activeTab.value = 'existing';
    }
};

/**
 * Closes the edit resource modal.
 */
const closeEditModal = () => {
    showEditModal.value = false;
    resourceToEdit.value = null;
};

/**
 * Closes the delete confirmation dialog.
 */
const closeDeleteConfirm = () => {
    showDeleteConfirm.value = false;
    resourceToDelete.value = null;
};

// Fetch resources when the component is mounted
onMounted(() => {
    fetchResources();
});
</script>

<template>
    <AuthenticatedLayout>
    <div class="container mx-auto p-6 bg-gray-50 min-h-screen font-inter">
        <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Shareable Resource Management</h1>

        <!-- Tab Navigation -->
        <div class="flex border-b border-gray-200 mb-6">
            <button
                @click="activeTab = 'existing'"
                :class="[
                    'py-3 px-6 text-lg font-medium transition-all duration-200',
                    activeTab === 'existing'
                        ? 'border-b-4 border-indigo-600 text-indigo-700 bg-white shadow-sm'
                        : 'text-gray-600 hover:text-gray-800 hover:border-gray-300'
                ]"
                class="rounded-t-lg focus:outline-none"
            >
                Existing Resources
            </button>
            <button
                @click="openCreateModal"
                :class="[
                    'py-3 px-6 text-lg font-medium transition-all duration-200',
                    activeTab === 'add-new'
                        ? 'border-b-4 border-indigo-600 text-indigo-700 bg-white shadow-sm'
                        : 'text-gray-600 hover:text-gray-800 hover:border-gray-300'
                ]"
                class="rounded-t-lg focus:outline-none"
            >
                Add New Resource
            </button>
        </div>

        <!-- Content Area based on Active Tab -->
        <div class="bg-white p-8 rounded-lg shadow-xl border border-gray-100">
            <div v-if="activeTab === 'existing'">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Your Shareable Resources</h2>

                <div v-if="isLoadingResources" class="text-center py-8">
                    <svg class="animate-spin mx-auto h-10 w-10 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-3 text-gray-600">Loading resources...</p>
                </div>

                <div v-else-if="fetchError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ fetchError }}</span>
                </div>

                <div v-else-if="resources.length === 0" class="text-center py-8 text-gray-600">
                    <p class="text-lg">No shareable resources found. Start by adding a new one!</p>
                    <PrimaryButton @click="openCreateModal" class="mt-4">
                        Add First Resource
                    </PrimaryButton>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visible to Client</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tags</th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Edit</span>
                            </th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="resource in resources" :key="resource.id">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ resource.title }}</div>
                                <div v-if="resource.description" class="text-sm text-gray-500 mt-1 truncate max-w-xs">{{ resource.description }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        resource.type === 'youtube' ? 'bg-red-100 text-red-800' :
                                        resource.type === 'website' ? 'bg-blue-100 text-blue-800' :
                                        resource.type === 'document' ? 'bg-green-100 text-green-800' :
                                        resource.type === 'image' ? 'bg-purple-100 text-purple-800' :
                                        'bg-gray-100 text-gray-800'
                                    ]">
                                        {{ resource.type }}
                                    </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-indigo-600 hover:text-indigo-900">
                                <a :href="resource.url" target="_blank" rel="noopener noreferrer" class="truncate max-w-xs block">
                                    {{ resource.url }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        resource.visible_to_client ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]">
                                        {{ resource.visible_to_client ? 'Yes' : 'No' }}
                                    </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex flex-wrap gap-1">
                                        <span v-for="tag in resource.tags" :key="tag.id" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ tag.name }}
                                        </span>
                                    <span v-if="!resource.tags || resource.tags.length === 0" class="text-gray-400">No tags</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-3">
                                    <button @click="openEditModal(resource)" class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                    <button @click="openDeleteConfirm(resource)" class="text-red-600 hover:text-red-900">Delete</button>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- The 'Add New Resource' content is handled by the modal, not directly in this tab content -->
        </div>

        <!-- Create Resource Modal (always rendered, but shown/hidden via 'showCreateModal') -->
        <CreateShareableResourceForm
            :show="showCreateModal"
            :apiEndpoint="props.apiEndpoint"
            @close="closeCreateModal"
            @resourceCreated="handleResourceCreated"
        />

        <!-- Edit Resource Modal (shown when editing a resource) -->
        <EditShareableResourceForm
            v-show="resourceToEdit"
            :show="showEditModal"
            :apiEndpoint="props.apiEndpoint"
            :resource="resourceToEdit || {}"
            @close="closeEditModal"
            @resourceUpdated="handleResourceUpdated"
        />

        <!-- Delete Confirmation Dialog -->
        <div v-if="showDeleteConfirm" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Confirm Delete</h3>
                <p class="text-gray-600 mb-6">
                    Are you sure you want to delete the resource "{{ resourceToDelete?.title }}"? This action cannot be undone.
                </p>
                <div class="flex justify-end space-x-3">
                    <button
                        @click="closeDeleteConfirm"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteResource"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
    </AuthenticatedLayout>
</template>

<style>
.font-inter {
    font-family: 'Inter', sans-serif;
}
</style>
