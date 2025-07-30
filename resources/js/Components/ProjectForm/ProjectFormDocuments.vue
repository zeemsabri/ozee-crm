<script setup>
import { computed, ref, onMounted, watch, reactive } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { success, error } from '@/Utils/notification';
import { fetchProjectSectionData } from '@/Components/ProjectForm/useProjectData';

const props = defineProps({
  projectId: { // Now accepts projectId directly
    type: [Number, String],
    required: true
  },
  errors: {
    type: Object,
    default: () => ({})
  },
  canUploadProjectDocuments: {
    type: Boolean,
    default: false
  },
  canViewProjectDocuments: {
    type: Boolean,
    default: false
  },
  isSaving: { // Overall page saving state
    type: Boolean,
    default: false
  }
});

// Local reactive state for documents
const localDocuments = reactive({
  documents: [] // Array of file objects or document URLs/paths
});

// Ref for the file input element to clear it after upload
const documentFileInput = ref(null);
const isUploading = ref(false); // Local saving state for document uploads


// Function to fetch documents data for this specific tab
const fetchDocumentsData = async () => {
  if (!props.projectId) return;

  try {
    const data = await fetchProjectSectionData(props.projectId, 'documents', {
      canViewProjectDocuments: props.canViewProjectDocuments,
    });
    if (data) {
      localDocuments.documents = data || [];
    }
  } catch (err) {
    console.error('Error fetching documents data:', err);
    error('Failed to load documents data.');
  }
};


// Watch for projectId changes to re-fetch data
watch(() => props.projectId, async (newId) => {
  if (newId) {
    await fetchDocumentsData();
  }
}, { immediate: true }); // Immediate ensures it runs on initial mount too

/**
 * Handles the change event of the file input.
 * Adds selected files to the localDocuments.documents array, keeping existing document objects.
 * @param {Event} event - The change event from the file input.
 */
const handleFileChange = (event) => {
  const files = Array.from(event.target.files);
  if (files.length > 0) {
    // Filter out existing document objects that have a 'path' property (already uploaded)
    const existingDocs = Array.isArray(localDocuments.documents)
        ? localDocuments.documents.filter(doc => typeof doc === 'object' && 'path' in doc)
        : [];
    // Concatenate existing documents with newly selected files (File objects)
    localDocuments.documents = [...existingDocs, ...files];
  }
};

/**
 * Initiates the document upload process by directly making the API call.
 * Clears the file input after emitting.
 */
const submitDocuments = async () => {
  if (!props.projectId) {
    error('Project ID is missing. Cannot upload documents.');
    return;
  }
  // Updated Fix: Check for file-like properties instead of instanceof File
  const filesToUpload = localDocuments.documents.filter(doc =>
      doc && typeof doc === 'object' && 'name' in doc && 'size' in doc && 'type' in doc
  );

  if (!filesToUpload || filesToUpload.length === 0) {
    error('Please select documents to upload.');
    return;
  }

  isUploading.value = true;


  try {
    const formData = new FormData();
    filesToUpload.forEach((file, index) => {
      formData.append(`documents[${index}]`, file);
    });
    const response = await window.axios.post(
        `/api/projects/${props.projectId}/documents`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
    );
    localDocuments.documents = response.data.documents || []; // Update with new list of documents
    success('Documents uploaded successfully!');
    // Re-fetch documents after upload to ensure consistency
    await fetchDocumentsData();
  } catch (err) {
    error('Failed to upload documents.');
    console.error('Error uploading documents:', err);
  } finally {
    isUploading.value = false;
    if (documentFileInput.value) {
      documentFileInput.value.value = ''; // Clear the file input
    }
  }
};

/**
 * Removes a document (either a new file or an already uploaded one) from the local form array.
 * Note: Deleting an already uploaded document from the server would require another API call
 * and confirmation, which is outside the scope of this refactoring, but a placeholder is left.
 * @param {number} index - The index of the document to remove.
 */
const removeDocument = (index) => {
  // Create a new array without the document at the given index
  localDocuments.documents = localDocuments.documents.filter((_, i) => i !== index);
  // TODO: If this was an already uploaded document, you might need a confirmation
  // and an API call to delete it from storage/database.
  // For now, it just removes it from the list in the form.
};


