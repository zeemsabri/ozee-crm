<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    isLoading: Boolean
});
</script>

<template>
    <div v-if="props.isLoading" class="fixed inset-0 bg-gray-200 bg-opacity-75 flex items-center justify-center z-50">
        <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-500"></div>
        <p class="ml-4 text-lg text-gray-700">Loading...</p>
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed, or rely on Tailwind CSS */
</style>
