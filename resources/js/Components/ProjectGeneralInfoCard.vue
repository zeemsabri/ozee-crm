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
import CustomMultiSelect from '@/Components/CustomMultiSelect.vue';
import ComposeEmailModal from './ComposeEmailModal.vue';
import {
    PencilSquareIcon,
    PlusIcon,
    CalendarDaysIcon,
    PaperAirplaneIcon,
    LinkIcon,
    FolderOpenIcon,
    ShareIcon,
    ChatBubbleLeftRightIcon,
    GlobeAltIcon,
    CubeTransparentIcon,
    SparklesIcon
} from '@heroicons/vue/24/outline';
import {
    PencilIcon,
    TrashIcon
} from '@heroicons/vue/20/solid';

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
    canEditProjects: {
        type: Boolean,
        required: false
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
    'openComposeModal'
]);

// Resource Modal State
const showResourceModal = ref(false);
const selectedResource = ref(null);
const resources = ref([]);
const loadingResources = ref(false);
const activeTooltipId = ref(null);

// Compose Email Modal State
const showComposeEmailModal = ref(false);

// Magic Link Modal State
const showMagicLinkModal = ref(false);
const sendingMagicLink = ref(false);
const magicLinkForm = ref({
    client_ids: [],
});
const magicLinkErrors = ref({});
const magicLinkSuccess = ref('');

// Client data for the MultiSelect
const projectClients = ref([]);
const loadingClients = ref(false);
const clientsError = ref('');

// Meetings State
const meetings = ref([]);
const loadingMeetings = ref(false);
const userTimezone = ref('');

// Set up permission checking functions
const { canDo } = usePermissions(props.projectId);

