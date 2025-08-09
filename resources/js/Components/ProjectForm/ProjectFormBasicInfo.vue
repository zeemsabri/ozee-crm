<script setup>
import { computed, watch, ref, onMounted, reactive } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TagInput from '@/Components/TagInput.vue';
import { success, error } from '@/Utils/notification';
import TimezoneSelect from "@/Components/TimezoneSelect.vue";
import ProjectTypeInput from "@/Components/ProjectTypeInput.vue";
import { fetchProjectSectionData } from '@/Components/ProjectForm/useProjectData'; // Import the data fetching utility

const props = defineProps({
    projectId: { // Now directly accepts projectId from parent
        type: [Number, String],
        required: true
    },
    errors: {
        type: Object,
        default: () => ({})
    },
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
    canManageProjects: { // Global permission
        type: Boolean,
        default: false
    },
    canManageProjectBasicDetails: { // Project-specific permission
        type: Boolean,
        default: false
    },
    isSaving: { // Overall page saving state (for disabling inputs)
        type: Boolean,
        default: false
    }
});

// Local reactive state for the project form data, initialized with defaults
const localProjectForm = reactive({
    id: null,
    name: '',
    description: '',
    website: '',
    social_media_link: '',
    preferred_keywords: '',
    google_chat_id: '',
    google_drive_link: '',
    logo: null,
    status: 'active',
    project_type: '',
    source: '',
    tags: [],
    tags_data: [],
    timezone: null,
    project_tier_id: null,
    profit_margin_percentage: null
});

const isSavingLocal = ref(false); // Local saving state for this component's submit button
const isLoadingLocal = ref(true); // Local loading state for this component's data fetch
const projectTiers = ref([]); // Store project tiers fetched from API

/**
 * Handles the change event of the file input for the logo.
 * Stores the selected file object in the local form data.
 * @param {Event} event - The change event from the file input.
 */
const handleLogoChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        localProjectForm.logo = file;
    } else {
        localProjectForm.logo = null;
    }
};

/**
 * Submits the updated basic information for an existing project.
 * Uses a PUT request.
 */
