<script setup>
import { ref, watch, reactive, computed } from 'vue';
import BaseFormModal from '@/Components/BaseFormModal.vue'; // Import your BaseFormModal
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue'; // Correctly imported SelectDropdown
import InputError from '@/Components/InputError.vue'; // Added InputError for consistency

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  projectId: {
    type: [String, Number],
    required: true,
  },
});

const emits = defineEmits(['close', 'saved']);

// Reactive form data to be passed to BaseFormModal
const deliverableForm = reactive({
  title: '',
  description: '',
  type: 'blog_post', // Default type (e.g., blog_post, design_mockup)
  content_url: '',
  content_url_type: null, // NEW: To specify if the URL is a PDF, Image, Google Doc, etc.
  attachment_file: null, // Will hold the File object
  is_visible_to_client: true,
  initial_status: 'pending_review', // Default to pending review
});

const deliverableTypes = [
  { value: 'blog_post', label: 'Blog Post' },
  { value: 'design_mockup', label: 'Design Mockup' },
  { value: 'social_media_post', label: 'Social Media Post' },
  { value: 'report', label: 'Report' },
  { value: 'contract_draft', label: 'Contract Draft' },
  { value: 'proposal', label: 'Proposal' },
  { value: 'other', label: 'Other' },
];

// Options for initial status
const initialStatusOptions = [
  { value: 'pending_review', label: 'Pending Review (Requires Client Approval)' },
  { value: 'for_information', label: 'For Information Only (No Approval Needed)' },
];

// NEW: Options for content URL type
const contentUrlTypeOptions = [
  { value: null, label: 'Select Type (if providing URL)' }, // Default empty option
  { value: 'google_doc', label: 'Google Document (Doc, Sheet, Slide)' },
  { value: 'pdf', label: 'PDF Document' },
  { value: 'image', label: 'Image (JPG, PNG, GIF)' },
  { value: 'video', label: 'Video (YouTube, Vimeo, etc.)' },
  { value: 'other', label: 'Other Web Link' },
];

const fileInput = ref(null);
const previewImageUrl = ref(null);

// Watch for modal opening to reset form
watch(() => props.show, (newVal) => {
  if (newVal) {
    // Reset form data
    Object.assign(deliverableForm, {
      title: '',
      description: '',
      type: 'blog_post',
      content_url: '',
      content_url_type: null, // Reset new field
      attachment_file: null,
      is_visible_to_client: true,
      initial_status: 'pending_review',
    });
    previewImageUrl.value = null;
    if (fileInput.value) {
      fileInput.value.value = ''; // Clear file input
    }
  }
});

const handleFileChange = (event) => {
  const file = event.target.files[0];
  if (file) {
    deliverableForm.attachment_file = file;
    deliverableForm.content_url = ''; // Clear URL if file is selected
    deliverableForm.content_url_type = null; // Clear URL type
    // Generate a preview for images
    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (e) => {
        previewImageUrl.value = e.target.result;
      };
      reader.readAsDataURL(file);
    } else {
      previewImageUrl.value = null; // Clear image preview for non-images
    }
  } else {
    deliverableForm.attachment_file = null;
    previewImageUrl.value = null;
  }
};

const handleUrlChange = () => {
  // Clear file if URL is being typed
  if (deliverableForm.content_url) {
    deliverableForm.attachment_file = null;
    previewImageUrl.value = null;
    if (fileInput.value) {
      fileInput.value.value = ''; // Clear file input
    }
  } else {
    // If URL is cleared, also clear its type
    deliverableForm.content_url_type = null;
  }
};

// Function to format data for the API call (required by BaseFormModal)
const formatDataForApi = (data) => {
  const formData = new FormData();
  formData.append('title', data.title);
  formData.append('description', data.description || '');
  formData.append('type', data.type);
  formData.append('is_visible_to_client', data.is_visible_to_client ? 1 : 0);
  formData.append('initial_status', data.initial_status);

  if (data.attachment_file) {
    formData.append('attachment_file', data.attachment_file);
    // Backend will detect type for uploaded files
  } else if (data.content_url) {
    formData.append('content_url', data.content_url);
    // Only append content_url_type if a URL is provided
    formData.append('content_url_type', data.content_url_type || '');
  }

  return formData;
};

// Computed properties for BaseFormModal
const modalTitle = 'Create New Deliverable';
const apiEndpoint = computed(() => `/api/projects/${props.projectId}/deliverables`);
const httpMethod = 'post';
const submitButtonText = 'Create Deliverable';
const successMessage = 'Deliverable created successfully!';

// Handle successful submission from BaseFormModal
const handleSaved = (responseData) => {
  emits('saved', responseData);
  emits('close');
};

// Pass through the close event
const closeModal = () => {
  emits('close');
};

</script>

