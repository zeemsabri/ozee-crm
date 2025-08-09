<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TagInput from '@/Components/TagInput.vue';
import { success, error } from '@/Utils/notification';
import TimezoneSelect from "@/Components/TimezoneSelect.vue";
import ProjectTypeInput from "@/Components/ProjectTypeInput.vue";

const props = defineProps({
    statusOptions: {
        type: Array,
        required: true,
        default: () => [
            { value: 'active', label: 'Active' },
            { value: 'completed', label: 'Completed' },
            { value: 'paid', label: 'Paid' },
            { value: 'on-hold', label: 'On-hold' },
        ]
    },
    sourceOptions: {
        type: Array,
        required: true
    },
    errors: { // Inertia errors from the controller
        type: Object,
        default: () => ({})
    },
    canManageProjects: { // Global permission to create/manage projects
        type: Boolean,
        default: false
    },
});

const emit = defineEmits(['projectCreated']); // Emit event on successful project creation

// Local reactive state for the new project form
const newProjectForm = reactive({
    name: '',
    description: '',
    website: '',
    social_media_link: '',
    preferred_keywords: '',
    google_chat_id: '',
    google_drive_link: '',
    logo: null,
    status: 'active', // Default status for new projects
    project_type: '',
    source: '',
    tags: [],
    tags_data: [], // For initial display of tags if needed (not for new projects)
    timezone: null,
    project_tier_id: null,
    profit_margin_percentage: null
});

const isCreating = ref(false); // Local saving state for this component
const projectTiers = ref([]); // Store project tiers fetched from API

/**
 * Fetches project tiers from the API
 */
const fetchProjectTiers = async () => {
    try {
        const response = await window.axios.get('/api/project-tiers');
        projectTiers.value = response.data;
    } catch (err) {
        console.error('Error fetching project tiers:', err);
        error('Failed to load project tiers.');
    }
};

/**
 * Handles the change event for the logo file input.
 * Stores the selected file object in the form data.
 * @param {Event} event - The change event from the file input.
 */
const handleLogoChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        newProjectForm.logo = file;
    } else {
        newProjectForm.logo = null;
    }
};

/**
 * Submits the new project basic information to the backend.
 * Uses a POST request as it's for creation.
 */
