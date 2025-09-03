<template>
    <modal @close="$emit('close')" aria-label="Presentation preview modal">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-4xl max-h-[80vh] overflow-auto">
            <h2 class="text-xl font-bold mb-4">{{ presentation.title }}</h2>
            <div v-for="s in presentation.slides" :key="s.id" class="mb-6">
                <h3 class="text-lg font-semibold mb-2">{{ s.title || s.template_name }}</h3>
                <div v-for="b in s.content_blocks" :key="b.id" class="mb-3">
                    <component :is="getRenderer(b)" />
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button @click="$emit('close')" class="btn" aria-label="Close preview">Close</button>
            </div>
        </div>
    </modal>
</template>

<script setup>
import { h } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    item: { type: Object, required: true },
});

const presentation = computed(() => props.item);

function getRenderer(b) {
    const c = b.content_data || {};
    if (b.block_type === 'heading') {
        const Tag = `h${c.level || 2}`;
        return {
            render() {
                return h(Tag, { class: 'font-bold text-xl text-gray-900' }, c.text || '');
            },
        };
    }
    if (b.block_type === 'paragraph') {
        return {
            render() {
                return h('p', { class: 'text-gray-700 leading-relaxed' }, c.text || '');
            },
        };
    }
    if (b.block_type === 'feature_card') {
        return {
            render() {
                return h('div', { class: 'p-4 border rounded-lg bg-white shadow-sm' }, [
                    c.icon ? h('i', { class: `${c.icon} mr-2 text-indigo-500` }) : null,
                    h('div', { class: 'font-semibold text-gray-900' }, c.title || ''),
                    h('div', { class: 'text-sm text-gray-600' }, c.description || ''),
                ]);
            },
        };
    }
    return {
        render() {
            return h('div', { class: 'text-red-500' }, `Unsupported: ${b.block_type}`);
        },
    };
}
</script>

<style scoped>
.btn {
    @apply px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors;
}
</style>
