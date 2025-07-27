<script setup>
import { ref, inject, defineProps, defineEmits } from 'vue';

const props = defineProps(['documents']);
const emits = defineEmits(['add-document', 'add-activity']);

const fileInputRef = ref(null);
const fileNameDisplay = ref('No file chosen');
const { showModal } = inject('modalService');

const displayFileName = () => {
    if (fileInputRef.value.files.length > 0) {
        fileNameDisplay.value = fileInputRef.value.files[0].name;
    } else {
        fileNameDisplay.value = "No file chosen";
    }
};

const handleFileUpload = () => {
    if (!fileInputRef.value.files[0]) {
        showModal('No File Selected', 'Please select a file to upload.', 'alert');
        return;
    }

    const file = fileInputRef.value.files[0];
    const newDocument = {
        id: Date.now(),
        fileName: file.name,
        fileType: file.type || 'N/A',
        fileUrl: `https://placehold.co/150x100/A855F7/ffffff?text=File+Preview`, // Placeholder URL
        uploadDate: new Date().toISOString(),
    };

    emits('add-document', newDocument); // Emit event to parent
    emits('add-activity', `Uploaded document: ${file.name}`); // Emit activity

    fileInputRef.value.value = ""; // Clear the input
    fileNameDisplay.value = "No file chosen";
    showModal('Success', `Document "${file.name}" uploaded successfully! (Note: File content not stored in this demo)`, 'alert');
};
</script>

<template>
    <div id="documents" class="section">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Shared Documents</h2>
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Upload New Document</h3>
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center mb-4">
                <input type="file" id="file-upload" class="hidden" ref="fileInputRef" @change="displayFileName" />
                <label for="file-upload" class="cursor-pointer text-blue-600 hover:underline">Choose a file</label>
                <p id="file-name-display" class="text-sm text-gray-500 mt-2">{{ fileNameDisplay }}</p>
                <p class="text-sm text-gray-500 mt-1">or drag and drop here (PDF, DOCX, Images, etc.)</p>
            </div>
            <button @click="handleFileUpload" class="bg-blue-600 text-white py-2 px-6 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Upload Document</button>
        </div>
        <div class="bg-white rounded-lg shadow-md overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upload Date</th>
                    <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="props.documents.length === 0">
                    <td colspan="4" class="p-4 text-center text-gray-500">No documents found.</td>
                </tr>
                <tr v-for="docItem in props.documents" :key="docItem.id" class="border-t hover:bg-gray-50">
                    <td class="p-4 whitespace-nowrap">{{ docItem.fileName }}</td>
                    <td class="p-4 whitespace-nowrap">{{ docItem.fileType }}</td>
                    <td class="p-4 whitespace-nowrap">{{ docItem.uploadDate ? new Date(docItem.uploadDate).toLocaleDateString() : 'N/A' }}</td>
                    <td class="p-4 whitespace-nowrap">
                        <a :href="docItem.fileUrl || '#'" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline mr-3">View</a>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed, or rely on Tailwind CSS */
</style>
