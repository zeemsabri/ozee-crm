<template>
    <div class="mt-4">
        <InputLabel value="Available Placeholders" class="mb-2" />
        <div class="flex flex-wrap gap-2 p-2 bg-gray-50 border border-gray-200 rounded-lg">
            <button
                v-for="placeholder in definitions"
                :key="placeholder.id"
                @click.prevent="insertPlaceholder(placeholder.name)"
                type="button"
                class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded-full shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150"
            >
                {{ placeholder.name }}
            </button>
        </div>
        <p class="mt-2 text-xs text-gray-500">
            Click a placeholder to insert it into the text.
        </p>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    // The list of placeholder definitions to display
    definitions: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['insert']);

/**
 * Emits an 'insert' event with the placeholder text.
 * @param {string} placeholderName The name of the placeholder to insert.
 */
const insertPlaceholder = (placeholderName) => {
    emit('insert', `{{ ${placeholderName} }}`);
};
</script>

