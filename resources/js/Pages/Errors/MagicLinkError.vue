<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    message: {
        type: String,
        default: 'Something went wrong with your magic link.'
    }
});
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-100 p-4">
        <div class="bg-white shadow-md rounded-lg p-8 max-w-md w-full text-center">
            <h1 class="text-3xl font-bold text-red-600 mb-4">Magic Link Error</h1>
            <p class="text-gray-700 text-lg mb-6">{{ message }}</p>
            <p class="text-gray-500 text-sm">Please try again or contact support if the issue persists.</p>
            <a href="/" class="mt-6 inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                Go to Homepage
            </a>
        </div>
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed */
</style>
