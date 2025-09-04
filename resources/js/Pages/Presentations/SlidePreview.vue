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

    switch (b.block_type) {
        case 'heading':
            const Tag = `h${c.level || 2}`;
            return { render() { return h(Tag, { class: `font-bold text-${4 - c.level}xl ${classes}` }, c.text || ''); } };
        case 'paragraph':
            return { render() { return h('p', { class: `text-gray-700 ${classes}`, innerHTML: c.text || '' }); } };
        case 'feature_card':
            return {
                render() {
                    return h('div', { class: `p-4 border rounded-lg bg-white ${previewTheme.value === 'dark' ? 'bg-gray-800' : ''}` }, [
                        c.icon ? h('i', { class: `${c.icon} mr-2 text-indigo-500` }) : null,
                        h('div', { class: `font-semibold ${classes}` }, c.title || ''),
                        h('div', { class: `text-sm text-gray-600 ${classes}` }, c.description || ''),
                    ]);
                },
            };
        case 'image':
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
                                const target = e?.target;
                                if (target) { target.replaceWith(document.createTextNode('Image failed to load')); }
                            },
                        }),
                    ]);
                },
            };
        case 'step_card':
            return {
                render() {
                    return h('div', { class: `p-4 border rounded-lg bg-white ${previewTheme.value === 'dark' ? 'bg-gray-800' : ''}` }, [
                        h('div', { class: 'text-xl font-bold text-indigo-500' }, c.step_number || ''),
                        h('div', { class: `font-semibold ${classes}` }, c.title || ''),
                        h('div', { class: `text-sm text-gray-600 ${classes}` }, c.description || ''),
                    ]);
                },
            };
        case 'slogan':
            return {
                render() {
                    return h('div', { class: `text-xl font-bold text-center ${classes}` }, c.text || '');
                },
            };
        case 'pricing_table':
            return {
                render() {
                    return h('div', { class: `p-4 border rounded-lg bg-white ${previewTheme.value === 'dark' ? 'bg-gray-800' : ''}` }, [
                        h('h4', { class: 'text-lg font-bold' }, c.title || 'Pricing'),
                        h('p', { class: 'text-sm' }, `Price: ${c.price || ''}`),
                        c.payment_schedule && h('ul', { class: 'list-disc list-inside mt-2' }, c.payment_schedule.map(item => h('li', item))),
                    ]);
                },
            };
        case 'timeline_table':
            return {
                render() {
                    return h('div', { class: `p-4 border rounded-lg bg-white ${previewTheme.value === 'dark' ? 'bg-gray-800' : ''}` }, [
                        h('h4', { class: 'text-lg font-bold' }, c.title || 'Timeline'),
                        c.timeline && h('table', { class: 'w-full text-sm text-left mt-2' }, [
                            h('tbody', c.timeline.map(item => h('tr', { class: 'border-b' }, [
                                h('td', { class: 'py-3 pr-3 font-semibold' }, item.phase),
                                h('td', { class: 'py-3' }, item.duration),
                            ]))),
                        ]),
                    ]);
                },
            };
        case 'details_list':
            return {
                render() {
                    return h('div', { class: 'flex justify-center mt-8 space-x-6 text-gray-500 text-sm' },
                        c.items && c.items.map(item => h('span', { innerHTML: item }))
                    );
                },
            };
        case 'list_with_icons':
            return {
                render() {
                    return h('ul', { class: 'space-y-4 text-gray-700' },
                        c.items && c.items.map(item => h('li', { class: 'flex items-start' }, [
                            h('svg', { xmlns: 'http://www.w3.org/2000/svg', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '2', stroke: 'currentColor', class: 'w-6 h-6 text-oz-gold mr-3 flex-shrink-0' }, [
                                h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
                            ]),
                            h('span', item)
                        ]))
                    );
                }
            };
        case 'feature_list':
            return {
                render() {
                    return h('div', { class: 'text-left space-y-8' },
                        c.items && c.items.map(item => h('div', { class: 'flex items-start' }, [
                            h('div', { class: 'flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-oz-blue text-oz-gold mr-4' }, [
                                h('svg', { xmlns: 'http://www.w3.org/2000/svg', fill: 'none', viewBox: '0 0 24 24', strokeWidth: '1.5', stroke: 'currentColor', class: 'w-6 h-6' }, [
                                    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.286zm0 13.036h.008v.008h-.008v-.008z' })
                                ]),
                            ]),
                            h('div', {}, [
                                h('h3', { class: 'text-xl font-bold text-dark-grey' }, item.title),
                                h('p', { class: 'text-gray-600' }, item.description)
                            ]),
                        ]))
                    );
                }
            };
        case 'image_block':
            return {
                render() {
                    return h('div', { class: 'p-8 bg-gray-50 rounded-2xl' }, [
                        h('h3', { class: 'text-xl font-bold text-dark-grey mb-4' }, c.title),
                        h('div', { class: 'w-full h-64 bg-gray-200 rounded-lg flex items-center justify-center' }, [
                            h('img', { src: c.url, alt: c.title, class: 'max-w-full h-auto', onError: (e) => e.target.style.display = 'none' })
                        ]),
                    ]);
                }
            };
        default:
            return { render() { return h('div', { class: `text-red-500 ${classes}` }, `Unsupported: ${b.block_type}`); } };
    }
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
