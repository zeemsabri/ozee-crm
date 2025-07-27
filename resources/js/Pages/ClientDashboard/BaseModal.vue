<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
    isOpen: Boolean,
    title: String,
    message: String,
    buttons: Array,
    children: Object // For VNodes or components passed as slots/props
});

const emits = defineEmits(['close']);
</script>

<template>
    <div v-if="props.isOpen" class="fixed inset-0 bg-gray-200 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl w-11/12 max-w-md relative">
            <span class="absolute top-2 right-4 text-gray-500 text-3xl cursor-pointer hover:text-gray-800" @click="emits('close')">&times;</span>
            <h3 class="text-xl font-semibold mb-4 text-gray-800">{{ props.title }}</h3>
            <p v-if="props.message" class="mb-6 text-gray-700">{{ props.message }}</p>
            <component :is="props.children" v-if="props.children" /> <!-- For custom content within the modal -->
            <div class="flex justify-end space-x-4 mt-4">
                <button
                    v-for="(button, index) in props.buttons"
                    :key="index"
                    @click="() => { button.onClick(); emits('close'); }"
                    :class="['py-2 px-4 rounded-lg font-medium', button.className]"
                >
                    {{ button.label }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed, or rely on Tailwind CSS */
</style>
