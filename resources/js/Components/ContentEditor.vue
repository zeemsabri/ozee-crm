<template>
    <textarea
        :id="id"
        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm mt-1 block w-full"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        ref="textareaRef"
    ></textarea>
</template>

<script setup>
import { ref, defineProps, defineEmits, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    id: String,
    modelValue: String,
    rows: {
        type: Number,
        default: 4,
    },
});

const emit = defineEmits(['update:modelValue']);

const textareaRef = ref(null);

// Expose the textarea element so the parent can access it
defineExpose({
    textareaRef,
});

</script>
