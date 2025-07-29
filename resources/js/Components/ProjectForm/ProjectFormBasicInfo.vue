<script setup>
import { computed, watch } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue'; // Import SelectDropdown
import TagInput from '@/Components/TagInput.vue'; // Import TagInput
import { success } from '@/Utils/notification'; // Assuming notification is globally available or passed down

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
            status: 'active',
            project_type: '', // Make sure this is initialized
            source: '',
            tags: [], // Initialize tags as an empty array of IDs
            tags_data: [], // Initialize tags_data as empty array of objects for TagInput
        })
    },
    errors: {
        type: Object,
        default: () => ({})
    },
    statusOptions: {
        type: Array,
        required: true
    },
    sourceOptions: {
        type: Array,
        required: true
    },
    projectTypeOptions: { // New prop for project types
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
    }
});

const emit = defineEmits(['update:projectForm', 'submit']);

// Create a local reactive copy for v-model binding
const localProjectForm = computed({
    get: () => props.projectForm,
    set: (value) => emit('update:projectForm', value)
});

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

        // Update the logo in the form with the response from the server
        if (response.data && response.data.logo) {
            localProjectForm.value.logo = response.data.logo;
            success('Project logo uploaded successfully!');
        }
        return response;
    } catch (error) {
        console.error('Error uploading logo:', error);
        // Do not show error to user as this is a background operation
        // The project was already saved successfully if this is an update
    }
};

