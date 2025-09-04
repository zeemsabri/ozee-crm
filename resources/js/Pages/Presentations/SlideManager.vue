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
                {{ isAdding ? '...' : '+ Add' }}
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
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';
import { confirmPrompt } from '@/Utils/notification';
import draggable from 'vuedraggable';
import SlideThumbnail from './Components/SlideThumbnail.vue';

const store = usePresentationStore();
const selectedId = computed(() => store.selectedSlideId);
const search = ref('');
const isAdding = ref(false);


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
