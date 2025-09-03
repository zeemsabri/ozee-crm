<template>
    <div class="flex gap-2" role="group" aria-label="Add content block">
        <button
            v-for="type in types"
            :key="type"
            @click="$emit('add', type)"
            class="btn btn-secondary flex items-center gap-2"
            :aria-label="`Add ${type} block`"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ blockLabels[type] || type }}
        </button>
    </div>
</template>

<script setup>
const props = defineProps({
    types: { type: Array, required: true },
});

const blockLabels = {
    heading: 'Heading',
    paragraph: 'Paragraph',
    feature_card: 'Feature Card',
    image: 'Image',
    chart: 'Chart',
};
</script>

<style scoped>
.btn {
    @apply px-3 py-1 rounded-lg transition-colors;
}
.btn-secondary {
    @apply bg-indigo-100 text-indigo-700 hover:bg-indigo-200;
}
</style>
