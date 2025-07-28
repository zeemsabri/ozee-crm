<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        default: 'Modal Title',
    },
    message: {
        type: String,
        default: '',
    },
    buttons: {
        type: Array,
        default: () => [],
    },
    children: {
        // Can be any type for custom content
        type: [Object, String, Array, null],
        default: null,
    },
});

const emits = defineEmits(['close']);

const handleClose = () => {
    emits('close');
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 bg-gray-200 bg-opacity-75 flex items-center justify-center z-[100]">
        <div class="bg-white p-6 rounded-lg shadow-xl w-11/12 max-w-md relative">
            <span class="absolute top-2 right-4 text-gray-500 text-3xl cursor-pointer hover:text-gray-800" @click="handleClose">&times;</span>
            <h3 class="text-xl font-semibold mb-4 text-gray-800">{{ title }}</h3>
            <p v-if="message" class="mb-6 text-gray-700 whitespace-pre-line">{{ message }}</p>
            <div v-if="children">
                <component :is="children" />
            </div>
            <div class="flex justify-end space-x-4 mt-4">
                <button
                    v-for="(button, index) in buttons"
                    :key="index"
                    :class="button.className"
                    @click="button.onClick"
                >
                    {{ button.label }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Any additional custom styles for BaseModal can go here */
</style>
