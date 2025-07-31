<script setup>
import { ref, onMounted, inject, watch, computed } from 'vue';
import TicketNotesSidebar from "./TicketNotesSidebar.vue"; // Assuming relative path or alias is set up

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
const isUploading = ref(false); // Loading state for upload process
const selectedFiles = ref([]); // Stores files selected by user
const apiError = ref(null); // Local error state for this section
const documentsList = ref([]); // Reactive list of documents to display
const documentSearchQuery = ref(''); // New: Search query for documents

const showNotesSidebar = ref(false);
const selectedDocument = ref(null);

// Inject the showModal from ClientDashboard for showing alerts
const { showModal } = inject('modalService');
const { addActivity } = inject('activityService'); // Inject addActivity (assuming it's provided)


// Computed property for filtered documents
const filteredDocuments = computed(() => {
    if (!documentSearchQuery.value) {
        return documentsList.value;
    }
    const query = documentSearchQuery.value.toLowerCase();
    return documentsList.value.filter(doc =>
        doc.filename.toLowerCase().includes(query) ||
        (doc.notes && doc.notes.some(note => note.content.toLowerCase().includes(query)))
    );
});

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

// Open notes sidebar for a document
const openNotesSidebar = (doc) => {
    selectedDocument.value = doc;
    showNotesSidebar.value = true;
};