// Initial data fetch on component mount
onMounted(() => {
  // The watch handler with { immediate: true } will handle the initial fetch
  // when props.projectId is first available.
});
</script>

<template>
  <div class="space-y-6">
    <!-- Document Upload Section -->
    <div v-if="canUploadProjectDocuments" class="bg-gray-50 p-6 rounded-lg shadow-inner border border-gray-200">
      <InputLabel for="documents-upload" value="Upload New Documents" class="mb-2 text-lg" />
      <input
          type="file"
          id="documents-upload"
          ref="documentFileInput"
          @change="handleFileChange"
          class="mt-2 block w-full text-sm text-gray-600
                       file:mr-4 file:py-2.5 file:px-5 file:rounded-full file:border-0
                       file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700
                       hover:file:bg-indigo-100 transition-colors duration-200 cursor-pointer"
          multiple
          accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif"
      />
      <p class="text-sm text-gray-500 mt-2">Supported formats: PDF, DOC, DOCX, JPG, JPEG, PNG, GIF. Max file size: 5MB.</p>
      <InputError :message="errors.documents ? errors.documents[0] : ''" class="mt-2" />

      <div class="mt-6">
        <PrimaryButton
            type="button"
            @click="submitDocuments"
            :disabled="!projectId || !localDocuments.documents || !localDocuments.documents.some(doc => doc && typeof doc === 'object' && 'name' in doc && 'size' in doc && 'type' in doc) || isUploading || isSaving"
            class="px-6 py-3 rounded-lg text-lg shadow-md hover:shadow-lg transition-all duration-200"
        >
          <span v-if="isUploading">Uploading...</span>
          <span v-else>Upload Selected Documents</span>
        </PrimaryButton>
        <p v-if="!projectId" class="text-sm text-red-500 mt-3">
          Please save the project first before uploading documents.
        </p>
      </div>
    </div>

    <!-- Existing Documents Display -->
    <div v-if="canViewProjectDocuments && localDocuments.documents && localDocuments.documents.length > 0" class="mt-8 bg-white p-6 rounded-lg shadow-md border border-gray-200">
      <h3 class="text-xl font-semibold text-gray-800 mb-5">Existing Project Documents</h3>
      <ul class="space-y-3">
        <li v-for="(doc, index) in localDocuments.documents" :key="index"
            class="flex items-center justify-between p-4 bg-gray-50 rounded-md border border-gray-100 shadow-sm transition-shadow hover:shadow-md duration-200">
          <div class="flex items-center">
            <!-- File icon based on type -->
            <span class="mr-3 text-indigo-500">
                            <template v-if="typeof doc === 'object' && 'type' in doc && doc.type.includes('image')">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 002.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </template>
                            <template v-else-if="typeof doc === 'object' && 'type' in doc && doc.type.includes('pdf')">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </template>
                            <template v-else-if="typeof doc === 'object' && 'type' in doc && (doc.type.includes('word') || doc.type.includes('document'))">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            </template>
                            <template v-else>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            </template>
                        </span>

            <a v-if="typeof doc === 'object' && 'path' in doc"
               :href="`/storage/${doc.path}`" target="_blank"
               class="text-blue-600 hover:text-blue-800 hover:underline font-medium break-all mr-4">
              {{ doc.filename }}
            </a>
            <span v-else class="text-gray-700 font-medium break-all mr-4">
                            {{ doc.name }} <span class="text-gray-500 text-sm">(Pending Upload)</span>
                        </span>
          </div>

          <!-- Remove Button (only if user can upload documents) -->
          <button
              v-if="canUploadProjectDocuments"
              type="button"
              @click="removeDocument(index)"
              class="p-2 rounded-full text-red-500 hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200"
              title="Remove document"
              :disabled="isUploading || isSaving"
          >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </li>
      </ul>
    </div>
    <div v-else-if="canViewProjectDocuments" class="p-6 bg-gray-50 rounded-lg text-gray-600 text-center border border-gray-200 shadow-sm">
      No documents found for this project.
    </div>

    <div class="mt-6 p-4 bg-blue-50 rounded-lg text-blue-700 text-sm border border-blue-200">
      <p>Note: Uploaded documents will be stored in the project's designated Google Drive folder.</p>
    </div>
  </div>
</template>
