<script setup>
import { computed, watch, ref, onMounted, reactive } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TagInput from '@/Components/TagInput.vue';
import {success, error, handleLaravelError, info} from '@/Utils/notification';
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
            { value: 'on_hold', label: 'On-hold' },
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
    reporting_sites: '',
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
    profit_margin_percentage: null,
    telegram_group_id: '',
    telegram_group_name: '',
    telegram_link_code: '',
    integrations: {}
});

const bugherdProjects = ref([]);
const isLoadingBugHerd = ref(false);

const fetchBugHerdProjects = async () => {
    isLoadingBugHerd.value = true;
    try {
        const response = await window.axios.get('/api/bugherd/projects');
        bugherdProjects.value = response.data.map(p => ({
            value: p.id,
            label: p.name
        }));
    } catch (err) {
        console.error('Failed to load BugHerd projects');
    } finally {
        isLoadingBugHerd.value = false;
    }
};

const isSavingLocal = ref(false); // Local saving state for this component's submit button
const isLoadingLocal = ref(true); // Local loading state for this component's data fetch
const projectTiers = ref([]); // Store project tiers fetched from API

/**
 * Fetches project tiers from the API
 */
const fetchProjectTiers = async () => {
    try {
        const response = await window.axios.get('/api/project-tiers');
        projectTiers.value = response.data;
    } catch (err) {
        error('Failed to load project tiers.');
    }
};

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
        // If file is cleared, set logo to null.
        // If it was previously a string (existing logo), it will become null,
        // which means it won't be sent in the FormData and the backend won't try to validate it.
        localProjectForm.logo = null;
    }
};

/**
 * Submits the updated basic information for an existing project.
 * Uses a PUT request.
 */
const submitBasicInfo = async () => {
    isSavingLocal.value = true; // Start local saving indicator

    const dataToSubmit = new FormData();

    // Append all basic text/non-file/non-array fields
    // Explicitly exclude 'id', 'tags', 'tags_data', and 'logo' from this initial loop
    const fieldsToExclude = ['id', 'tags', 'tags_data', 'logo', 'integrations'];

    // Explicitly add project_tier_id to ensure it's included
    if (localProjectForm.project_tier_id !== null && localProjectForm.project_tier_id !== undefined) {
        // Convert to string to ensure consistent type handling
        const projectTierId = String(localProjectForm.project_tier_id);
        dataToSubmit.append('project_tier_id', projectTierId);
    } else {
        console.log('project_tier_id is null or undefined:', localProjectForm.project_tier_id);
    }

    // Add profit_margin_percentage explicitly as well
    if (localProjectForm.profit_margin_percentage !== null && localProjectForm.profit_margin_percentage !== undefined) {
        dataToSubmit.append('profit_margin_percentage', localProjectForm.profit_margin_percentage);
    }

    // Handle integrations explicitly
    if (localProjectForm.integrations && typeof localProjectForm.integrations === 'object') {
        dataToSubmit.append('integrations', JSON.stringify(localProjectForm.integrations));
    }

    // Add the rest of the fields
    for (const key in localProjectForm) {
        if (!fieldsToExclude.includes(key) && key !== 'project_tier_id' && key !== 'profit_margin_percentage') {
            if (localProjectForm[key] !== null && localProjectForm[key] !== undefined) {
                dataToSubmit.append(key, localProjectForm[key]);
            }
        }
    }

    // Handle tags: ensure they are sent as an array of IDs
    if (Array.isArray(localProjectForm.tags)) {
        localProjectForm.tags.forEach(tag => {
            // Assuming tag can be an object {id, name} or just an ID
            const tagId = typeof tag === 'object' && tag !== null && tag.id ? tag.id : tag;
            if (tagId !== null && tagId !== undefined) {
                dataToSubmit.append('tags[]', tagId);
            }
        });
    }

    // Handle logo: only append if it's a new File object
    if (localProjectForm.logo instanceof File) {
        dataToSubmit.append('logo', localProjectForm.logo);
    }
    // If localProjectForm.logo is a string (existing path), we do NOT append it.
    // The backend should interpret the absence of the 'logo' field as 'no change to logo'.

    dataToSubmit.append('_method', 'PUT'); // Spoof PUT request for FormData

    try {
        // Make the PUT request to update the existing project
        const response = await window.axios.post(`/api/projects/${props.projectId}/sections/basic`, dataToSubmit, {
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
                reporting_sites: response.data.reporting_sites || localProjectForm.reporting_sites,
                google_chat_id: response.data.google_chat_id || localProjectForm.google_chat_id,
                google_drive_link: response.data.google_drive_link || localProjectForm.google_drive_link,
                logo: response.data.logo_url || response.data.logo || localProjectForm.logo, // Ensure logo path is updated
                status: response.data.status || localProjectForm.status,
                project_type: response.data.project_type || localProjectForm.project_type,
                source: response.data.source || localProjectForm.source,
                tags: response.data.tags || localProjectForm.tags,
                tags_data: response.data.tags_data || localProjectForm.tags_data,
                timezone: response.data.timezone || localProjectForm.timezone,
                project_tier_id: response.data.project_tier_id || localProjectForm.project_tier_id,
                profit_margin_percentage: response.data.profit_margin_percentage || localProjectForm.profit_margin_percentage,
                telegram_group_id: response.data.telegram_group_id || localProjectForm.telegram_group_id,
                telegram_group_name: response.data.telegram_group_name || localProjectForm.telegram_group_name,
                telegram_link_code: response.data.telegram_link_code || localProjectForm.telegram_link_code,
                integrations: response.data.integrations || {}
            });
        }

    } catch (err) {

        handleLaravelError(err);

    } finally {
        isSavingLocal.value = false; // End local saving indicator
    }
};

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
                reporting_sites: data.reporting_sites || '',
                google_chat_id: data.google_chat_id || '',
                google_drive_link: data.google_drive_link || '',
                logo: data.logo_url || data.logo || null,
                status: data.status || 'active',
                project_type: data.project_type || '',
                source: data.source || '',
                tags: data.tags || [],
                tags_data: data.tags_data || [],
                timezone: data.timezone || null,
                project_tier_id: data.project_tier_id || null,
                profit_margin_percentage: data.profit_margin_percentage || null,
                telegram_group_id: data.telegram_group_id || '',
                telegram_group_name: data.telegram_group_name || '',
                telegram_link_code: data.telegram_link_code || '',
                integrations: data.integrations || {}
            });
        }
    } catch (err) {
        error('Failed to load basic project information.');
    } finally {
        isLoadingLocal.value = false;
    }
};