const submitBasicInfo = async () => {
    const formData = {
        name: localProjectForm.value.name,
        description: localProjectForm.value.description,
        website: localProjectForm.value.website,
        social_media_link: localProjectForm.value.social_media_link,
        preferred_keywords: localProjectForm.value.preferred_keywords,
        google_chat_id: localProjectForm.value.google_chat_id,
        status: localProjectForm.value.status,
        project_type: localProjectForm.value.project_type, // Include project_type
        source: localProjectForm.value.source,
        google_drive_link: localProjectForm.value.google_drive_link,
        tags: localProjectForm.value.tags, // Include tags (array of IDs)
    };

    const currentLogo = localProjectForm.value.logo;

    // If logo is a File object, it should be uploaded separately
    const logoFileToUpload = typeof currentLogo === 'object' && currentLogo !== null && 'name' in currentLogo
        ? currentLogo
        : null;

    if (logoFileToUpload) {
        // Remove logo from JSON submission as it will be uploaded separately
        delete formData.logo;
    }

    // Emit to parent for creation/update
    emit('submit', formData, !localProjectForm.value.id);

    // If there was a logo file and project is saved/updated, upload it
    if (logoFileToUpload && localProjectForm.value.id) {
        await uploadLogo(logoFileToUpload, localProjectForm.value.id);
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
</script>

<template>
    <div class="space-y-6 bg-white p-6 rounded-lg shadow-md border border-gray-100">
        <div class="mb-4">
            <InputLabel for="name" value="Project Name" />
            <TextInput
                id="name"
                type="text"
                class="mt-1 block w-full rounded-lg shadow-sm"
                v-model="localProjectForm.name"
                required
                autofocus
                :disabled="!canManageProjects"
            />
            <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
        </div>
        <div class="mb-4">
            <InputLabel for="description" value="Description" />
            <textarea
                id="description"
                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full"
                v-model="localProjectForm.description"
                :disabled="!canManageProjects"
                rows="4"
            ></textarea>
            <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
        </div>
        <div class="mb-4">
            <InputLabel for="website" value="Website" />
            <TextInput
                id="website"
                type="url"
                class="mt-1 block w-full rounded-lg shadow-sm"
                v-model="localProjectForm.website"
                :disabled="!canManageProjects"
            />
            <InputError :message="errors.website ? errors.website[0] : ''" class="mt-2" />
        </div>
        <div class="mb-4">
            <InputLabel for="social_media_link" value="Social Media Link" />
            <TextInput
                id="social_media_link"
                type="url"
                class="mt-1 block w-full rounded-lg shadow-sm"
                v-model="localProjectForm.social_media_link"
                :disabled="!canManageProjects"
            />
            <InputError :message="errors.social_media_link ? errors.social_media_link[0] : ''" class="mt-2" />
        </div>
        <div class="mb-4">
            <InputLabel for="preferred_keywords" value="Client Preferred Keywords" />
            <TextInput
                id="preferred_keywords"
                type="text"
                class="mt-1 block w-full rounded-lg shadow-sm"
                v-model="localProjectForm.preferred_keywords"
                :disabled="!canManageProjects"
            />
            <InputError :message="errors.preferred_keywords ? errors.preferred_keywords[0] : ''" class="mt-2" />
        </div>

        <!-- Tags Input Field -->
        <div class="mb-4">
            <TagInput
                v-model="localProjectForm.tags"
                :initialTags="localProjectForm.tags_data"
                label="Associated Tags"
                placeholder="Search or add tags"
                :error="errors.tags ? errors.tags[0] : ''"
                :disabled="!canManageProjects"
            />
        </div>

        <div class="mb-4" v-if="localProjectForm.id && canManageProjectBasicDetails">
            <InputLabel for="logo" value="Project Logo" />
            <input
                type="file"
                id="logo"
                @change="handleLogoChange"
                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
                accept="image/*"
                :disabled="!canManageProjectBasicDetails"
            />
            <div v-if="localProjectForm.logo && typeof localProjectForm.logo === 'string'" class="mt-2">
                <p class="text-sm text-gray-600">Current Logo:</p>
                <img :src="localProjectForm.logo" alt="Project Logo" class="h-20 w-20 object-contain rounded-lg shadow-md border border-gray-200" />
            </div>
            <InputError :message="errors.logo ? errors.logo[0] : ''" class="mt-2" />
        </div>

        <div class="mb-4" v-if="canManageProjectBasicDetails">
            <InputLabel for="google_chat_id" value="Google Chat ID" />
            <TextInput
                id="google_chat_id"
                type="text"
                class="mt-1 block w-full rounded-lg shadow-sm"
                v-model="localProjectForm.google_chat_id"
                :disabled="!canManageProjects"
            />
            <InputError :message="errors.google_chat_id ? errors.google_chat_id[0] : ''" class="mt-2" />
        </div>

        <div class="mb-4" v-if="canManageProjectBasicDetails">
            <InputLabel for="google_drive_link" value="Google Drive Link" />
            <TextInput
                id="google_drive_link"
                type="text"
                class="mt-1 block w-full rounded-lg shadow-sm"
                v-model="localProjectForm.google_drive_link"
                :disabled="!canManageProjects"
            />
            <InputError :message="errors.google_drive_link ? errors.google_drive_link[0] : ''" class="mt-2" />
        </div>

        <div class="mb-4">
            <InputLabel for="status" value="Status" />
            <SelectDropdown
                id="status"
                v-model="localProjectForm.status"
                :options="statusOptions"
                valueKey="value"
                labelKey="label"
                placeholder="Select Project Status"
                :disabled="!canManageProjects"
                :required="true"
                class="mt-1 block w-full"
            />
            <InputError :message="errors.status ? errors.status[0] : ''" class="mt-2" />
        </div>

        <!-- Project Type Dropdown -->
        <div class="mb-4">
            <InputLabel for="project_type" value="Project Type" />
            <SelectDropdown
                id="project_type"
                v-model="localProjectForm.project_type"
                :options="projectTypeOptions"
                valueKey="value"
                labelKey="label"
                placeholder="Select Project Type"
                :disabled="!canManageProjects"
                class="mt-1 block w-full"
            />
            <InputError :message="errors.project_type ? errors.project_type[0] : ''" class="mt-2" />
        </div>

        <div class="mb-4">
            <InputLabel for="source" value="Source" />
            <SelectDropdown
                id="source"
                v-model="localProjectForm.source"
                :options="sourceOptions"
                valueKey="value"
                labelKey="label"
                placeholder="Select a Source"
                :disabled="!canManageProjects"
                class="mt-1 block w-full"
            />
            <InputError :message="errors.source ? errors.source[0] : ''" class="mt-2" />
        </div>

        <div class="mt-6 flex justify-end">
            <PrimaryButton
                @click="submitBasicInfo"
                :disabled="!canManageProjects || (localProjectForm.id && !canManageProjectBasicDetails)"
                class="px-6 py-2 rounded-lg text-base shadow-md hover:shadow-lg transition-all"
            >
                {{ localProjectForm.id ? 'Update Basic Information' : 'Create Project' }}
            </PrimaryButton>
        </div>
    </div>
</template>

