<script setup>
import { defineProps, computed } from 'vue';

const props = defineProps({
    attachments: {
        type: Array,
        default: () => []
    }
});

// Helper function to convert bytes to a human-readable format
const formatBytes = (bytes, decimals = 2) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};

// Computed property to determine the icon based on file type
const getFileIcon = (mimeType) => {
    if (mimeType.startsWith('image/')) {
        return 'https://placehold.co/40x40/E5E7EB/6B7280?text=IMG';
    } else if (mimeType === 'application/pdf') {
        return 'https://placehold.co/40x40/E5E7EB/6B7280?text=PDF';
    } else if (mimeType.includes('officedocument')) {
        return 'https://placehold.co/40x40/E5E7EB/6B7280?text=DOC';
    } else if (mimeType.includes('spreadsheet')) {
        return 'https://placehold.co/40x40/E5E7EB/6B7280?text=XLS';
    } else if (mimeType.includes('presentation')) {
        return 'https://placehold.co/40x40/E5E7EB/6B7280?text=PPT';
    } else if (mimeType.includes('zip')) {
        return 'https://placehold.co/40x40/E5E7EB/6B7280?text=ZIP';
    } else {
        return 'https://placehold.co/40x40/E5E7EB/6B7280?text=FILE';
    }
};

</script>

<template>
    <div v-if="attachments.length > 0">
        <h4 class="text-lg font-medium text-gray-900 mb-2">Attachments</h4>
        <ul class="divide-y divide-gray-200">
            <li v-for="attachment in attachments" :key="attachment.id" class="py-2 flex items-center space-x-4">
                <img
                    v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')"
                    :src="attachment.thumbnail_url"
                    alt="Attachment thumbnail"
                    class="h-10 w-10 flex-shrink-0 rounded-md object-cover"
                />
                <img
                    v-else
                    :src="getFileIcon(attachment.mime_type)"
                    alt="File icon"
                    class="h-10 w-10 flex-shrink-0"
                />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ attachment.filename }}</p>
                    <p class="text-sm text-gray-500">{{ attachment.mime_type }} - {{ formatBytes(attachment.file_size) }}</p>
                </div>
                <div class="flex-shrink-0 flex items-center space-x-2">
                    <a :href="attachment.path_url" target="_blank" class="text-blue-600 hover:underline text-sm font-medium">View</a>
                    <a :href="attachment.path_url" :download="attachment.filename" class="text-indigo-600 hover:underline text-sm font-medium">Download</a>
                </div>
            </li>
        </ul>
    </div>
    <div v-else class="text-gray-500">No attachments found.</div>
</template>
