<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ResourceModal from '@/Components/ResourceModal.vue';
import { usePermissions } from '@/Directives/permissions';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    projectId: {
        type: Number,
        required: true,
    },
    canManageProjects: {
        type: Boolean,
        required: true,
    },
    isSuperAdmin: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits([
    'openEditModal',
    'openStandupModal',
    'openMeetingModal',
    'resourceSaved',
]);

// Resource Modal State
const showResourceModal = ref(false);
const selectedResource = ref(null);
const resources = ref([]);
const loadingResources = ref(false);
const activeTooltipId = ref(null);

// Magic Link Modal State
const showMagicLinkModal = ref(false);
const sendingMagicLink = ref(false);
const magicLinkForm = ref({
    email: '',
});
const magicLinkErrors = ref({});
const magicLinkSuccess = ref('');

// Set up permission checking functions
const { canDo } = usePermissions(props.projectId);

// Fetch resources for the project
const fetchResources = async () => {
    loadingResources.value = true;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/resources`);
        if (response.data.success) {
            resources.value = response.data.resources;
        } else {
            console.error('Failed to fetch resources:', response.data.message);
        }
    } catch (error) {
        console.error('Error fetching resources:', error);
    } finally {
        loadingResources.value = false;
    }
};

// Open the resource modal for adding a new resource
const openAddResourceModal = () => {
    selectedResource.value = null; // Clear selected resource for adding new
    showResourceModal.value = true;
};

// Open the resource modal for editing an existing resource
const editResource = (resource) => {
    selectedResource.value = resource;
    showResourceModal.value = true;
};

// Delete a resource
const deleteResource = async (resourceId) => {
    if (!confirm('Are you sure you want to delete this resource?')) {
        return;
    }

    try {
        const response = await window.axios.delete(`/api/projects/${props.projectId}/resources/${resourceId}`);

        if (response.data.success) {
            resources.value = resources.value.filter(r => r.id !== resourceId);
        } else {
            console.error('Failed to delete resource:', response.data.message);
        }
    } catch (error) {
        console.error('Error deleting resource:', error);
    }
};

// Handle resource saved event from the modal
const handleResourceSaved = (resource) => {
    if (selectedResource.value) {
        // If editing, update in list
        const index = resources.value.findIndex(r => r.id === resource.id);
        if (index !== -1) {
            resources.value[index] = resource;
        }
    } else {
        // If adding, add to list
        resources.value.push(resource);
    }
    emit('resourceSaved', resource); // Emit to parent if parent needs to know
};

// Toggle tooltip visibility
const toggleTooltip = (resourceId) => {
    activeTooltipId.value = activeTooltipId.value === resourceId ? null : resourceId;
};

// Close tooltip
const closeTooltip = () => {
    activeTooltipId.value = null;
};

// Close tooltip when clicking outside
const handleClickOutside = (event) => {
    if (activeTooltipId.value !== null) {
        const isTooltipClick = event.target.closest('.resource-tooltip');
        const isResourceButtonClick = event.target.closest('.resource-button');
        if (!isTooltipClick && !isResourceButtonClick) {
            closeTooltip();
        }
    }
};

onMounted(() => {
    fetchResources();
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Magic Link Modal Methods
const openMagicLinkModal = () => {
    // Reset form and errors
    magicLinkForm.value.email = '';
    magicLinkErrors.value = {};
    magicLinkSuccess.value = '';
    showMagicLinkModal.value = true;
};

const closeMagicLinkModal = () => {
    showMagicLinkModal.value = false;
};

const sendMagicLink = async () => {
    // Reset errors and success message
    magicLinkErrors.value = {};
    magicLinkSuccess.value = '';

    // Set loading state
    sendingMagicLink.value = true;

    try {
        // Send request to the API
        const response = await window.axios.post(`/api/projects/${props.projectId}/magic-link`, {
            email: magicLinkForm.value.email
        });

        // Handle success
        if (response.data.success) {
            magicLinkSuccess.value = response.data.message;
            // Clear the form
            magicLinkForm.value.email = '';
        }
    } catch (error) {
        // Handle validation errors
        if (error.response && error.response.status === 422) {
            magicLinkErrors.value = error.response.data.errors;
        }
        // Handle other errors
        else if (error.response && error.response.data.message) {
            magicLinkErrors.value = { general: [error.response.data.message] };
        } else {
            magicLinkErrors.value = { general: ['An unexpected error occurred. Please try again.'] };
        }
    } finally {
        // Reset loading state
        sendingMagicLink.value = false;
    }
};
</script>

<template>
    <div class="bg-white p-6 rounded-xl shadow-md hover:shadow-lg transition-shadow mb-6">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-gray-900">General Information</h4>
            <div class="flex gap-3">
                <PrimaryButton class="bg-blue-600 hover:bg-blue-700 transition-colors" @click="emit('openStandupModal')">
                    Daily Standup
                </PrimaryButton>
                <PrimaryButton v-if="canManageProjects || isSuperAdmin" class="bg-indigo-600 hover:bg-indigo-700 transition-colors" @click="emit('openEditModal')">
                    Edit Project
                </PrimaryButton>
                <PrimaryButton v-if="canManageProjects || isSuperAdmin" class="bg-green-600 hover:bg-green-700 transition-colors" @click="emit('openMeetingModal')">
                    Schedule Meeting
                </PrimaryButton>
                <!-- Magic Link button remains commented out as in original for consistency -->

                <PrimaryButton
                    v-if="canManageProjects "
                    class="bg-purple-600 hover:bg-purple-700 transition-colors"
                    @click="openMagicLinkModal"
                    :disabled="sendingMagicLink"
                >
                    {{ sendingMagicLink ? 'Sending...' : 'Send Magic Link' }}
                </PrimaryButton>

            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-2xl font-bold text-gray-900 tracking-tight mb-2">{{ project.name }}</h3>
                <p class="text-gray-600 text-base">{{ project.description || 'No description provided' }}</p>
            </div>
            <div class="space-y-3 text-sm text-gray-700">
                <p><strong class="text-gray-900">Status:</strong> {{ project.status?.replace('_', ' ').toUpperCase() || 'N/A' }}</p>
                <p><strong class="text-gray-900">Project Type:</strong> {{ project.project_type || 'N/A' }}</p>
                <p><strong class="text-gray-900">Source:</strong> {{ project.source || 'N/A' }}</p>
                <!-- Links and Resources -->
                <div class="flex flex-wrap gap-2 mt-2 items-center">
                    <!-- Add Resource Button -->
                    <button
                        @click="openAddResourceModal"
                        class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors"
                        title="Add Resource"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>

                    <!-- Website Link -->
                    <a v-if="project.website" :href="project.website" target="_blank"
                       class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors"
                       title="Visit Website">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </a>

                    <!-- Social Media Link -->
                    <a v-if="project.social_media_link" :href="project.social_media_link" target="_blank"
                       class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors"
                       title="Social Media">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                    </a>

                    <!-- Google Drive Link -->
                    <a v-if="project.google_drive_link" :href="project.google_drive_link" target="_blank"
                       class="p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors"
                       title="Google Drive">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </a>

                    <!-- Loading Resources -->
                    <div v-if="loadingResources" class="text-gray-500 text-sm">
                        Loading resources...
                    </div>

                    <!-- Dynamic Resources -->
                    <template v-else>
                        <div v-for="resource in resources" :key="resource.id" class="relative">
                            <div class="relative">
                                <!-- Resource Link with Icon -->
                                <button @click.prevent="toggleTooltip(resource.id)"
                                        class="resource-button p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors block">
                                    <!-- Icon based on resource type -->
                                    <svg v-if="resource.type === 'link'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.102 1.101" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </button>

                                <!-- Tooltip with resource info (visible when active) -->
                                <div v-if="activeTooltipId === resource.id" class="resource-tooltip absolute left-0 bottom-full mb-2 w-48 z-10">
                                    <div class="bg-white rounded-md shadow-lg p-3 text-sm border border-gray-200">
                                        <div class="flex justify-between items-center mb-2">
                                            <h4 class="font-medium text-gray-900">{{ resource.name }}</h4>
                                            <button @click.prevent="closeTooltip" class="text-gray-400 hover:text-gray-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        <p v-if="resource.description" class="text-gray-600 text-xs mb-2">{{ resource.description }}</p>

                                        <div class="flex justify-between mt-2 pt-2 border-t border-gray-100">
                                            <a :href="resource.url" target="_blank"
                                               class="text-xs text-indigo-600 hover:text-indigo-800">
                                                Open Link
                                            </a>
                                            <div class="flex space-x-2">
                                                <button @click.prevent="editResource(resource)"
                                                        class="p-1 text-gray-500 hover:text-indigo-600"
                                                        title="Edit">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button @click.prevent="deleteResource(resource.id)"
                                                        class="p-1 text-gray-500 hover:text-red-600"
                                                        title="Delete">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="absolute left-5 bottom-0 transform translate-y-1/2 rotate-45 w-2 h-2 bg-white border-r border-b border-gray-200"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
    <ResourceModal
        :show="showResourceModal"
        :project-id="projectId"
        :resource="selectedResource"
        @close="showResourceModal = false"
        @saved="handleResourceSaved"
    />

    <!-- Magic Link Modal -->
    <Modal :show="showMagicLinkModal" @close="closeMagicLinkModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">
                Send Magic Link
            </h2>

            <!-- Success Message -->
            <div v-if="magicLinkSuccess" class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-md">
                {{ magicLinkSuccess }}
            </div>

            <!-- General Error Message -->
            <div v-if="magicLinkErrors.general" class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-md">
                {{ magicLinkErrors.general[0] }}
            </div>

            <form @submit.prevent="sendMagicLink">
                <div class="mb-4">
                    <InputLabel for="email" value="Client Email" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="magicLinkForm.email"
                        required
                        autofocus
                        placeholder="Enter client email"
                    />
                    <InputError :message="magicLinkErrors.email ? magicLinkErrors.email[0] : ''" class="mt-2" />
                    <p class="mt-2 text-sm text-gray-500">
                        The email must belong to a client associated with this project.
                    </p>
                </div>

                <div class="flex justify-end mt-6">
                    <SecondaryButton @click="closeMagicLinkModal" class="mr-3">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        class="bg-purple-600 hover:bg-purple-700 transition-colors"
                        :disabled="sendingMagicLink"
                    >
                        {{ sendingMagicLink ? 'Sending...' : 'Send Magic Link' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>

<style scoped>
/* Resource Tooltip specific styles */
.resource-tooltip {
    /* Using transform to position the tooltip accurately relative to its button */
    transform: translateX(-50%); /* Center horizontally above the button */
    left: 50%; /* Start at the center of the button */
}
</style>