const submitBasicInfo = async () => {
    isCreating.value = true; // Start local saving indicator

    // Create FormData object for multipart/form-data submission (required for file uploads)
    const formData = new FormData();
    for (const key in newProjectForm) {
        // Append all fields except 'tags_data' which is for display
        if (key !== 'tags_data' && newProjectForm[key] !== null && newProjectForm[key] !== undefined) {
            if (Array.isArray(newProjectForm[key])) {
                // For arrays like 'tags', append each item
                newProjectForm[key].forEach((item, index) => {
                    if (typeof item === 'object' && item !== null) {
                        // If tags are objects (e.g., {id, name}), stringify them
                        formData.append(`${key}[${index}]`, JSON.stringify(item));
                    } else {
                        formData.append(`${key}[]`, item);
                    }
                });
            } else {
                formData.append(key, newProjectForm[key]);
            }
        }
    }

    try {
        // Make the POST request to create a new project
        const response = await window.axios.post('/api/projects', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        success('Project created successfully!');

        // If a new project ID is returned, redirect to its edit page
        if (response.data && response.data.id) {
            // Emit event to parent (Create.vue page) if needed, though router.visit handles navigation
            emit('projectCreated', response.data.id);
            router.visit(route('projects.edit', { project: response.data.id }));
        }

    } catch (err) {
        console.error('Error creating project:', err);
        let errorMessage = 'Failed to create project. Please check the form and try again.';
        if (err.response && err.response.data && err.response.data.message) {
            errorMessage = err.response.data.message;
        }
        error(errorMessage); // Display error notification
    } finally {
        isCreating.value = false; // End local saving indicator
    }
};

// Fetch project tiers when component is mounted
onMounted(() => {
    fetchProjectTiers();
});
</script>

<template>
    <div class="space-y-6 bg-white p-6 rounded-lg shadow-md border border-gray-100 font-inter">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Create New Project</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Project Name -->
            <div>
                <InputLabel for="name" value="Project Name" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="newProjectForm.name"
                    required
                    autofocus
                    :disabled="!canManageProjects || isCreating"
                />
                <InputError :message="props.errors.name ? props.errors.name[0] : ''" class="mt-2" />
            </div>

            <!-- Website -->
            <div>
                <InputLabel for="website" value="Website" />
                <TextInput
                    id="website"
                    type="url"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="newProjectForm.website"
                    :disabled="!canManageProjects || isCreating"
                />
                <InputError :message="props.errors.website ? props.errors.website[0] : ''" class="mt-2" />
            </div>

            <!-- Social Media Link -->
            <div>
                <InputLabel for="social_media_link" value="Social Media Link" />
                <TextInput
                    id="social_media_link"
                    type="url"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="newProjectForm.social_media_link"
                    :disabled="!canManageProjects || isCreating"
                />
                <InputError :message="props.errors.social_media_link ? props.errors.social_media_link[0] : ''" class="mt-2" />
            </div>

            <!-- Google Chat ID -->
            <div>
                <InputLabel for="google_chat_id" value="Google Chat ID" />
                <TextInput
                    id="google_chat_id"
                    type="text"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="newProjectForm.google_chat_id"
                    :disabled="!canManageProjects || isCreating"
                />
                <InputError :message="props.errors.google_chat_id ? props.errors.google_chat_id[0] : ''" class="mt-2" />
            </div>

            <!-- Google Drive Link -->
            <div>
                <InputLabel for="google_drive_link" value="Google Drive Link" />
                <TextInput
                    id="google_drive_link"
                    type="text"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="newProjectForm.google_drive_link"
                    :disabled="!canManageProjects || isCreating"
                />
                <InputError :message="props.errors.google_drive_link ? props.errors.google_drive_link[0] : ''" class="mt-2" />
            </div>

            <!-- Status -->
            <div>
                <InputLabel for="status" value="Status" />
                <SelectDropdown
                    id="status"
                    v-model="newProjectForm.status"
                    :options="statusOptions"
                    valueKey="value"
                    labelKey="label"
                    placeholder="Select Project Status"
                    :disabled="!canManageProjects || isCreating"
                    :required="true"
                    class="mt-1 block w-full"
                />
                <InputError :message="props.errors.status ? props.errors.status[0] : ''" class="mt-2" />
            </div>

            <!-- Project Type Dropdown -->
            <div>
                <ProjectTypeInput
                    id="project_type"
                    v-model="newProjectForm.project_type"
                    :disabled="!canManageProjects || isCreating"
                    class="mt-1 block w-full"
                />
                <InputError :message="props.errors.project_type ? props.errors.project_type[0] : ''" class="mt-2" />
            </div>

            <!-- Source -->
            <div>
                <InputLabel for="source" value="Source" />
                <SelectDropdown
                    id="source"
                    v-model="newProjectForm.source"
                    :options="sourceOptions"
                    valueKey="value"
                    labelKey="label"
                    placeholder="Select a Source"
                    :disabled="!canManageProjects || isCreating"
                    class="mt-1 block w-full"
                />
                <InputError :message="props.errors.source ? props.errors.source[0] : ''" class="mt-2" />
            </div>

            <!-- Timezone -->
            <div>
                <InputLabel for="timezone" value="Timezone" />
                <TimezoneSelect
                    id="timezone"
                    v-model="newProjectForm.timezone"
                    :disabled="!canManageProjects || isCreating"
                    class="mt-1 block w-full"
                />
                <InputError :message="props.errors.timezone ? props.errors.timezone[0] : ''" class="mt-2" />
            </div>

            <!-- Project Tier -->
            <div>
                <InputLabel for="project_tier_id" value="Project Tier" />
                <SelectDropdown
                    id="project_tier_id"
                    v-model="newProjectForm.project_tier_id"
                    :options="projectTiers"
                    valueKey="id"
                    labelKey="name"
                    placeholder="Select Project Tier"
                    :disabled="!canManageProjects || isCreating"
                    class="mt-1 block w-full"
                />
                <InputError :message="props.errors.project_tier_id ? props.errors.project_tier_id[0] : ''" class="mt-2" />
            </div>

            <!-- Profit Margin Percentage -->
            <div>
                <InputLabel for="profit_margin_percentage" value="Profit Margin (%)" />
                <TextInput
                    id="profit_margin_percentage"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="newProjectForm.profit_margin_percentage"
                    :disabled="!canManageProjects || isCreating"
                />
                <InputError :message="props.errors.profit_margin_percentage ? props.errors.profit_margin_percentage[0] : ''" class="mt-2" />
            </div>

            <!-- Project Logo -->
            <div>
                <InputLabel for="logo" value="Project Logo" />
                <input
                    type="file"
                    id="logo"
                    @change="handleLogoChange"
                    class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                    accept="image/*"
                    :disabled="!canManageProjects || isCreating"
                />
                <InputError :message="props.errors.logo ? props.errors.logo[0] : ''" class="mt-2" />
            </div>
        </div>

        <!-- Description (full width) -->
        <div class="mt-6">
            <InputLabel for="description" value="Description" />
            <textarea
                id="description"
                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full"
                v-model="newProjectForm.description"
                :disabled="!canManageProjects || isCreating"
                rows="4"
            ></textarea>
            <InputError :message="props.errors.description ? props.errors.description[0] : ''" class="mt-2" />
        </div>

        <!-- Client Preferred Keywords (full width) -->
        <div class="mt-6">
            <InputLabel for="preferred_keywords" value="Client Preferred Keywords" />
            <TextInput
                id="preferred_keywords"
                type="text"
                class="mt-1 block w-full rounded-lg shadow-sm"
                v-model="newProjectForm.preferred_keywords"
                :disabled="!canManageProjects || isCreating"
            />
            <InputError :message="props.errors.preferred_keywords ? props.errors.preferred_keywords[0] : ''" class="mt-2" />
        </div>

        <!-- Tags Input Field (full width) -->
        <div class="mt-6">
            <TagInput
                v-model="newProjectForm.tags"
                :initialTags="newProjectForm.tags_data"
                label="Associated Tags"
                placeholder="Search or add tags"
                :error="props.errors.tags ? props.errors.tags[0] : ''"
                :disabled="!canManageProjects || isCreating"
            />
        </div>

        <div class="mt-6 flex justify-end">
            <PrimaryButton
                @click="submitBasicInfo"
                :disabled="!canManageProjects || isCreating"
                :class="{ 'opacity-50 cursor-not-allowed': isCreating }"
                class="px-6 py-2 rounded-lg text-base shadow-md hover:shadow-lg transition-all"
            >
                <span v-if="isCreating" class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Creating...
                </span>
                <span v-else>
                    Create Project
                </span>
            </PrimaryButton>
        </div>
    </div>
</template>

<style>
.font-inter {
    font-family: 'Inter', sans-serif;
}
</style>
