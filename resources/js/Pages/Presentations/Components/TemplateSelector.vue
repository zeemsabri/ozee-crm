<template>
    <div class="relative">
        <select
            v-model="localValue"
            @change="$emit('change', localValue)"
            class="border border-gray-200 rounded-lg p-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500 appearance-none w-full"
            aria-label="Slide template"
        >
            <option v-for="option in options" :key="option" :value="option">{{ option }}</option>
        </select>
        <svg class="absolute right-2 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue', 'change']);

const localValue = ref(props.modelValue);

watch(() => props.modelValue, (newValue) => {
    localValue.value = newValue;
});

watch(localValue, (newValue) => {
    emit('update:modelValue', newValue);
});
</script>

<style scoped>
/* Tailwind handles styling */
</style>
