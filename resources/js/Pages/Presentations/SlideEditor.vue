<template>
    <div class="bg-white rounded-lg shadow-md h-full flex flex-col">
        <div v-if="!slide" class="text-gray-500 text-center py-10">Select a slide</div>
        <div v-else class="flex flex-col h-full">
            <!-- Conditional UI: Template Selector or Editor -->

            <div class="flex flex-col h-full">
                <!-- Sticky Title and Block Toolbox -->
                <div class="flex gap-4 items-center p-6 bg-white sticky top-0 z-10 shadow-sm border-b">
                    <input
                        v-model="slide.title"
                        @change="updateSlide"
                        placeholder="Slide title"
                        class="flex-1 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                        aria-label="Slide title"
                    />
                    <select
                        v-model="localTemplateName"
                        @change="onTemplateChange"
                        class="border border-gray-200 rounded-lg p-2 text-sm bg-white focus:ring-2 focus:ring-indigo-500"
                        aria-label="Slide theme"
                        title="Slide Theme"
                    >
                        <option v-for="opt in themeOptions" :key="opt" :value="opt">{{ opt }}</option>
                    </select>
                    <block-toolbox
                        @add="addBlock"
                        :types="['heading', 'paragraph', 'feature_card', 'image', 'step_card', 'slogan', 'pricing_table', 'timeline_table', 'details_list', 'list_with_icons', 'feature_list', 'image_block']"
                        aria-label="Add content block"
                    />
                </div>

                <!-- Draggable Content Blocks with Scroll -->
                <draggable
                    v-model="contentBlocks"
                    item-key="id"
                    class="space-y-4 p-6 overflow-y-auto flex-1"
                    handle=".drag-handle"
                    @end="reorderBlocks"
                    role="list"
                    aria-label="Content blocks"
                >
                    <template #item="{ element: b }">
                        <div
                            class="border border-gray-200 rounded-lg p-4 bg-gray-50 hover:shadow-md transition-shadow duration-200"
                            role="listitem"
                        >
                            <div class="flex justify-between mb-3 items-center">
                                <div class="flex items-center gap-3">
                                    <span class="font-bold drag-handle cursor-move text-gray-700">{{ b.block_type }}</span>
                                    <span v-if="store.savingBlocks[b.id]" class="text-xs text-gray-500">Saving…</span>
                                    <span v-else class="text-xs text-green-600">Saved ✓</span>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="duplicateBlock(b)" class="btn btn-xs" aria-label="Duplicate block">Duplicate</button>
                                    <button @click="removeBlock(b)" class="btn btn-xs text-red-500" aria-label="Delete block">Delete</button>
                                </div>
                            </div>
                            <ContentBlockForm :block="b" @update="onUpdateBlock(b, $event)" />
                        </div>
                    </template>
                </draggable>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePresentationStore } from '@/Stores/presentationStore';
import { confirmPrompt } from '@/Utils/notification';
import ContentBlockForm from './ContentBlockForm.vue';
import draggable from 'vuedraggable';
import BlockToolbox from './Components/BlockToolbox.vue';

const store = usePresentationStore();
const slide = computed(() => store.selectedSlide || { content_blocks: [] });

