<script setup>
import { computed, watch, ref } from 'vue'; // Import ref for local state if needed, but isSaving will be a prop
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import TagInput from '@/Components/TagInput.vue';
import { success } from '@/Utils/notification';
import TimezoneSelect from "@/Components/TimezoneSelect.vue";
import ProjectTypeInput from "@/Components/ProjectTypeInput.vue";

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
  // The statusOptions prop will now be defined here with the new mandatory options
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
  // NEW PROP: isSaving to control loading indicator from parent
  isSaving: {
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
  // No need to set a local loading state here, as it's controlled by the parent.
  // The parent will set isSaving to true before calling the API.

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

  // If logo is a File object, it should be uploaded separately
  const logoFileToUpload = typeof currentLogo === 'object' && currentLogo !== null && 'name' in currentLogo
      ? currentLogo
      : null;

  if (logoFileToUpload) {
    // Remove logo from JSON submission as it will be uploaded separately
    delete formData.logo;
  }

  // Emit to parent for creation/update. Parent will handle the actual API call and loading state.
  emit('submit', formData, !localProjectForm.value.id, logoFileToUpload);

  // If there was a logo file and project is saved/updated, upload it
  // This part should ideally also be handled by the parent or a service
  // that manages the full save flow, including setting isSaving to false
  // only after logo upload is complete. For now, keeping it here for continuity.
  if (logoFileToUpload && localProjectForm.value.id) {
    // You might want to pass a separate loading state for logo upload if it's critical
    // For simplicity, we'll assume the main isSaving covers this.
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
  <div class="space-y-6 bg-white p-6 rounded-lg shadow-md border border-gray-100 font-inter">
    <div class="mb-4">
      <InputLabel for="name" value="Project Name" />
      <TextInput
          id="name"
          type="text"
          class="mt-1 block w-full rounded-lg shadow-sm"
          v-model="localProjectForm.name"
          required
          autofocus
          :disabled="!canManageProjects || isSaving"
      />
      <InputError :message="errors.name ? errors.name[0] : ''" class="mt-2" />
    </div>
    <div class="mb-4">
      <InputLabel for="description" value="Description" />
      <textarea
          id="description"
          class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full"
          v-model="localProjectForm.description"
          :disabled="!canManageProjects || isSaving"
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
          :disabled="!canManageProjects || isSaving"
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
          :disabled="!canManageProjects || isSaving"
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
          :disabled="!canManageProjects || isSaving"
      />
      <InputError :message="errors.preferred_keywords ? errors.preferred_keywords[0] : ''" class="mt-2" />
    </div>

    <div class="mb-4" v-if="localProjectForm.id && canManageProjectBasicDetails">
      <InputLabel for="logo" value="Project Logo" />
      <input
          type="file"
          id="logo"
          @change="handleLogoChange"
          class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer"
          accept="image/*"
          :disabled="!canManageProjectBasicDetails || isSaving"
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
          :disabled="!canManageProjects || isSaving"
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
          :disabled="!canManageProjects || isSaving"
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
          :disabled="!canManageProjects || isSaving"
          :required="true"
          class="mt-1 block w-full"
      />
      <InputError :message="errors.status ? errors.status[0] : ''" class="mt-2" />
    </div>

    <!-- Project Type Dropdown -->
    <div class="mb-4">
      <ProjectTypeInput
          id="project_type"
          v-model="localProjectForm.project_type"
          :disabled="!canManageProjects || isSaving"
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
          :disabled="!canManageProjects || isSaving"
          class="mt-1 block w-full"
      />
      <InputError :message="errors.source ? errors.source[0] : ''" class="mt-2" />
    </div>

    <div class="mb-4">
      <TimezoneSelect
          id="timezone"
          v-model="localProjectForm.timezone"
          :disabled="!canManageProjects || isSaving"
          class="mt-1 block w-full"
      />
      <InputError :message="errors.timezone ? errors.timezone[0] : ''" class="mt-2" />
    </div>

    <!-- Tags Input Field -->
    <div class="mb-4">
      <TagInput
          v-model="localProjectForm.tags"
          :initialTags="localProjectForm.tags_data"
          label="Associated Tags"
          placeholder="Search or add tags"
          :error="errors.tags ? errors.tags[0] : ''"
          :disabled="!canManageProjects || isSaving"
      />
    </div>

    <div class="mt-6 flex justify-end">
      <PrimaryButton
          @click="submitBasicInfo"
          :disabled="!canManageProjects || (localProjectForm.id && !canManageProjectBasicDetails) || isSaving"
          :class="{ 'opacity-50 cursor-not-allowed': isSaving }"
          class="px-6 py-2 rounded-lg text-base shadow-md hover:shadow-lg transition-all"
      >
                <span v-if="isSaving" class="flex items-center">
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
