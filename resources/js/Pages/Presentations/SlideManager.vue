<template>
    <div class="p-4 h-full overflow-y-auto">
        <div class="flex items-center mb-4 gap-3">
            <input
                v-model="search"
                placeholder="Search slides..."
                class="w-25 sm:w-56 md:w-56 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                aria-label="Search slides"
            />
            <button @click="add" class="btn btn-primary" aria-label="Add new slide" :disabled="isAdding">
                {{ isAdding ? '...' : 'Add' }}
            </button>
            <button
                @click="openAiModal"
                class="btn bg-purple-100 text-purple-700 hover:bg-purple-200 flex items-center gap-2"
                :disabled="!canUseAI"
                aria-label="Generate slide with AI"
                title="Generate slide with AI"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                    <path d="M12 2a5 5 0 00-5 5v1.1A5.002 5.002 0 002 13a5 5 0 005 5h.1A5.002 5.002 0 0013 22a5 5 0 005-5v-.1A5.002 5.002 0 0022 11a5 5 0 00-5-5h-.1A5.002 5.002 0 0012 2z" />
                </svg>
                AI
            </button>
        </div>
        <draggable
            v-model="draggableSlides"
            item-key="id"
            class="grid grid-cols-1 gap-4"
            handle=".drag-handle"
            @end="onDragEnd"
            role="list"
            aria-label="Slides list"
        >
            <template #item="{ element: s }">
                <div
                    :class="{ 'bg-indigo-50 border-indigo-300': s.id === selectedId, 'border-transparent': s.id !== selectedId }"
                    class="p-4 border rounded-lg shadow-sm cursor-pointer hover:shadow-md transition-all duration-200"
                    @click="select(s.id)"
                    role="listitem"
                    tabindex="0"
                    @keydown.enter="select(s.id)"
                >
                    <slide-thumbnail :slide="s" class="mb-2" aria-label="Slide thumbnail" />
                    <div class="flex justify-between items-center">
                        <span class="truncate font-medium text-gray-700 drag-handle cursor-move flex-1">
                          #{{ s.display_order }} · {{ s.title || s.template_name }}
                        </span>
                        <div class="flex gap-2">
                            <button @click.stop="remove(s)" class="btn btn-xs text-red-500" aria-label="Delete slide">Delete</button>
                        </div>
                    </div>
                </div>
            </template>
        </draggable>

        <BaseFormModal
            :show="showAiModal"
            title="Generate slide with AI"
            :api-endpoint="aiEndpoint"
            http-method="post"
            :form-data="aiForm"
            submit-button-text="Generate"
            success-message="AI slide generated"
            :before-submit="beforeAiSubmit"
            @close="showAiModal = false"
            @submitted="onAiSubmitted"
        >
            <template #default="{ errors }">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prompt</label>
                        <textarea
                            v-model="aiForm.prompt"
                            class="w-full border border-gray-300 rounded-md p-2 min-h-[120px] focus:ring-2 focus:ring-indigo-500"
                            placeholder="Describe the slide you want to generate..."
                            aria-label="AI prompt"
                            required
                        ></textarea>
                        <p v-if="errors.prompt" class="text-sm text-red-600 mt-1">{{ errors.prompt?.[0] || errors.prompt }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Template</label>
                        <select
                            v-model="aiForm.template_name"
                            class="w-full border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-indigo-500 bg-white"
                            aria-label="Slide template"
                        >
                            <option v-for="opt in templateOptions" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                        <p v-if="errors.template_name" class="text-sm text-red-600 mt-1">{{ errors.template_name?.[0] || errors.template_name }}</p>
                    </div>
                </div>
            </template>
        </BaseFormModal>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';
import { confirmPrompt, error } from '@/Utils/notification';
import draggable from 'vuedraggable';
import SlideThumbnail from './Components/SlideThumbnail.vue';
import BaseFormModal from '@/Components/BaseFormModal.vue';

const store = usePresentationStore();
const selectedId = computed(() => store.selectedSlideId);
const search = ref('');
const isAdding = ref(false);

// AI modal state
const showAiModal = ref(false);
const aiForm = ref({ prompt: '', template_name: 'Default' });
const canUseAI = computed(() => !!store.presentation?.id);
const aiEndpoint = computed(() => (store.presentation?.id ? `/api/presentations/${store.presentation.id}/generate-slide` : ''));

// Template options available for AI generation
const templateOptions = [
    'IntroCover',
    'ThreeColumn',
    'FourColumn',
    'TwoColumnWithImageRight',
    'TwoColumnWithImageLeft',
    'FourStepProcess',
    'ThreeStepProcess',
    'TwoColumnWithChart',
    'ProjectDetails',
    'CallToAction',
    'Default',
    'Heading',
];

function openAiModal() {
    if (!canUseAI.value) {
        error('No presentation loaded.');
        return;
    }
    aiForm.value = { prompt: '', template_name: 'Default' };
    showAiModal.value = true;
}

async function beforeAiSubmit() {
    if (!canUseAI.value) {
        error('No presentation loaded.');
        return false;
    }
    const prompt = (aiForm.value.prompt || '').trim();
    if (!prompt) {
        error('Please enter a prompt.');
        return false;
    }
    return true;
}

async function onAiSubmitted() {
    // After AI creates a slide on the server, reload and select the last slide
    const id = store.presentation?.id;
    if (!id) return;
    await store.load(id);
    const slides = store.slides || [];
    if (slides.length) {
        const last = slides[slides.length - 1];
        if (last?.id) store.selectSlide(last.id);
    }
}

/**
 * [FIXED] This is now a writable computed property.
 * 'get' provides the filtered list for display.
 * 'set' is called by vuedraggable when the list is reordered visually. It updates
 * the entire slide list in the store to reflect the new order, solving the "snap back" issue.
 */
const draggableSlides = computed({
    get() {
        const q = search.value.toLowerCase();
        if (!q) {
            return store.slides; // Return the already sorted list from the store
        }
        return store.slides.filter((s) => (s.title || '').toLowerCase().includes(q));
    },
    set(reorderedSlides) {
        // Find the full set of slides in their original order.
        const fullSlides = [...store.slides];

        // Map the reordered slides to their original objects to maintain reactivity.
        const reorderedIds = reorderedSlides.map(s => s.id);
        const finalSlides = reorderedIds.map(id => fullSlides.find(s => s.id === id));

        // Find any slides that were not visible in the search filter, and append them
        // in their original order.
        const remainingSlides = fullSlides.filter(s => !reorderedIds.includes(s.id));

        // Combine the reordered and remaining slides.
        const combinedSlides = [...finalSlides, ...remainingSlides];

        // Update the display_order of the slides locally to prevent a visual "snap back."
        combinedSlides.forEach((s, idx) => { if (s) s.display_order = idx + 1; });

        // Update the store's presentation slides with the new order.
        store.presentation.slides = combinedSlides;
    }
});

function select(id) {
    store.selectSlide(id);
}

async function add() {
    isAdding.value = true;
    await store.addSlide();
    isAdding.value = false;
}

async function remove(s) {
    const ok = await confirmPrompt('Delete this slide?', { confirmText: 'Delete', cancelText: 'Cancel', type: 'warning' });
    if (ok) await store.deleteSlide(s.id);
}

/**
 * [FIXED] This function now correctly triggers the API call to persist the new order.
 */
function onDragEnd() {
    // The v-model has already updated the local state visually.
    // Now, we just need to get the new order of ALL slide IDs and tell the store to save it.
    const ids = store.slides.map((x) => x.id);
    store.reorderSlides(ids);
}
</script>

<style scoped>
.btn {
    @apply px-3 py-1 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors;
}
.btn-primary {
    @apply bg-indigo-600 text-white hover:bg-indigo-700;
}
.btn:disabled {
    @apply opacity-50 cursor-not-allowed;
}
.btn-xs {
    @apply text-xs;
}
</style>