// Method to handle a new note being added (triggers a re-fetch to get updated notes)
const handleNoteAdded = () => {
    fetchDocuments(); // Re-fetch documents to ensure the latest notes are displayed
    emits('add-activity', 'A new note was added to a document.'); // Log activity to dashboard
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
    formData.append('project_id', props.projectId); // Ensure project_id is sent

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

        // Add newly uploaded documents to the existing list and sort
        documentsList.value = [...documentsList.value, ...data.documents];
        documentsList.value.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()); // Sort by newest first

        selectedFiles.value = []; // Clear selected files
        // Clear the file input element manually if needed
        const fileInput = document.getElementById('document-upload-input');
        if (fileInput) {
            fileInput.value = '';
        }

        showModal('Upload Successful', 'Your document(s) have been uploaded successfully!', 'alert');
        if (addActivity) { // Check if addActivity is injected
            addActivity(`Uploaded ${data.documents.length} new document(s).`);
        }

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
    if (!mimeType) {
        return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file text-gray-500"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>`;
    }
    if (mimeType.includes('pdf')) return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text text-red-500"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`;
    if (mimeType.includes('image')) return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-image text-purple-500"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`;
    if (mimeType.includes('wordprocessingml') || mimeType.includes('msword')) return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-type-doc text-blue-500"><path d="M14.5 22H18a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8.5"/><path d="M14 2v6a2 2 0 0 0 2 2h6"/><path d="M8 12h4"/><path d="M8 16h4"/><path d="M8 20h4"/></svg>`;
    if (mimeType.includes('spreadsheetml') || mimeType.includes('excel')) return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-spreadsheet text-green-600"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M8 17h2"/><path d="M14 13h2"/><path d="M14 17h2"/></svg>`;
    if (mimeType.includes('presentationml') || mimeType.includes('powerpoint')) return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-type-ppt text-orange-500"><path d="M14.5 22H18a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h8.5"/><path d="M14 2v6a2 2 0 0 0 2 2h6"/><path d="M10 12h4"/><path d="M12 12v6"/></svg>`;
    return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file text-gray-500"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>`; // Generic file icon
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
    <div class="p-6 bg-white rounded-xl shadow-lg font-inter min-h-[calc(100vh-6rem)]">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open mr-2 w-6 h-6"><path d="M6 14H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h2.5L7 4.5 8.5 3h8A2 2 0 0 1 19 5v2"/><path d="M22 10H10c-.6 0-1 .4-1 1v9c0 .6.4 1 1 1h12c.6 0 1-.4 1-1V11c0-.6-.4-1-1-1Z"/></svg>
                {{ projectData.name ?? 'Project' }} Documents
            </h2>
            <a v-if="projectData.google_drive_link" :href="projectData.google_drive_link" target="_blank" rel="noopener noreferrer"
               class="bg-indigo-600 text-white py-2 px-5 rounded-lg font-semibold hover:bg-indigo-700 transition-all duration-200 ease-in-out transform hover:scale-105 shadow-md flex items-center"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-folder-open mr-2"><path d="M6 14H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h2.5L7 4.5 8.5 3h8A2 2 0 0 1 19 5v2"/><path d="M22 10H10c-.6 0-1 .4-1 1v9c0 .6.4 1 1 1h12c.6 0 1-.4 1-1V11c0-.6-.4-1-1-1Z"/></svg>
                Open in Google Drive
            </a>
        </div>

        <!-- Document Upload Section -->
        <div class="mb-8 p-6 border border-blue-200 bg-blue-50 rounded-xl shadow-md">
            <h3 class="text-xl font-bold text-blue-800 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload mr-2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                Upload New Documents
            </h3>
            <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4">
                <input type="file"
                       id="document-upload-input"
                       multiple
                       @change="handleFileChange"
                       class="flex-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-white focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 transition-colors duration-200"
                />
                <button @click="handleUploadDocuments"
                        :disabled="isUploading || selectedFiles.length === 0"
                        class="bg-green-600 text-white py-2.5 px-6 rounded-lg font-semibold hover:bg-green-700 transition-all duration-200 ease-in-out transform hover:scale-105 shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center min-w-[120px]"
                >
                    <span v-if="isUploading" class="flex items-center">
                        <svg class="animate-spin h-5 w-5 text-white mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Uploading...
                    </span>
                    <span v-else class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-plus mr-2"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>
                        Upload Files
                    </span>
                </button>
            </div>
            <p v-if="selectedFiles.length > 0" class="mt-3 text-sm text-gray-700">
                Selected: <span class="font-semibold">{{ selectedFiles.length }} file(s)</span>
            </p>
            <p v-if="apiError" class="mt-3 text-sm text-red-600 font-semibold">{{ apiError }}</p>
        </div>

        <!-- Documents List Section -->
        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-files mr-2"><path d="M15.5 2H8.6c-.4 0-.8.2-1.1.5-.3.3-.5.7-.5 1.1v12.8c0 .4.2.8.5 1.1.3.3.7.5 1.1.5H20.4c.4 0 .8-.2 1.1-.5.3-.3.5-.7.5-1.1V6.5L15.5 2z"/><path d="M3 7.6v12.8c0 .4.2.8.5 1.1.3.3.7.5 1.1.5h9.8"/><path d="M15 2v5h5"/></svg>
            All Project Documents
        </h3>

        <!-- Document Search Bar -->
        <div class="relative mb-6">
            <input
                type="text"
                v-model="documentSearchQuery"
                placeholder="Search documents by file name or notes..."
                class="w-full p-3 pl-10 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-200"
                aria-label="Search Documents"
            >
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search text-gray-400 w-5 h-5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </div>
        </div>

        <!-- Conditional rendering based on loading, errors, or empty state -->
        <div v-if="isLoading" class="text-center text-gray-600 py-12">
            <svg class="animate-spin h-8 w-8 text-indigo-500 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p>Loading documents...</p>
        </div>
        <div v-else-if="apiError && !isLoading" class="text-center text-red-600 py-12">
            <p class="font-semibold mb-2">Error loading documents:</p>
            <p>{{ apiError }}</p>
        </div>
        <div v-else-if="documentsList.length === 0" class="text-center text-gray-500 py-12">
            <p class="text-lg mb-2">No documents have been uploaded for this project yet.</p>
            <p>Use the "Upload New Documents" section above to add your files.</p>
        </div>
        <div v-else-if="filteredDocuments.length === 0 && documentSearchQuery" class="text-center text-gray-500 py-12">
            <p class="text-lg mb-2">No documents match your search "{{ documentSearchQuery }}".</p>
            <p>Try a different keyword or clear your search.</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="doc in filteredDocuments" :key="doc.id"
                 class="bg-gray-50 rounded-lg shadow-sm border border-gray-200 flex overflow-hidden hover:shadow-md transition-shadow duration-200 h-40">
                <!-- Thumbnail/Icon on Left -->
                <div class="w-1/3 flex-shrink-0 bg-gray-200 flex items-center justify-center relative">
                    <img v-if="doc.thumbnail" :src="doc.thumbnail" :alt="doc.filename"
                         class="absolute inset-0 w-full h-full object-cover" loading="lazy"
                         onerror="this.onerror=null;this.src='https://placehold.co/150x160/E2E8F0/64748B?text=File';" />
                    <div v-else v-html="getFileIcon(doc.mime_type)" class="text-gray-500 w-12 h-12"></div>
                </div>

                <!-- Information on Right -->
                <div class="flex-1 p-4 flex flex-col justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 line-clamp-2 mb-1">{{ doc.filename }}</h3>
                        <div class="text-sm text-gray-600 mb-2">
                            <div class="flex items-center mb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar mr-1 text-gray-500"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                                <span>Uploaded: {{ new Date(doc.created_at).toLocaleDateString() }}</span>
                            </div>
                            <div v-if="doc.notes && doc.notes.length > 0" class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square mr-1 text-gray-500"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V3a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <span>{{ doc.notes.length }} Note(s)</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex space-x-2 mt-auto">
                        <a v-if="doc.path" :href="doc.path" target="_blank" rel="noopener noreferrer"
                           class="flex-1 bg-blue-600 text-white py-2 px-3 rounded-lg font-semibold text-xs hover:bg-blue-700 transition-all duration-200 ease-in-out transform hover:scale-105 shadow-md flex items-center justify-center"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download mr-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                            Download
                        </a>
                        <button @click="openNotesSidebar(doc)"
                                class="flex-1 bg-gray-300 text-gray-800 py-2 px-3 rounded-lg font-semibold text-xs hover:bg-gray-400 transition-all duration-200 ease-in-out transform hover:scale-105 shadow-md flex items-center justify-center"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-square-plus mr-1"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V3a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M12 7v6"/><path d="M9 10h6"/></svg>
                            Add Note
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Notes Sidebar Component (re-used for documents) -->
        <TicketNotesSidebar
            v-model:isOpen="showNotesSidebar"
            :selected-item="selectedDocument"
            :initialAuthToken="initialAuthToken"
            note-for="documents"
            :projectId="projectId"
            @note-added-success="handleNoteAdded"
        />
    </div>
</template>

<style scoped>
.font-inter {
    font-family: 'Inter', sans-serif;
}
/* Custom file input styling */
input[type="file"]::file-selector-button {
    cursor: pointer;
}
/* Specific styling for search input to place icon inside */
.relative input[type="text"] {
    padding-left: 2.5rem; /* Adjust padding to make space for the icon */
}
</style>
