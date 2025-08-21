<script setup>
import { defineProps, defineEmits, computed } from 'vue';
import { PaperClipIcon, TrashIcon, EyeIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { PhotoIcon, DocumentIcon, DocumentTextIcon, DocumentMagnifyingGlassIcon, ArchiveBoxIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    attachments: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['delete-attachment']);

// Helper function to convert bytes to a human-readable format
const formatBytes = (bytes, decimals = 2) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};

const getFileIcon = (mimeType) => {
    if (mimeType.startsWith('image/')) {
        return PhotoIcon;
    } else if (mimeType === 'application/pdf') {
        return DocumentTextIcon;
    } else if (mimeType.includes('zip') || mimeType.includes('rar')) {
        return ArchiveBoxIcon;
    } else if (mimeType.includes('text/') || mimeType.includes('word') || mimeType.includes('excel')) {
        return DocumentMagnifyingGlassIcon;
    } else {
        return DocumentIcon;
    }
};

const getFileNameAndExtension = (filename) => {
    const parts = filename.split('.');
    const extension = parts.pop();
    const name = parts.join('.');
    return { name, extension };
};
</script>

<template>
    <div v-if="attachments.length > 0">
        <h4 class="text-lg font-medium text-gray-900 mb-2">Attachments</h4>
        <ul class="divide-y divide-gray-200">
            <li v-for="attachment in attachments" :key="attachment.id" class="py-2 flex items-center space-x-4">
                <img v-if="attachment.mime_type.startsWith('image/')" :src="attachment.thumbnail_url" class="h-10 w-10 flex-shrink-0 object-cover rounded-md" alt="Attachment Thumbnail" />
                <component v-else :is="getFileIcon(attachment.mime_type)" class="h-10 w-10 text-gray-400 flex-shrink-0" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ attachment.filename }}</p>
                    <p class="text-sm text-gray-500">{{ attachment.mime_type }} - {{ formatBytes(attachment.file_size) }}</p>
                </div>
                <div class="flex-shrink-0 flex items-center space-x-2">
                    <a :href="attachment.path_url" target="_blank" class="text-blue-600 hover:text-blue-800 transition-colors">
                        <EyeIcon class="h-5 w-5" />
                    </a>
                    <a :href="attachment.path_url" :download="attachment.filename" class="text-indigo-600 hover:text-indigo-800 transition-colors">
                        <ArrowDownTrayIcon class="h-5 w-5" />
                    </a>
                    <button @click="$emit('delete-attachment', attachment.id)" class="text-red-600 hover:text-red-800 transition-colors">
                        <TrashIcon class="h-5 w-5" />
                    </button>
                </div>
            </li>
        </ul>
    </div>
    <div v-else class="text-gray-500">No attachments found.</div>
</template>
