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

    // Headings
    if (b.block_type === 'heading') {
        const Tag = `h${c.level || 2}`;
        return {
            render() {
                return h(Tag, { class: `font-bold text-sm truncate ${classes}` }, c.text || '');
            },
        };
    }

    // Paragraph
    if (b.block_type === 'paragraph') {
        return {
            render() {
                const txt = (c.text || '').toString();
                const short = txt.length > 30 ? txt.slice(0, 30) + '...' : txt;
                return h('p', { class: `text-xs text-gray-600 truncate ${classes}` }, short);
            },
        };
    }

    // Feature card
    if (b.block_type === 'feature_card') {
        return {
            render() {
                return h('div', { class: `p-1 border rounded ${previewTheme.value === 'dark' ? 'bg-gray-800' : 'bg-white'}` }, [
                    c.icon ? h('i', { class: `${c.icon} mr-1 text-indigo-400 text-xs` }) : null,
                    h('div', { class: `font-semibold text-xs truncate ${classes}` }, c.title || ''),
                    h('div', { class: `text-xs text-gray-500 truncate ${classes}` }, (c.description || '').toString().slice(0, 20) + ((c.description || '').toString().length > 20 ? '...' : '')),
                ]);
            },
        };
    }

    // Image
    if (b.block_type === 'image') {
        return {
            render() {
                const url = c.url || c.src || '';
                return h('div', { class: 'w-full h-16 bg-gray-100 border rounded overflow-hidden flex items-center justify-center' }, [
                    url
                        ? h('img', { src: url, alt: c.alt || 'Image', class: 'w-full h-full object-cover' })
                        : h('div', { class: 'text-[10px] text-gray-400' }, 'Image')
                ]);
            },
        };
    }

    // Simple list (strings)
    if (b.block_type === 'list') {
        return {
            render() {
                const items = Array.isArray(c.items) ? c.items : [];
                const top = items.slice(0, 3).map((t) =>
                    h('li', { class: 'truncate' }, (typeof t === 'string' ? t : (t?.text || '')))
                );
                return h('ul', { class: `list-disc ml-4 text-[10px] text-gray-600 ${classes}` }, top.length ? top : [h('li', '—')]);
            },
        };
    }

    // List with icons (objects)
    if (b.block_type === 'list_with_icons') {
        return {
            render() {
                const items = Array.isArray(c.items) ? c.items : [];
                const top = items.slice(0, 3).map((it) =>
                    h('div', { class: 'flex items-center gap-1 text-[10px] truncate' }, [
                        h('span', { class: 'inline-block w-2 h-2 rounded-full bg-indigo-400' }),
                        h('span', { class: `truncate ${classes}` }, (it?.text || (typeof it === 'string' ? it : ''))) ,
                    ])
                );
                return h('div', {}, top.length ? top : [h('div', { class: 'text-[10px] text-gray-400' }, 'No items')]);
            },
        };
    }

    // Step card
    if (b.block_type === 'step_card') {
        return {
            render() {
                const step = c.step_number != null ? String(c.step_number) : '';
                return h('div', { class: 'flex items-center gap-2 text-xs' }, [
                    h('div', { class: 'w-5 h-5 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px]' }, step || '•'),
                    h('div', { class: `truncate ${classes}` }, c.title || 'Step')
                ]);
            },
        };
    }

    // Slogan
    if (b.block_type === 'slogan') {
        return {
            render() {
                return h('div', { class: `text-xs font-semibold ${classes}` }, c.text || '');
            },
        };
    }

    // Pricing table summary
    if (b.block_type === 'pricing_table') {
        return {
            render() {
                return h('div', { class: 'text-[10px] text-gray-700' }, [
                    h('div', { class: `font-semibold truncate ${classes}` }, c.title || 'Pricing'),
                    h('div', { class: 'truncate text-gray-500' }, c.price ? String(c.price) : '')
                ]);
            },
        };
    }

    // Timeline table summary
    if (b.block_type === 'timeline_table') {
        return {
            render() {
                const rows = Array.isArray(c.timeline) ? c.timeline.length : 0;
                return h('div', { class: 'text-[10px] text-gray-700' }, [
                    h('div', { class: `font-semibold truncate ${classes}` }, c.title || 'Timeline'),
                    h('div', { class: 'truncate text-gray-500' }, `${rows} phases`)
                ]);
            },
        };
    }

    // Details list summary
    if (b.block_type === 'details_list') {
        return {
            render() {
                const count = Array.isArray(c.items) ? c.items.length : 0;
                return h('div', { class: 'text-[10px] text-gray-700' }, [
                    h('div', { class: `font-semibold truncate ${classes}` }, c.title || 'Details'),
                    h('div', { class: 'truncate text-gray-500' }, `${count} items`)
                ]);
            },
        };
    }

    // Feature list summary (cards)
    if (b.block_type === 'feature_list') {
        return {
            render() {
                const items = Array.isArray(c.items) ? c.items.slice(0, 2) : [];
                return h('div', { class: 'text-[10px] space-y-1' }, items.map(it =>
                    h('div', { class: 'flex items-center gap-2 truncate' }, [
                        h('div', { class: 'w-4 h-4 rounded-full bg-oz-blue text-oz-gold flex items-center justify-center text-[9px]' }, '★'),
                        h('span', { class: `truncate ${classes}` }, it?.title || (typeof it === 'string' ? it : ''))
                    ])
                ));
            },
        };
    }

    // Image block
    if (b.block_type === 'image_block') {
        return {
            render() {
                const url = c.url || c.src || '';
                return h('div', { class: 'w-full h-16 bg-gray-100 border rounded overflow-hidden flex items-center justify-center' }, [
                    url
                        ? h('img', { src: url, alt: c.title || 'Image', class: 'w-full h-full object-cover' })
                        : h('div', { class: 'text-[10px] text-gray-400' }, 'Image')
                ]);
            },
        };
    }

    // Fallback
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
