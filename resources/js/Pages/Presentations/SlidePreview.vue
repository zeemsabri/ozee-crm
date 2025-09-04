<template>
    <div class="border border-gray-200 rounded-lg p-6 bg-gray-100 relative overflow-y-auto h-full transition-all duration-300">
        <div v-if="!slide" class="text-center text-gray-500 py-10">No slide selected</div>
        <div v-else class="max-w-4xl mx-auto" :class="previewTheme">
            <h3 class="text-lg font-bold mb-4 sticky top-0 bg-white/90 p-2 rounded shadow-sm">
                Preview: {{ slide.title || slide.template_name }}
            </h3>
            <div
                v-for="b in slide.content_blocks"
                :key="b.id"
                class="mb-4 cursor-pointer hover:shadow-md p-3 rounded-lg transition-shadow duration-200"
                @click="editBlock(b.id)"
                role="button"
                tabindex="0"
                @keydown.enter="editBlock(b.id)"
            >
                <component :is="getRenderer(b)" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, h, ref } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';
import ZoomControls from './Components/ZoomControls.vue';

const store = usePresentationStore();
const slide = computed(() => store.selectedSlide);
const previewTheme = ref('light');
const zoomLevel = ref(1);

function toggleTheme() {
    previewTheme.value = previewTheme.value === 'light' ? 'dark' : 'light';
}

function editBlock(id) {
    store.selectBlock(id); // Placeholder for block selection
}

function getRenderer(b) {
    const c = b.content_data || {};
    const classes = previewTheme.value === 'light' ? 'text-gray-900' : 'text-gray-100';
    if (b.block_type === 'heading') {
        const Tag = `h${c.level || 2}`;
        return {
            render() {
                return h(Tag, { class: `font-bold text-xl ${classes}` }, c.text || '');
            },
        };
    }
    if (b.block_type === 'paragraph') {
        return {
            render() {
                // --- THIS IS THE FIX ---
                // We use `innerHTML` to tell Vue to render the string as HTML.
                // This is safe because the rich text editor (Tiptap) sanitizes its output.
                return h('p', { class: `text-gray-700 ${classes}`, innerHTML: c.text || '' });
            },
        };
    }
    if (b.block_type === 'feature_card') {
        return {
            render() {
                return h('div', { class: `p-4 border rounded-lg bg-white ${previewTheme.value === 'dark' ? 'bg-gray-800' : ''}` }, [
                    c.icon ? h('i', { class: `${c.icon} mr-2 text-indigo-500` }) : null,
                    h('div', { class: `font-semibold ${classes}` }, c.title || ''),
                    h('div', { class: `text-sm text-gray-600 ${classes}` }, c.description || ''),
                ]);
            },
        };
    }
    if (b.block_type === 'image') {
        return {
            render() {
                const src = c.url || c.src || '';
                const alt = c.alt || 'Image';
                return h('div', { class: 'w-full' }, [
                    h('img', {
                        src,
                        alt,
                        class: 'max-w-full h-auto rounded-md shadow-sm',
                        onError: (e) => {
                            // Fallback UI on broken image
                            const target = e?.target;
                            if (target) {
                                target.replaceWith(document.createTextNode('Image failed to load'));
                            }
                        },
                    }),
                ]);
            },
        };
    }
    return {
        render() {
            return h('div', { class: `text-red-500 ${classes}` }, `Unsupported: ${b.block_type}`);
        },
    };
}
</script>

<style scoped>
.btn {
    @apply px-3 py-1 bg-gray-200 rounded-lg hover:bg-gray-300;
}
.btn-xs {
    @apply text-xs;
}
.dark {
    @apply bg-gray-900 text-white;
}
</style>
