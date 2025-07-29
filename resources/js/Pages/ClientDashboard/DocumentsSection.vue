<script setup>
import { ref, onMounted, inject, watch } from 'vue';
import TicketNotesSidebar from "@/Pages/ClientDashboard/TicketNotesSidebar.vue";

const props = defineProps({
    initialAuthToken: {
        type: String,
        required: true,
    },
    projectId: {
        type: [String, Number],
        required: true,
    },
    documents: { // This prop will initially be populated from ClientDashboard.vue's fetch
        type: Array,
        default: () => []
    },
    projectData: {
        type: [Object, null],
        default: () => ({})
    }
});

const emits = defineEmits(['add-activity']);

const isLoading = ref(true); // Local loading state for fetching documents
const isUploading = ref(false); // New: Loading state for upload process
const selectedFiles = ref([]); // New: Stores files selected by user
const apiError = ref(null); // Local error state for this section
const documentsList = ref([]); // New: Reactive list of documents to display
const showNotesSidebar = ref(false);
const selectedDocument = ref(null);

// Inject the showModal from ClientDashboard for showing alerts
const { showModal } = inject('modalService');
const { addActivity } = inject('activityService'); // Inject addActivity

// Function to fetch documents from the API
const fetchDocuments = async () => {
    isLoading.value = true;
    apiError.value = null;
    try {
        const response = await fetch(`/api/client-api/project/${props.projectId}/documents`, {
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMessage = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Failed to fetch documents.');
            throw new Error(errorMessage);
        }

        documentsList.value = data; // Update reactive list
        isLoading.value = false;

    } catch (err) {
        console.error("Error fetching documents:", err);
        apiError.value = err.message || 'An unexpected error occurred while fetching documents.';
        showModal('Error', apiError.value, 'alert');
    } finally {
        isLoading.value = false;
    }
};

// Handle file input change
const handleFileChange = (event) => {
    selectedFiles.value = Array.from(event.target.files);
    apiError.value = null; // Clear previous errors on new file selection
};

const openNotesSidebar = (task) => {
    selectedDocument.value = task;
    showNotesSidebar.value = true;
};


// Method to handle a new note being added (triggers a re-fetch of tasks to get updated notes)
const handleNoteAdded = () => {
    emits('add-activity', 'A new note was added to a task.'); // Log activity to dashboard
};

// Function to handle document upload
const handleUploadDocuments = async () => {
    if (selectedFiles.value.length === 0) {
        showModal('No Files Selected', 'Please select one or more files to upload.', 'alert');
        return;
    }

    isUploading.value = true;
    apiError.value = null; // Clear previous errors

    const formData = new FormData();
    selectedFiles.value.forEach(file => {
        formData.append('documents[]', file); // Append each file to 'documents[]'
    });

    try {
        const response = await fetch(`/api/client-api/documents`, { // Use the new client-specific upload endpoint
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${props.initialAuthToken}`,
                // 'Content-Type': 'multipart/form-data' is automatically set by browser for FormData
                'Accept': 'application/json'
            },
            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            const errorMessage = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Failed to upload documents.');
            throw new Error(errorMessage);
        }

        // Add newly uploaded documents to the existing list
        documentsList.value = [...documentsList.value, ...data.documents];
        documentsList.value.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()); // Sort by newest first

        selectedFiles.value = []; // Clear selected files
        // Clear the file input element manually if needed (e.g., if it's a ref to the input)
        const fileInput = document.getElementById('document-upload-input');
        if (fileInput) {
            fileInput.value = '';
        }

        showModal('Upload Successful', 'Your document(s) have been uploaded successfully!', 'alert');
        addActivity(`Uploaded ${data.documents.length} new document(s).`);

    } catch (err) {
        console.error("Error uploading documents:", err);
        apiError.value = err.message || 'An unexpected error occurred during upload.';
        showModal('Upload Error', apiError.value, 'alert');
    } finally {
        isUploading.value = false;
    }
};

// Helper to get file icon based on mime type
const getFileIcon = (mimeType) => {
    if(!mimeType) {
        return '📄'
    }
    if (mimeType.includes('pdf')) return '📄';
    if (mimeType.includes('image')) return '🖼️';
    if (mimeType.includes('word')) return '📝';
    if (mimeType.includes('spreadsheet')) return '📊';
    if (mimeType.includes('presentation')) return '📽️';
    return '📁'; // Default folder icon
};

// Initial data load
onMounted(() => {
    fetchDocuments();
});

// Watch the prop 'documents' coming from the parent and update local list IF it's provided
// This might be useful if parent fetches initial data directly
watch(() => props.documents, (newDocs) => {
    if (newDocs && newDocs.length > 0 && documentsList.value.length === 0) {
        documentsList.value = [...newDocs];
    }
}, { immediate: true });
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md min-h-[calc(100vh-6rem)] relative">

        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800">{{ projectData.name ?? 'Project' }} Documents</h2>
            <a v-if="projectData.google_drive_link" :href="projectData.google_drive_link" target="_blank" rel="noopener noreferrer"
               class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 ease-in-out text-sm">
                Manage in Google Drive
            </a>
        </div>
        <!-- Document Upload Section -->
        <div class="mb-8 p-6 border border-blue-200 bg-blue-50 rounded-lg shadow-sm">
            <h3 class="text-xl font-semibold text-blue-800 mb-4">Upload New Documents</h3>
            <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4">
                <input type="file"
                       id="document-upload-input"
                       multiple
                       @change="handleFileChange"
                       class="flex-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-white focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                />
                <button @click="handleUploadDocuments"
                        :disabled="isUploading || selectedFiles.length === 0"
                        class="bg-green-600 text-white py-2 px-6 rounded-lg hover:bg-green-700 transition-colors shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center min-w-[120px]"
                >
                    <span v-if="isUploading" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Uploading...
                    </span>
                    <span v-else>Upload Files</span>
                </button>
            </div>
            <p v-if="selectedFiles.length > 0" class="mt-2 text-sm text-gray-600">
                Selected: {{ selectedFiles.length }} file(s)
            </p>
            <p v-if="apiError" class="mt-2 text-sm text-red-600">{{ apiError }}</p>
        </div>

        <!-- Documents List Section -->
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Uploaded Documents</h3>
        <div v-if="isLoading" class="text-center text-gray-600 py-8">Loading documents...</div>
        <div v-else-if="documentsList.length === 0" class="text-center text-gray-500 py-8">
            No documents have been uploaded for this project yet.
        </div>
        <div v-else class="overflow-x-auto rounded-lg shadow">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                <tr class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                    <th class="py-3 px-6 text-left">File Name</th>
                    <th class="py-3 px-6 text-left">Uploaded On</th>
                    <th class="py-3 px-6 text-center">Actions</th>
                </tr>
                </thead>
                <tbody class="text-gray-700 text-sm font-light">
                <tr v-for="doc in documentsList" :key="doc.id" class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="py-3 px-6 text-left flex items-center">
                        <img v-if="doc.thumbnail" :src="doc.thumbnail" :alt="doc.filename" loading="lazy">
                        <span v-else>{{ getFileIcon(doc.mime_type) }} {{ doc.filename }}</span>
                    </td>
                    <td class="py-3 px-6 text-left">{{ new Date(doc.created_at).toLocaleDateString() }}</td>
                    <td class="py-3 px-6 text-center items-center space-x-2">
                        <a v-if="doc.path" :href="doc.path" target="_blank" rel="noopener noreferrer"
                           class="bg-blue-500 text-white py-1 px-3 rounded-lg hover:bg-blue-600 transition-colors text-xs font-semibold"
                        >
                            Download
                        </a>
                        <a @click="openNotesSidebar(doc)" rel="noopener noreferrer"
                           class="bg-blue-500 text-white py-1 px-3 rounded-lg hover:bg-blue-600 transition-colors text-xs font-semibold"
                        >
                            Add Notes
                        </a>
                        <!-- Potentially add download or delete actions here -->
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Task Notes Sidebar Component -->
        <TicketNotesSidebar
            v-model:isOpen="showNotesSidebar"
            :selected-item="selectedDocument"
            :initialAuthToken="initialAuthToken"
            note-for="document"
            :projectId="projectId"
            @note-added-success="handleNoteAdded"
        />

    </div>
</template>

<style scoped>
/* Custom file input styling if desired, but Tailwind's `file:` utilities cover most cases */
</style>
