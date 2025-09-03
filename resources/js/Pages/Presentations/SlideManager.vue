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
        // This makes the visual drag work by updating the state.
        // The actual API call happens on the @end event.
        // We need to merge the reordered (potentially filtered) list back into the main list.
        const fullSlides = [...store.slides];
        const reorderedIds = new Set(reorderedSlides.map(s => s.id));
        const stationarySlides = fullSlides.filter(s => !reorderedIds.has(s.id));

        // A simple way to merge is to place all reordered slides first, then stationary ones.
        // A more complex merge could preserve relative order, but this is robust.
        // For our case, we will just update the order of all slides.
        const finalSlides = [...reorderedSlides, ...stationarySlides];
        store.presentation.slides = finalSlides;
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