// Theme options per requirement
const themeOptions = [
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

// Local v-model to avoid mutating directly before API save
const localTemplateName = computed({
    get() {
        return slide.value?.template_name || 'Default';
    },
    set(v) {
        if (slide.value) slide.value.template_name = v;
    }
});
const contentBlocks = computed({
    get: () => slide.value.content_blocks || [],
    set: (newList) => {
        if (!slide.value) return;
        // Assign reordered list to the slide and keep display_order in sync to avoid snap-back
        slide.value.content_blocks = Array.isArray(newList) ? newList.slice() : [];
        slide.value.content_blocks.forEach((b, idx) => { if (b) b.display_order = idx + 1; });
    },
});

const templates = [
    { name: 'Heading', label: 'Heading Slide', icon: '📝' },
    { name: 'TwoColumnWithImage', label: 'Image with Text', icon: '🖼️' },
    { name: 'FeatureGrid', label: 'Feature Grid', icon: '✨' },
    { name: 'ChartSlide', label: 'Chart Slide', icon: '📊' },
];

const payloads = {
    heading: [{ block_type: 'heading', content_data: { text: 'New Heading', level: 2 } }],
    TwoColumnWithImage: [
        { block_type: 'heading', content_data: { text: 'Title Here', level: 2 } },
        { block_type: 'image', content_data: { url: '', alt: 'Image' } },
        { block_type: 'paragraph', content_data: { text: 'Add some descriptive text here.' } },
    ],
    FeatureGrid: [
        { block_type: 'heading', content_data: { text: 'Key Features', level: 2 } },
        { block_type: 'feature_card', content_data: { icon: 'fa-star', title: 'Feature One', description: 'Description of feature one.' } },
        { block_type: 'feature_card', content_data: { icon: 'fa-check', title: 'Feature Two', description: 'Description of feature two.' } },
        { block_type: 'feature_card', content_data: { icon: 'fa-bolt', title: 'Feature Three', description: 'Description of feature three.' } },
    ],
    ChartSlide: [{ block_type: 'chart', content_data: { type: 'bar', data: { labels: ['A', 'B', 'C'], values: [10, 20, 30] } } }],
};

async function updateSlide() {
    if (!slide.value?.id) return;
    await store.updateSlide(slide.value.id, { title: slide.value.title });
}

function onTemplateChange() {
    if (!slide.value?.id) return;
    const tpl = localTemplateName.value || 'Default';
    store.updateSlide(slide.value.id, { template_name: tpl });
}

async function addTemplateBlocks(templateName) {
    if (!slide.value?.id || !payloads[templateName]) return;
    await store.updateSlide(slide.value.id, { template_name: templateName });
    for (const blockPayload of payloads[templateName]) {
        await store.addBlock(slide.value.id, blockPayload);
    }
}

async function addBlock(type) {
    if (!slide.value?.id) return;
    const blockPayloads = {
        heading: { block_type: 'heading', content_data: { text: 'New Block Heading', level: 2 } },
        paragraph: { block_type: 'paragraph', content_data: { text: 'Lorem ipsum' } },
        feature_card: { block_type: 'feature_card', content_data: { icon: 'fa-star', title: 'New Feature', description: 'Description' } },
        image: { block_type: 'image', content_data: { url: '', alt: 'Image' } },
        step_card: { block_type: 'step_card', content_data: { step_number: 1, title: 'New Step', description: 'Description' } },
        slogan: { block_type: 'slogan', content_data: { text: 'New Slogan' } },
        pricing_table: { block_type: 'pricing_table', content_data: { title: 'Pricing', price: '$0', payment_schedule: ['Item 1', 'Item 2'] } },
        timeline_table: { block_type: 'timeline_table', content_data: { title: 'Timeline', timeline: [{ phase: 'Phase 1', duration: '1 Week' }] } },
        details_list: { block_type: 'details_list', content_data: { items: ['Detail 1', 'Detail 2'] } },
        list_with_icons: { block_type: 'list_with_icons', content_data: { items: ['Item 1', 'Item 2'] } },
        feature_list: { block_type: 'feature_list', content_data: { items: [{ title: 'Feature 1', description: 'Description 1' }] } },
        image_block: { block_type: 'image_block', content_data: { url: '', title: 'New Image Block' } },
    };
    await store.addBlock(slide.value.id, blockPayloads[type]);
}

function onUpdateBlock(block, content) {
    store.scheduleSaveBlock(block.id, content);
}

async function duplicateBlock(block) {
    if (!slide.value?.id) return;
    await store.addBlock(slide.value.id, { block_type: block.block_type, content_data: { ...block.content_data } });
}

async function removeBlock(block) {
    // Replaced confirm() with the custom confirmPrompt utility
    const ok = await confirmPrompt('Delete this block?', { confirmText: 'Delete', cancelText: 'Cancel', type: 'warning' });
    if (ok) await store.deleteBlock(block.id);
}

async function reorderBlocks() {
    if (!slide.value?.id) return;
    const ids = contentBlocks.value.map((x) => x.id);
    await store.reorderBlocks(slide.value.id, ids);
}
</script>

<style scoped>
.btn {
    @apply px-3 py-1 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors;
}
.btn-xs {
    @apply text-xs;
}
</style>