const detectUserTimezone = () => {
    try {
        userTimezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (error) {
        console.error('Error detecting user timezone:', error);
        userTimezone.value = 'UTC';
    }
};

const fetchMeetings = async () => {
    loadingMeetings.value = true;
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/meetings`);
        meetings.value = response.data;
    } catch (error) {
        console.error('Error fetching project meetings:', error);
    } finally {
        loadingMeetings.value = false;
    }
};

const formatMeetingTime = (timeString) => {
    if (!timeString) return '';

    try {
        const options = {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: userTimezone.value
        };
        return new Date(timeString).toLocaleString(undefined, options);
    } catch (error) {
        console.error('Error formatting meeting time:', error);
        return new Date(timeString).toLocaleString();
    }
};

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
    detectUserTimezone();
    fetchMeetings();
    document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Fetch clients for the project
const fetchClients = async () => {
    loadingClients.value = true;
    clientsError.value = '';
    try {
        const response = await window.axios.get(`/api/projects/${props.projectId}/sections/clients?type=clients`);
        projectClients.value = response.data;
    } catch (error) {
        console.error('Failed to fetch project clients:', error);
        clientsError.value = error.response?.data?.message || 'Failed to load client data.';
    } finally {
        loadingClients.value = false;
    }
};

// Magic Link Modal Methods
const openMagicLinkModal = () => {
    // Reset form and errors
    magicLinkForm.value.client_ids = [];
    magicLinkErrors.value = {};
    magicLinkSuccess.value = '';
    showMagicLinkModal.value = true;

    // Fetch clients when the modal is opened
    fetchClients();
};

const handOpenCompose = () => {
    showComposeEmailModal.value = true
    fetchClients();
}


const closeMagicLinkModal = () => {
    showMagicLinkModal.value = false;
};

const sendMagicLink = async () => {
    // Reset errors and success message
    magicLinkErrors.value = {};
    magicLinkSuccess.value = '';

    // Set loading state
    sendingMagicLink.value = true;

    // Validate that a client is selected
    if (!magicLinkForm.value.client_ids || magicLinkForm.value.client_ids.length === 0) {
        magicLinkErrors.value = { client_ids: ['Please select a client.'] };
        sendingMagicLink.value = false;
        return;
    }

    try {
        // Get the selected client
        const selectedClientId = magicLinkForm.value.client_ids[0]; // Get the first selected client
        const selectedClient = projectClients.value.find(client => client.id === selectedClientId);

        if (!selectedClient) {
            magicLinkErrors.value = { client_ids: ['Selected client does not have a valid email.'] };
            sendingMagicLink.value = false;
            return;
        }

        // Send request to the API
        const response = await window.axios.post(`/api/projects/${props.projectId}/magic-link`, {
            client_id: selectedClientId
        });

        // Handle success
        if (response.data.success) {
            magicLinkSuccess.value = response.data.message;
            // Clear the form
            magicLinkForm.value.client_ids = [];
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
    <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200">
        <div class="flex flex-col lg:flex-row justify-between lg:items-center mb-6 border-b pb-4 border-gray-200">
            <div class="flex-1">
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                    {{ project.name }}
                </h2>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-500">
                    <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold"
                          :class="{
                            'bg-green-100 text-green-800': project.status === 'active',
                            'bg-yellow-100 text-yellow-800': project.status === 'on_hold',
                            'bg-blue-100 text-blue-800': project.status === 'completed',
                            'bg-red-100 text-red-800': project.status === 'archived'
                        }"
                    >
                        {{ project.status?.replace('_', ' ').toUpperCase() || 'N/A' }}
                    </span>
                    <span class="text-gray-600">|</span>
                    <span class="text-gray-600 font-medium">{{ project.project_type || 'N/A' }}</span>
                    <span class="text-gray-600">|</span>
                    <div class="flex items-center gap-2">
                        <a v-if="project.website" :href="project.website" target="_blank"
                           class="flex items-center text-sm text-indigo-600 hover:text-indigo-800 transition-colors"
                           title="Visit Website">
                            <GlobeAltIcon class="h-5 w-5 mr-1" />
                            Website
                        </a>
                        <a v-if="project.social_media_link" :href="project.social_media_link" target="_blank"
                           class="flex items-center text-sm text-indigo-600 hover:text-indigo-800 transition-colors"
                           title="Social Media">
                            <SparklesIcon class="h-5 w-5 mr-1" />
                            Socials
                        </a>
                        <a v-if="project.google_drive_link" :href="project.google_drive_link" target="_blank"
                           class="flex items-center text-sm text-indigo-600 hover:text-indigo-800 transition-colors"
                           title="Google Drive">
                            <CubeTransparentIcon class="h-5 w-5 mr-1" />
                            Drive
                        </a>
                    </div>
                </div>
            </div>
            <div class="mt-4 lg:mt-0 flex flex-wrap gap-2">
                <PrimaryButton v-if="canEditProjects || isSuperAdmin" @click="$inertia.visit('/projects/' + projectId + '/edit')" class="bg-indigo-600 hover:bg-indigo-700 transition-colors">
                    <PencilSquareIcon class="h-5 w-5 mr-2" />
                    Edit Project
                </PrimaryButton>
                <SecondaryButton v-if="canManageProjects || isSuperAdmin" @click="emit('openMeetingModal')" class="bg-white border-gray-300 text-gray-700 hover:bg-gray-50">
                    <CalendarDaysIcon class="h-5 w-5 mr-2" />
                    Schedule Meeting
                </SecondaryButton>
                <SecondaryButton @click="emit('openStandupModal')" class="bg-white border-gray-300 text-gray-700 hover:bg-gray-50">
                    <ChatBubbleLeftRightIcon class="h-5 w-5 mr-2" />
                    Daily Standup
                </SecondaryButton>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div class="col-span-1 md:col-span-2">
                <p class="text-gray-600 text-base leading-relaxed">{{ project.description || 'No description provided.' }}</p>

                <div class="mt-6 flex flex-wrap gap-2 items-center">
                    <button @click="openAddResourceModal" class="flex items-center p-2 rounded-full text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition-colors" title="Add Resource">
                        <PlusIcon class="h-5 w-5" />
                        <span class="ml-1 text-sm font-medium">Add Resource</span>
                    </button>
                    <button @click="openMagicLinkModal" class="flex items-center p-2 rounded-full text-green-600 bg-green-50 hover:bg-green-100 transition-colors" title="Share Project">
                        <ShareIcon class="h-5 w-5" />
                        <span class="ml-1 text-sm font-medium">Share</span>
                    </button>
                    <button @click="emit('openComposeModal')" class="flex items-center p-2 rounded-full text-blue-600 bg-blue-50 hover:bg-blue-100 transition-colors" title="Compose Email">
                        <PaperAirplaneIcon class="h-5 w-5" />
                        <span class="ml-1 text-sm font-medium">Compose Email</span>
                    </button>
                </div>

                <div class="mt-6">
                    <h5 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Dynamic Project Resources</h5>
                    <div v-if="loadingResources" class="text-gray-500 text-sm">Loading resources...</div>
                    <div v-else-if="resources.length === 0" class="text-gray-400 text-sm">No dynamic resources have been added.</div>
                    <div v-else class="flex flex-wrap gap-3 items-center">
                        <div v-for="resource in resources" :key="resource.id" class="relative">
                            <button @click.prevent="toggleTooltip(resource.id)" class="resource-button flex items-center p-2 bg-gray-100 rounded-full hover:bg-gray-200 transition-colors text-sm font-medium text-gray-700">
                                <LinkIcon class="h-4 w-4 mr-1" />
                                {{ resource.name }}
                            </button>
                            <div v-if="activeTooltipId === resource.id" class="resource-tooltip absolute left-1/2 bottom-full mb-2 w-64 z-10 -translate-x-1/2">
                                <div class="bg-white rounded-xl shadow-xl p-4 text-sm border border-gray-200">
                                    <div class="flex justify-between items-center mb-2">
                                        <h4 class="font-bold text-gray-900">{{ resource.name }}</h4>
                                        <button @click.prevent="closeTooltip" class="text-gray-400 hover:text-gray-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p v-if="resource.description" class="text-gray-600 text-xs mb-2">{{ resource.description }}</p>
                                    <a :href="resource.url" target="_blank" class="mt-2 text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center">
                                        <FolderOpenIcon class="h-4 w-4 mr-1" />
                                        Open Resource
                                    </a>
                                    <div class="flex justify-end space-x-2 mt-2 pt-2 border-t border-gray-100">
                                        <button @click.prevent="editResource(resource)" class="p-1 text-gray-500 hover:text-indigo-600 transition-colors" title="Edit">
                                            <PencilIcon class="h-4 w-4" />
                                        </button>
                                        <button @click.prevent="deleteResource(resource.id)" class="p-1 text-gray-500 hover:text-red-600 transition-colors" title="Delete">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <div class="absolute left-1/2 bottom-0 transform translate-y-1/2 -translate-x-1/2 rotate-45 w-4 h-4 bg-white border-r border-b border-gray-200"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-1 border-l border-gray-200 pl-8">
                <h5 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Upcoming Meetings</h5>
                <div v-if="loadingMeetings" class="text-gray-500 text-sm">
                    Loading meetings...
                </div>
                <div v-else-if="meetings.length === 0" class="text-gray-500 text-sm">
                    No upcoming meetings scheduled.
                </div>
                <div v-else class="space-y-3 max-h-[300px] overflow-y-auto">
                    <div v-for="meeting in meetings" :key="meeting.id"
                         class="p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">
                        <h5 class="font-medium text-gray-900 truncate">{{ meeting.summary }}</h5>
                        <p class="text-sm text-gray-600">
                            {{ formatMeetingTime(meeting.start_time) }}
                        </p>
                        <div class="flex space-x-2 mt-2">
                            <a :href="meeting.google_event_link" target="_blank"
                               class="text-blue-600 hover:text-blue-800" title="View in Google Calendar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </a>
                            <a v-if="meeting.google_meet_link" :href="meeting.google_meet_link" target="_blank"
                               class="text-green-600 hover:text-green-800" title="Join Google Meet">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </a>
                        </div>
                    </div>
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
    <Modal :show="showMagicLinkModal" @close="closeMagicLinkModal">
        <div class="p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
                Send Magic Link
            </h2>
            <div v-if="magicLinkSuccess" class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-md">
                {{ magicLinkSuccess }}
            </div>
            <div v-if="magicLinkErrors.general" class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-md">
                {{ magicLinkErrors.general[0] }}
            </div>
            <form @submit.prevent="sendMagicLink">
                <div class="mb-4">
                    <InputLabel for="client" value="Select Client" />
                    <div v-if="loadingClients" class="text-gray-500 text-sm mt-1">Loading clients...</div>
                    <div v-else-if="clientsError" class="text-red-500 text-sm mt-1">{{ clientsError }}</div>
                    <CustomMultiSelect
                        v-else
                        id="client"
                        v-model="magicLinkForm.client_ids"
                        :options="projectClients"
                        placeholder="Select a client"
                        label-key="name"
                        track-by="id"
                        class="mt-1"
                    />
                    <InputError :message="magicLinkErrors.client_ids ? magicLinkErrors.client_ids[0] : ''" class="mt-2" />
                    <p class="mt-2 text-sm text-gray-500">
                        Select a client to send them a magic link for project access.
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
    <ComposeEmailModal
        :show="showComposeEmailModal"
        :title="'Compose New Email'"
        :api-endpoint="'/api/emails/templated'"
        :http-method="'post'"
        :clients="projectClients"
        :submit-button-text="'Submit for Approval'"
        :success-message="'Email submitted for approval successfully!'"
        :project-id="projectId"
        @close="showComposeEmailModal = false"
        @submitted="showComposeEmailModal = false"
        @error="(error) => console.error('Email submission error:', error)"
    />
</template>

<style scoped>
/* Resource Tooltip specific styles */
.resource-tooltip {
    left: 50%;
    transform: translateX(-50%);
}
</style>