<template>
  <BaseFormModal
      :show="show"
      :title="modalTitle"
      :api-endpoint="apiEndpoint"
      :http-method="httpMethod"
      :form-data="deliverableForm"
      :submit-button-text="submitButtonText"
      :success-message="successMessage"
      :format-data-for-api="formatDataForApi"
      @close="closeModal"
      @submitted="handleSaved"
  >
    <template #default="{ errors }">
      <div class="space-y-4">
        <!-- Title -->
        <div>
          <InputLabel for="title" value="Title" />
          <TextInput
              id="title"
              v-model="deliverableForm.title"
              type="text"
              class="mt-1 block w-full"
              required
              autofocus
          />
          <InputError :message="errors.title ? errors.title[0] : ''" class="mt-2" />
        </div>

        <!-- Description -->
        <div>
          <InputLabel for="description" value="Description" />
          <textarea
              id="description"
              v-model="deliverableForm.description"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              rows="3"
              placeholder="Enter deliverable description"
          ></textarea>
          <InputError :message="errors.description ? errors.description[0] : ''" class="mt-2" />
        </div>

        <!-- Deliverable Type -->
        <div>
          <InputLabel for="type" value="Deliverable Type" />
          <SelectDropdown
              id="type"
              v-model="deliverableForm.type"
              :options="deliverableTypes"
              value-key="value"
              label-key="label"
              placeholder="Select a type"
              class="mt-1 block w-full"
              required
          />
          <InputError :message="errors.type ? errors.type[0] : ''" class="mt-2" />
        </div>

        <!-- Initial Status -->
        <div>
          <InputLabel for="initial_status" value="Initial Status" />
          <SelectDropdown
              id="initial_status"
              v-model="deliverableForm.initial_status"
              :options="initialStatusOptions"
              value-key="value"
              label-key="label"
              class="mt-1 block w-full"
              required
          />
          <InputError :message="errors.initial_status ? errors.initial_status[0] : ''" class="mt-2" />
        </div>

        <!-- Content URL -->
        <div>
          <InputLabel for="content_url" value="Content URL (for Google Docs, external links)" />
          <TextInput
              id="content_url"
              v-model="deliverableForm.content_url"
              type="url"
              class="mt-1 block w-full"
              :disabled="!!deliverableForm.attachment_file"
              @input="handleUrlChange"
              placeholder="e.g., https://docs.google.com/document/d/..."
          />
          <p class="text-xs text-gray-500 mt-1">Leave empty if uploading a file below.</p>
          <InputError :message="errors.content_url ? errors.content_url[0] : ''" class="mt-2" />
        </div>

        <!-- Content URL Type (NEW: Conditionally rendered) -->
        <div v-if="deliverableForm.content_url && !deliverableForm.attachment_file">
          <InputLabel for="content_url_type" value="Type of Content URL" />
          <SelectDropdown
              id="content_url_type"
              v-model="deliverableForm.content_url_type"
              :options="contentUrlTypeOptions"
              value-key="value"
              label-key="label"
              placeholder="Select the type of content this URL links to"
              class="mt-1 block w-full"
              required
          />
          <InputError :message="errors.content_url_type ? errors.content_url_type[0] : ''" class="mt-2" />
        </div>

        <!-- Upload File -->
        <div>
          <InputLabel for="attachment_file" value="Upload File (PDF, Image)" />
          <input
              id="attachment_file"
              ref="fileInput"
              type="file"
              @change="handleFileChange"
              class="mt-1 block w-full text-sm text-gray-500
                               file:mr-4 file:py-2 file:px-4
                               file:rounded-md file:border-0
                               file:text-sm file:font-semibold
                               file:bg-indigo-50 file:text-indigo-700
                               hover:file:bg-indigo-100"
              :disabled="!!deliverableForm.content_url"
              accept="application/pdf,image/*"
          />
          <p class="text-xs text-gray-500 mt-1">Max 10MB. Leave empty if providing a URL above.</p>
          <InputError :message="errors.attachment_file ? errors.attachment_file[0] : ''" class="mt-2" />

          <div v-if="previewImageUrl" class="mt-4">
            <p class="text-sm font-medium text-gray-700 mb-2">Image Preview:</p>
            <img :src="previewImageUrl" alt="File Preview" class="max-w-full h-auto rounded-lg shadow-md max-h-60 object-contain" />
          </div>
        </div>

        <!-- Visible to Client Checkbox -->
        <div class="flex items-center">
          <input
              id="is_visible_to_client"
              type="checkbox"
              v-model="deliverableForm.is_visible_to_client"
              class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
          />
          <InputLabel for="is_visible_to_client" value="Visible to Client" class="ml-2" />
          <InputError :message="errors.is_visible_to_client ? errors.is_visible_to_client[0] : ''" class="mt-2" />
        </div>
      </div>
    </template>
  </BaseFormModal>
</template>