const generateTelegramCode = async () => {
    try {
        const response = await window.axios.post(`/api/projects/${props.projectId}/generate-telegram-code`);
        localProjectForm.telegram_link_code = response.data.code;
        success('Telegram link code generated successfully!');
    } catch (err) {
        error('Failed to generate Telegram link code.');
    }
};

const refreshStatus = async () => {
    await fetchBasicInfoData();
    if (localProjectForm.telegram_group_id) {
        success('Telegram linkage status updated!');
    } else {
        info('Telegram is not yet linked. Send the /link command from your group.');
    }
};

// Watch for projectId changes to re-fetch data (useful if component is reused or projectId changes)
watch(() => props.projectId, async (newId) => {

    setTimeout(async () => {
        if (newId) {
            await fetchBasicInfoData();
        }
    }, 500)

}, { immediate: true }); // Immediate ensures it runs on initial mount too

// Initial data fetch on component mount
onMounted(() => {
    // The watch handler with { immediate: true } will handle the initial fetch
    // when props.projectId is first available.
    fetchProjectTiers(); // Fetch project tiers on component mount
    fetchBugHerdProjects(); // Fetch BugHerd projects on component mount
});
</script>

<template>
    <div class="space-y-6 bg-white p-6 rounded-lg border border-gray-100 font-inter">
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
                <!-- Debug display of current project_tier_id value -->
                <div v-if="localProjectForm.project_tier_id" class="mt-1 text-xs text-gray-500">
                    Selected tier ID: {{ localProjectForm.project_tier_id }} ({{ typeof localProjectForm.project_tier_id }})
                </div>
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
            <div>
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

            <!-- BugHerd Integration -->
            <div>
                <InputLabel for="bugherd_project_id" value="BugHerd Project Integration" />
                <SelectDropdown
                    id="bugherd_project_id"
                    v-model="localProjectForm.integrations.bugherd_project_id"
                    :options="bugherdProjects"
                    valueKey="value"
                    labelKey="label"
                    placeholder="Select BugHerd Project"
                    :disabled="!canManageProjects || isSavingLocal || isSaving || isLoadingBugHerd"
                    class="mt-1 block w-full"
                />
                <div v-if="isLoadingBugHerd" class="mt-1 text-xs text-gray-500 flex items-center">
                    <svg class="animate-spin h-3 w-3 mr-1 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading BugHerd projects...
                </div>
                <InputError :message="errors['integrations.bugherd_project_id'] ? errors['integrations.bugherd_project_id'][0] : ''" class="mt-2" />
            </div>
            
            <!-- Telegram Integration -->
            <div class="col-span-1 md:col-span-2 mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                    <svg class="h-6 w-6 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.11.02-1.93 1.23-5.46 3.62-.51.35-.98.53-1.39.52-.46-.01-1.33-.26-1.98-.48-.8-.27-1.43-.42-1.37-.89.03-.25.38-.51 1.07-.78 4.2-1.82 7.01-3.02 8.42-3.58 4.02-1.61 4.85-1.89 5.39-1.89.12 0 .38.03.55.17.14.12.18.28.19.45.02.07.02.15.01.23z"/>
                    </svg>
                    Telegram Integration
                </h3>
                
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex-1">
                        <div v-if="localProjectForm.telegram_group_id" class="flex flex-col gap-2 text-green-700 bg-green-50 px-4 py-3 rounded-md border border-green-200">
                            <div class="flex items-center font-semibold">
                                <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Linked to Telegram Group
                            </div>
                            <div class="ml-7">
                                <div class="text-sm">Group Name: <span class="font-bold">{{ localProjectForm.telegram_group_name || 'N/A' }}</span></div>
                                <div class="text-xs mt-1 text-green-600 opacity-75">ID: <span class="font-mono">{{ localProjectForm.telegram_group_id }}</span></div>
                            </div>
                        </div>
                        <div v-else class="text-gray-600 flex items-center px-4 py-3">
                            <svg class="h-5 w-5 mr-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            Not currently linked to a Telegram group.
                        </div>

                        <div v-if="localProjectForm.telegram_link_code" class="mt-4 p-3 bg-indigo-50 rounded-md border border-indigo-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-semibold text-indigo-800 uppercase tracking-wider">Link Code</span>
                                    <div class="text-2xl font-bold text-indigo-900 font-mono tracking-widest mt-1">#{{ localProjectForm.telegram_link_code }}</div>
                                </div>
                                <div class="bg-indigo-100 p-2 rounded-full">
                                    <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-sm text-indigo-700 mt-2">
                                Send <code class="bg-indigo-200 px-1 py-0.5 rounded">/link #{{ localProjectForm.telegram_link_code }}</code> in your Telegram group to link it with this project.
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex-shrink-0 flex flex-col gap-2">
                        <PrimaryButton 
                            type="button" 
                            @click="generateTelegramCode" 
                            :disabled="isSavingLocal || isSaving"
                            class="bg-indigo-600 hover:bg-indigo-700 w-full justify-center"
                        >
                            {{ localProjectForm.telegram_link_code ? 'Regenerate Code' : 'Generate Link Code' }}
                        </PrimaryButton>

                        <button 
                            type="button"
                            @click="refreshStatus"
                            :disabled="isLoadingLocal"
                            class="inline-flex items-center justify-center px-4 py-2 border border-blue-600 text-sm font-medium rounded-md text-blue-600 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                        >
                            <svg :class="{'animate-spin': isLoadingLocal}" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Refresh Status
                        </button>
                    </div>
                </div>
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

        <!-- Reporting Sites (full width, comma separated) -->
        <div class="mt-6">
            <InputLabel for="reporting_sites" value="Reporting Sites (comma separated)" />
            <TextInput
                id="reporting_sites"
                type="text"
                class="mt-1 block w-full rounded-lg shadow-sm"
                placeholder="e.g. ahrefs.com, semrush.com, analytics.google.com"
                v-model="localProjectForm.reporting_sites"
                :disabled="!canManageProjects || isSavingLocal || isSaving"
            />
            <p class="mt-1 text-xs text-gray-500">Enter multiple site URLs separated by commas.</p>
            <InputError :message="errors.reporting_sites ? errors.reporting_sites[0] : ''" class="mt-2" />
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
                v-permission="['edit_projects', 'manage_projects']"
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
