<script setup>
// Props
const props = defineProps({
    'total-due-tasks': {
        type: Number,
        default: 0
    },
});
</script>

<template>
    <div class="bg-white rounded-lg shadow-xl p-8 flex flex-col justify-between transition-all duration-300 hover:shadow-2xl">
        <h3 class="text-xl font-semibold text-gray-900 mb-4">Pending Tasks</h3>
        <div class="flex items-center justify-between">
            <p class="text-5xl font-extrabold text-indigo-600">{{ props['total-due-tasks'] }}</p>
            <svg class="w-12 h-12 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
            </svg>
        </div>
        <p class="text-sm text-gray-600 mt-4">Tasks currently due across all your projects.</p>
    </div>
</template>
