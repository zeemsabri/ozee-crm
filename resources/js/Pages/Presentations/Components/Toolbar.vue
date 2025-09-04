<template>
    <div class="w-16 h-full sticky left-0 top-0 z-10 bg-white shadow-sm p-2 flex flex-col items-center justify-start gap-3" role="toolbar" aria-label="Presentation editor toolbar">
        <!-- Preview controls moved here -->
        <button
            @click="togglePreview"
            class="icon-btn"
            :aria-label="store.previewOpen ? 'Hide Preview' : 'Show Preview'"
            :title="store.previewOpen ? 'Hide Preview' : 'Show Preview'"
        >
            <!-- Eye icon -->
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7S3.732 16.057 2.458 12z" />
            </svg>
        </button>
        <button
            @click="toggleExpand"
            class="icon-btn"
            :disabled="!store.previewOpen"
            :aria-label="store.previewMaximized ? 'Restore Preview' : 'Expand Preview'"
            :title="store.previewMaximized ? 'Restore Preview' : 'Expand Preview'"
        >
            <!-- Expand/Restore icon (corners) -->
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4" />
            </svg>
        </button>

        <button
            v-for="action in filteredActions"
            :key="action"
            @click="handleAction(action)"
            class="icon-btn"
            :class="{ 'icon-btn-primary': action === 'save' }"
            :aria-label="actionLabels[action]"
            :title="actionLabels[action]"
            :disabled="isActionDisabled(action)"
        >
            <svg v-if="actionIcons[action]" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path :d="actionIcons[action]" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" />
            </svg>
        </button>

    </div>
    <ShareModal :show="showShare" :presentation="store.presentation" @close="showShare=false" />
</template>

<script setup>
import { ref, computed } from 'vue';
import ShareModal from './ShareModal.vue';
import { usePresentationStore } from '@/Stores/presentationStore';
import api from '@/Services/presentationsApi';
import { success, error } from '@/Utils/notification';

const props = defineProps({
    actions: {
        type: Array,
        default: () => ['save', 'share', 'export', 'collaborate', 'theme'],
    },
});

const store = usePresentationStore();
const saving = ref(false);
const showShare = ref(false);

const actionLabels = {
    save: 'Save',
    share: 'Share',
    export: 'Export PDF',
    collaborate: 'Collaborate',
    theme: 'Toggle Theme',
    saveTemplate: 'Save as Template',
};

const actionIcons = {
    save: 'M17 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V7l-4-4zm-5 16a3 3 0 110-6 3 3 0 010 6zm-3-9H5V5h4v5z',
    share: 'M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7M16 6l-4-4-4 4M12 2v14',
    export: 'M19 12v7H5v-7H3v7a2 2 0 002 2h14a2 2 0 002-2v-7h-2zM13 3v10h-2V3H7l5-2 5 2h-4z',
    collaborate: 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
    theme: 'M12 3v18m-9-9h18',
    saveTemplate: 'M12 4v16m8-8H4',
};

const filteredActions = computed(() => props.actions.filter((action) => actionLabels[action]));

function isActionDisabled(action) {
    if (action === 'save' && saving.value) return true;
    return false;
}

function togglePreview() {
    if (store.previewOpen) {
        store.hidePreview();
    } else {
        store.showPreview();
    }
}

function toggleExpand() {
    if (!store.previewOpen) return;
    store.setPreviewMax(!store.previewMaximized);
}

async function handleAction(action) {
    switch (action) {
        case 'save':
            saving.value = true;
            try {
                await store.updatePresentationTitle(store.presentation.title);
            } finally {
                saving.value = false;
            }
            break;
        case 'share':
            showShare.value = true;
            break;
        case 'export':
            // Placeholder for PDF export using jsPDF or similar
            console.log('Exporting presentation to PDF...');
            break;
        case 'collaborate':
            // Placeholder for initiating collaboration (e.g., WebSocket setup)
            console.log('Starting collaboration session...');
            break;
        case 'theme':
            console.log('Toggle theme clicked');
            break;
        case 'saveTemplate':
            try {
                const created = await api.saveAsTemplate(store.presentation.id);
                success('Template created successfully');
                // Optionally: nothing else
            } catch (e) {
                error('Failed to create template');
            }
            break;
    }
}
</script>

<style scoped>
.icon-btn {
    @apply w-10 h-10 flex items-center justify-center rounded-md bg-gray-100 hover:bg-gray-200 transition-colors;
}
.icon-btn-primary {
    @apply bg-indigo-600 text-white hover:bg-indigo-700;
}
.icon-btn:disabled {
    @apply opacity-50 cursor-not-allowed;
}
</style>
