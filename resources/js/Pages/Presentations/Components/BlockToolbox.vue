<template>
    <div class="flex gap-2 overflow-scroll" role="group" aria-label="Add content block">
        <button
            v-for="type in types"
            :key="type"
            @click="$emit('add', type)"
            class="btn btn-secondary flex items-center gap-2 text-sm"
            :aria-label="`Add ${type} block`"
        >
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