const submitBasicInfo = async () => {
    isSavingLocal.value = true; // Start local saving indicator

    // Create FormData for the main project update (required for file uploads)
    const dataToSubmit = new FormData();
    // Append all relevant fields from localProjectForm
    for (const key in localProjectForm) {
        // Skip 'id' and 'tags_data' as they are handled differently or are for display
        if (key !== 'id' && key !== 'tags_data' && localProjectForm[key] !== null && localProjectForm[key] !== undefined) {
            if (Array.isArray(localProjectForm[key])) {
                localProjectForm[key].forEach((item, index) => {
                    if (typeof item === 'object' && item !== null) {
                        dataToSubmit.append(`${key}[${index}]`, JSON.stringify(item));
                    } else {
                        dataToSubmit.append(`${key}[]`, item);
                    }
                });
            } else {
                dataToSubmit.append(key, localProjectForm[key]);
            }
        }
    }

    // Spoof PUT request for FormData submission
    dataToSubmit.append('_method', 'PUT');

    try {
        // Make the PUT request to update the existing project
        const response = await window.axios.post(`/api/projects/${props.projectId}`, dataToSubmit, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        success('Project basic information updated successfully!');

        // Update local form with any fresh data from response (e.g., logo path, updated fields)
        if (response.data) {
            Object.assign(localProjectForm, {
                name: response.data.name || localProjectForm.name,
                description: response.data.description || localProjectForm.description,
                website: response.data.website || localProjectForm.website,
                social_media_link: response.data.social_media_link || localProjectForm.social_media_link,
                preferred_keywords: response.data.preferred_keywords || localProjectForm.preferred_keywords,
                google_chat_id: response.data.google_chat_id || localProjectForm.google_chat_id,
                google_drive_link: response.data.google_drive_link || localProjectForm.google_drive_link,
                logo: response.data.logo || localProjectForm.logo, // Ensure logo path is updated
                status: response.data.status || localProjectForm.status,
                project_type: response.data.project_type || localProjectForm.project_type,
                source: response.data.source || localProjectForm.source,
                tags: response.data.tags || localProjectForm.tags,
                tags_data: response.data.tags_data || localProjectForm.tags_data,
                timezone: response.data.timezone || localProjectForm.timezone,
                project_tier_id: response.data.project_tier_id || localProjectForm.project_tier_id,
                profit_margin_percentage: response.data.profit_margin_percentage || localProjectForm.profit_margin_percentage
            });
        }

    } catch (err) {
        console.error('Error saving basic info:', err);
        let errorMessage = 'Failed to save basic information.';
        if (err.response && err.response.data && err.response.data.message) {
            errorMessage = err.response.data.message;
        }
        error(errorMessage); // Display error notification
    } finally {
        isSavingLocal.value = false; // End local saving indicator
    }
};

// Watch for projectId changes to re-fetch data (useful if component is reused or projectId changes)
watch(() => props.projectId, async (newId) => {
    if (newId) {
        await fetchBasicInfoData();
    }
}, { immediate: true }); // Immediate ensures it runs on initial mount too

/**
 * Fetches basic project information from the backend.
 * Populates the localProjectForm reactive object.
 */
const fetchBasicInfoData = async () => {
    if (!props.projectId) {
        isLoadingLocal.value = false;
        return;
    }
    isLoadingLocal.value = true;
    try {
        // Fetch basic info for the given projectId
        const data = await fetchProjectSectionData(props.projectId, 'basic', {}); // Permissions not strictly needed for basic fetch here
        if (data) {
            // Populate localProjectForm with fetched data
            Object.assign(localProjectForm, {
                id: data.id || props.projectId, // Ensure ID is set
                name: data.name || '',
                description: data.description || '',
                website: data.website || '',
                social_media_link: data.social_media_link || '',
                preferred_keywords: data.preferred_keywords || '',
                google_chat_id: data.google_chat_id || '',
                google_drive_link: data.google_drive_link || '',
                logo: data.logo || null,
                status: data.status || 'active',
                project_type: data.project_type || '',
                source: data.source || '',
                tags: data.tags || [],
                tags_data: data.tags_data || [],
                timezone: data.timezone || null,
                project_tier_id: data.project_tier_id || null,
                profit_margin_percentage: data.profit_margin_percentage || null
            });
        }
    } catch (err) {
        console.error('Error fetching basic info:', err);
        error('Failed to load basic project information.');
    } finally {
        isLoadingLocal.value = false;
    }
};

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

// Initial data fetch on component mount
onMounted(() => {
    // The watch handler with { immediate: true } will handle the initial fetch
    // when props.projectId is first available.
    fetchProjectTiers(); // Fetch project tiers on component mount
});
</script>

<template>
    <div class="space-y-6 bg-white p-6 rounded-lg shadow-md border border-gray-100 font-inter">
        <div v-if="isLoadingLocal" class="text-center py-8 text-gray-500 text-lg">
            <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Loading basic project information...
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Project Name -->
            <div>
                <InputLabel for="name" value="Project Name" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="localProjectForm.name"
                    required
                    autofocus
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                />
                <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
            </div>

            <!-- Website -->
            <div>
                <InputLabel for="website" value="Website" />
                <TextInput
                    id="website"
                    type="url"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="localProjectForm.website"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                />
                <InputError :message="errors.website ? errors.website[0] : ''" class="mt-2" />
            </div>

            <!-- Social Media Link -->
            <div>
                <InputLabel for="social_media_link" value="Social Media Link" />
                <TextInput
                    id="social_media_link"
                    type="url"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="localProjectForm.social_media_link"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                />
                <InputError :message="errors.social_media_link ? errors.social_media_link[0] : ''" class="mt-2" />
            </div>

            <!-- Google Chat ID -->
            <div v-if="canManageProjectBasicDetails">
                <InputLabel for="google_chat_id" value="Google Chat ID" />
                <TextInput
                    id="google_chat_id"
                    type="text"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="localProjectForm.google_chat_id"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                />
                <InputError :message="errors.google_chat_id ? errors.google_chat_id[0] : ''" class="mt-2" />
            </div>

            <!-- Google Drive Link -->
            <div v-if="canManageProjectBasicDetails">
                <InputLabel for="google_drive_link" value="Google Drive Link" />
                <TextInput
                    id="google_drive_link"
                    type="text"
                    class="mt-1 block w-full rounded-lg shadow-sm"
                    v-model="localProjectForm.google_drive_link"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                />
                <InputError :message="errors.google_drive_link ? errors.google_drive_link[0] : ''" class="mt-2" />
            </div>

            <!-- Status -->
            <div>
                <InputLabel for="status" value="Status" />
                <SelectDropdown
                    id="status"
                    v-model="localProjectForm.status"
                    :options="statusOptions"
                    valueKey="value"
                    labelKey="label"
                    placeholder="Select Project Status"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                    :required="true"
                    class="mt-1 block w-full"
                />
                <InputError :message="errors.status ? errors.status[0] : ''" class="mt-2" />
            </div>

            <!-- Project Type Dropdown -->
            <div>
                <ProjectTypeInput
                    id="project_type"
                    v-model="localProjectForm.project_type"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                    class="mt-1 block w-full"
                />
                <InputError :message="errors.project_type ? errors.project_type[0] : ''" class="mt-2" />
            </div>

            <!-- Source -->
            <div>
                <InputLabel for="source" value="Source" />
                <SelectDropdown
                    id="source"
                    v-model="localProjectForm.source"
                    :options="sourceOptions"
                    valueKey="value"
                    labelKey="label"
                    placeholder="Select a Source"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                    class="mt-1 block w-full"
                />
                <InputError :message="errors.source ? errors.source[0] : ''" class="mt-2" />
            </div>

            <!-- Timezone -->
            <div>
                <InputLabel for="timezone" value="Timezone" />
                <TimezoneSelect
                    id="timezone"
                    v-model="localProjectForm.timezone"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                    class="mt-1 block w-full"
                />
                <InputError :message="errors.timezone ? errors.timezone[0] : ''" class="mt-2" />
            </div>

            <!-- Project Tier -->
            <div>
                <InputLabel for="project_tier_id" value="Project Tier" />
                <SelectDropdown
                    id="project_tier_id"
                    v-model="localProjectForm.project_tier_id"
                    :options="projectTiers"
                    valueKey="id"
                    labelKey="name"
                    placeholder="Select Project Tier"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                    class="mt-1 block w-full"
                />
                <InputError :message="errors.project_tier_id ? errors.project_tier_id[0] : ''" class="mt-2" />
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
                    v-model="localProjectForm.profit_margin_percentage"
                    :disabled="!canManageProjects || isSavingLocal || isSaving"
                />
                <InputError :message="errors.profit_margin_percentage ? errors.profit_margin_percentage[0] : ''" class="mt-2" />
            </div>

            <!-- Project Logo -->
            <div v-if="canManageProjectBasicDetails">
                <InputLabel for="logo" value="Project Logo" />
                <input
                    type="file"
                    id="logo"
                    @change="handleLogoChange"
                    class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                    accept="image/*"
                    :disabled="!canManageProjectBasicDetails || isSavingLocal || isSaving"
                />
                <div v-if="localProjectForm.logo && typeof localProjectForm.logo === 'string'" class="mt-2">
                    <p class="text-sm text-gray-600">Current Logo:</p>
                    <img :src="localProjectForm.logo" alt="Project Logo" class="h-20 w-20 object-contain rounded-lg shadow-md border border-gray-200" />
                </div>
                <InputError :message="errors.logo ? errors.logo[0] : ''" class="mt-2" />
            </div>
        </div>

        <!-- Description (full width) -->
        <div class="mt-6">
            <InputLabel for="description" value="Description" />
            <textarea
                id="description"
                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full"
                v-model="localProjectForm.description"
                :disabled="!canManageProjects || isSavingLocal || isSaving"
                rows="4"
            ></textarea>
            <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
        </div>

        <!-- Client Preferred Keywords (full width) -->
        <div class="mt-6">
            <InputLabel for="preferred_keywords" value="Client Preferred Keywords" />
            <TextInput
                id="preferred_keywords"
                type="text"
                class="mt-1 block w-full rounded-lg shadow-sm"
                v-model="localProjectForm.preferred_keywords"
                :disabled="!canManageProjects || isSavingLocal || isSaving"
            />
            <InputError :message="errors.preferred_keywords ? errors.preferred_keywords[0] : ''" class="mt-2" />
        </div>

        <!-- Tags Input Field (full width) -->
        <div class="mt-6">
            <TagInput
                v-model="localProjectForm.tags"
                :initialTags="localProjectForm.tags_data"
                label="Associated Tags"
                placeholder="Search or add tags"
                :error="errors.tags ? errors.tags[0] : ''"
                :disabled="!canManageProjects || isSavingLocal || isSaving"
            />
        </div>

        <div class="mt-6 flex justify-end">
            <PrimaryButton
                @click="submitBasicInfo"
                v-if="canManageProjectBasicDetails"
                :disabled="!canManageProjects || isSavingLocal || isSaving"
                :class="{ 'opacity-50 cursor-not-allowed': isSavingLocal || isSaving }"
                class="px-6 py-2 rounded-lg text-base shadow-md hover:shadow-lg transition-all"
            >
                <span v-if="isSavingLocal || isSaving" class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Updating...
                </span>
                <span v-else>
                    Update Basic Information
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
