<template>
    <div
        class="relative w-32 h-18 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden transform scale-90 hover:scale-100 transition-transform duration-200"
        role="img"
        :aria-label="`Thumbnail for slide ${slide.title || slide.template_name}`"
    >
        <div class="p-2 text-xs" :class="previewTheme">
            <div v-for="b in slide.content_blocks" :key="b.id" class="mb-1">
                <component :is="getRenderer(b)" />
            </div>
        </div>
        <div v-if="!slide.content_blocks?.length" class="absolute inset-0 flex items-center justify-center text-gray-400 text-xs">
            Empty Slide
        </div>
    </div>
</template>

<script setup>
import { computed, h, ref } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';

const props = defineProps({
    slide: { type: Object, required: true },
});

const store = usePresentationStore();
const previewTheme = ref('light'); // Matches SlidePreview.vue for consistency

function getRenderer(b) {
    const c = b.content_data || {};
    const classes = previewTheme.value === 'light' ? 'text-gray-900' : 'text-gray-100';

    if (b.block_type === 'heading') {
        const Tag = `h${c.level || 2}`;
        return {
            render() {
                return h(Tag, { class: `font-bold text-sm truncate ${classes}` }, c.text || '');
            },
        };
    }
    if (b.block_type === 'paragraph') {
        return {
            render() {
                return h('p', { class: `text-xs text-gray-600 truncate ${classes}` }, c.text?.slice(0, 30) + (c.text?.length > 30 ? '...' : '') || '');
            },
        };
    }
    if (b.block_type === 'feature_card') {
        return {
            render() {
                return h('div', { class: `p-1 border rounded bg-white ${previewTheme.value === 'dark' ? 'bg-gray-800' : ''}` }, [
                    c.icon ? h('i', { class: `${c.icon} mr-1 text-indigo-400 text-xs` }) : null,
                    h('div', { class: `font-semibold text-xs truncate ${classes}` }, c.title || ''),
                    h('div', { class: `text-xs text-gray-500 truncate ${classes}` }, c.description?.slice(0, 20) + (c.description?.length > 20 ? '...' : '') || ''),
                ]);
            },
        };
    }
    return {
        render() {
            return h('div', { class: `text-red-400 text-xs ${classes}` }, `Unsupported: ${b.block_type}`);
        },
    };
}
</script>

<style scoped>
/* Tailwind handles most styling, but we can add custom tweaks if needed */
</style>
