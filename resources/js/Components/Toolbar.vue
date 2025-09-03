<template>
    <div class="w-16 h-full sticky left-0 top-0 z-10 bg-white shadow-sm p-2 flex flex-col items-center justify-start gap-3" role="toolbar" aria-label="Presentation editor toolbar">
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
        <div class="mt-auto mb-1">
            <span v-if="saving" class="text-[10px] text-gray-400">Saving…</span>
            <span v-else class="text-[10px] text-gray-400">Saved</span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';

const props = defineProps({
    actions: {
        type: Array,
        default: () => ['save', 'export', 'collaborate', 'theme'],
    },
});

const store = usePresentationStore();
const saving = ref(false);

const actionLabels = {
    save: 'Save',
    export: 'Export PDF',
    collaborate: 'Collaborate',
    theme: 'Toggle Theme',
};

const actionIcons = {
    save: 'M17 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V7l-4-4zm-5 16a3 3 0 110-6 3 3 0 010 6zm-3-9H5V5h4v5z',
    export: 'M19 12v7H5v-7H3v7a2 2 0 002 2h14a2 2 0 002-2v-7h-2zM13 3v10h-2V3H7l5-2 5 2h-4z',
    collaborate: 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
    theme: 'M12 3v18m-9-9h18',
};

const filteredActions = computed(() => props.actions.filter((action) => actionLabels[action]));

function isActionDisabled(action) {
    if (action === 'save' && saving.value) return true;
    return false;
}

async function handleAction(action) {
    switch (action) {
        case 'save':
            saving.value = true;
            try {
                await store.addSlide();
            } finally {
                saving.value = false;
            }
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
