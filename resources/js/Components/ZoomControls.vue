<template>
    <div class="flex items-center gap-2 bg-white rounded-lg p-1 shadow-sm" role="group" aria-label="Zoom controls">
        <button
            @click="zoomIn"
            class="btn btn-xs"
            :disabled="modelValue >= maxZoom"
            aria-label="Zoom in"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>
        <span class="text-xs text-gray-600">{{ Math.round(modelValue * 100) }}%</span>
        <button
            @click="zoomOut"
            class="btn btn-xs"
            :disabled="modelValue <= minZoom"
            aria-label="Zoom out"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
            </svg>
        </button>
        <button @click="resetZoom" class="btn btn-xs" aria-label="Reset zoom">Reset</button>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: Number, default: 1 },
});

const emit = defineEmits(['update:modelValue']);

const minZoom = 0.5;
const maxZoom = 2;
const zoomStep = 0.1;

function zoomIn() {
    if (props.modelValue < maxZoom) {
        emit('update:modelValue', Math.min(props.modelValue + zoomStep, maxZoom));
    }
}

function zoomOut() {
    if (props.modelValue > minZoom) {
        emit('update:modelValue', Math.max(props.modelValue - zoomStep, minZoom));
    }
}

function resetZoom() {
    emit('update:modelValue', 1);
}
</script>

<style scoped>
.btn {
    @apply px-2 py-1 bg-gray-200 rounded hover:bg-gray-300 transition-colors;
}
.btn-xs {
    @apply text-xs;
}
.btn:disabled {
    @apply opacity-50 cursor-not-allowed;
}
</style>
