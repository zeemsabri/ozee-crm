<script setup>
import { computed, watch, ref, onMounted } from 'vue';
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
    projectForm: {
        type: Object,
        required: true,
        default: () => ({
            id: null,
            name: '',
            description: '',
            website: '',
            social_media_link: '',
            preferred_keywords: '',
            google_chat_id: '',
            google_drive_link: '',
            logo: null,
            status: 'active', // Default status
            project_type: '',
            source: '',
            tags: [],
            tags_data: [],
            timezone: null
        })
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
    canManageProjects: {
        type: Boolean,
        default: false
    },
    canManageProjectBasicDetails: {
        type: Boolean,
        default: false
    },
    // isSaving prop from parent is now less critical for this component's own save button,
    // as it manages its own `isSavingLocal` state.
    // However, it can still be used to disable inputs if the *overall* form is in a saving state.
    isSaving: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:projectForm']);

// Create a local reactive copy for v-model binding
const localProjectForm = computed({
    get: () => props.projectForm,
    set: (value) => emit('update:projectForm', value)
});

const isSavingLocal = ref(false); // Local saving state for this component's submit button

// Function to upload logo separately
const uploadLogo = async (logoFile, projectId) => {
    try {
        const formData = new FormData();
        formData.append('logo', logoFile);

        const response = await window.axios.post(
            `/api/projects/${projectId}/logo`,
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        );

        if (response.data && response.data.logo) {
            localProjectForm.value.logo = response.data.logo;
            success('Project logo uploaded successfully!');
        }
        return response;
    } catch (error) {
        console.error('Error uploading logo:', error);
        throw error; // Re-throw to be caught by submitBasicInfo
    }
};

const submitBasicInfo = async () => {
    isSavingLocal.value = true; // Start local saving indicator

    const formData = {
        name: localProjectForm.value.name,
        description: localProjectForm.value.description,
        website: localProjectForm.value.website,
        social_media_link: localProjectForm.value.social_media_link,
        preferred_keywords: localProjectForm.value.preferred_keywords,
        google_chat_id: localProjectForm.value.google_chat_id,
        status: localProjectForm.value.status,
        project_type: localProjectForm.value.project_type,
        source: localProjectForm.value.source,
        google_drive_link: localProjectForm.value.google_drive_link,
        tags: localProjectForm.value.tags,
        timezone: localProjectForm.value.timezone
    };

    const currentLogo = localProjectForm.value.logo;
    const logoFileToUpload = typeof currentLogo === 'object' && currentLogo !== null && 'name' in currentLogo && currentLogo instanceof File
        ? currentLogo
        : null;

    // Create FormData for the main project update
    const dataToSubmit = new FormData();
    for (const key in formData) {
        if (formData[key] !== null && formData[key] !== undefined) {
            if (Array.isArray(formData[key])) {
                formData[key].forEach((item, index) => {
                    if (typeof item === 'object' && item !== null) {
                        dataToSubmit.append(`${key}[${index}]`, JSON.stringify(item));
                    } else {
                        dataToSubmit.append(`${key}[]`, item);
                    }
                });
            } else {
                dataToSubmit.append(key, formData[key]);
            }
        }
    }

    if (logoFileToUpload) {
        dataToSubmit.append('logo', logoFileToUpload);
    }

    try {
        let response;
        if (localProjectForm.value.id) {
            // Existing project: Use PUT method
            dataToSubmit.append('_method', 'PUT'); // Spoof PUT request for FormData
            response = await window.axios.post(`/api/projects/${localProjectForm.value.id}`, dataToSubmit, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            success('Project basic information updated successfully!');
        } else {
            // New project: Use POST method
            response = await window.axios.post('/api/projects', dataToSubmit, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            success('Project created successfully!');
            // Update the projectForm with the new ID from the response
            localProjectForm.value.id = response.data.id;
            // Redirect to the edit page if it's a new project
            if (response.data.id) {
                router.visit(route('projects.edit', { project: response.data.id }));
            }
        }

        // Update local form with any fresh data from response (e.g., logo path, updated fields)
        if (response.data) {
            Object.assign(localProjectForm.value, {
                name: response.data.name || localProjectForm.value.name,
                description: response.data.description || localProjectForm.value.description,
                website: response.data.website || localProjectForm.value.website,
                social_media_link: response.data.social_media_link || localProjectForm.value.social_media_link,
                preferred_keywords: response.data.preferred_keywords || localProjectForm.value.preferred_keywords,
                google_chat_id: response.data.google_chat_id || localProjectForm.value.google_chat_id,
                google_drive_link: response.data.google_drive_link || localProjectForm.value.google_drive_link,
                logo: response.data.logo || localProjectForm.value.logo, // Ensure logo path is updated
                status: response.data.status || localProjectForm.value.status,
                project_type: response.data.project_type || localProjectForm.value.project_type,
                source: response.data.source || localProjectForm.value.source,
                tags: response.data.tags || localProjectForm.value.tags,
                tags_data: response.data.tags_data || localProjectForm.value.tags_data,
                timezone: response.data.timezone || localProjectForm.value.timezone
            });
        }

    } catch (err) {
        console.error('Error saving basic info:', err);
        let errorMessage = 'Failed to save basic information.';
        if (err.response && err.response.data && err.response.data.message) {
            errorMessage = err.response.data.message;
        }
        error(errorMessage);
    } finally {
        isSavingLocal.value = false; // End local saving indicator
    }
};

const handleLogoChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        localProjectForm.value.logo = file;
    } else {
        localProjectForm.value.logo = null;
    }
};

// Fetch basic info on component mount if project ID exists
onMounted(async () => {
    if (localProjectForm.value.id) {
        try {
            const data = await fetchProjectSectionData(localProjectForm.value.id, 'basic', {}); // Permissions not needed for basic fetch here
            if (data) {
                // Only update fields that are part of basic info
                Object.assign(localProjectForm.value, {
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
                    timezone: data.timezone || null
                });
            }
        } catch (err) {
            console.error('Error fetching basic info on mount:', err);
            error('Failed to load basic project information.');
        }
    }
});
</script>

<template>
    <div class="space-y-6 bg-white p-6 rounded-lg shadow-md border border-gray-100 font-inter">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

            <!-- Project Logo (only for existing projects or if can manage basic details) -->
            <div v-if="localProjectForm.id && canManageProjectBasicDetails">
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
                :disabled="!canManageProjects || (localProjectForm.id && !canManageProjectBasicDetails) || isSavingLocal || isSaving"
                :class="{ 'opacity-50 cursor-not-allowed': isSavingLocal || isSaving }"
                class="px-6 py-2 rounded-lg text-base shadow-md hover:shadow-lg transition-all"
            >
                <span v-if="isSavingLocal || isSaving" class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ localProjectForm.id ? 'Updating...' : 'Creating...' }}
                </span>
                <span v-else>
                    {{ localProjectForm.id ? 'Update Basic Information' : 'Create Project' }}
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
