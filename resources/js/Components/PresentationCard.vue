<template>
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 hover:shadow-md transition-shadow duration-200">
        <h3 class="text-lg font-semibold text-gray-900 truncate">{{ presentation.title }}</h3>
        <p class="text-sm text-gray-600">{{ presentation.type }}</p>
        <p class="text-sm text-gray-500">{{ presentation.presentable_type?.split('\\').pop() }} #{{ presentation.presentable_id }}</p>
        <p class="text-xs text-gray-400">{{ new Date(presentation.created_at).toLocaleString() }}</p>
        <div class="mt-3 flex gap-2">
            <button @click="$emit('edit', presentation.id)" class="btn btn-primary" aria-label="Edit presentation">Edit</button>
            <button @click="$emit('preview', presentation)" class="btn" aria-label="Preview presentation">Preview</button>
            <button @click="$emit('share', presentation)" class="btn" aria-label="Share presentation">Share</button>
            <button @click="$emit('delete', presentation.id)" class="btn text-red-500" aria-label="Delete presentation">Delete</button>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    presentation: { type: Object, required: true },
});
</script>

<style scoped>
.btn {
    @apply px-3 py-1 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors;
}
.btn-primary {
    @apply bg-indigo-600 text-white hover:bg-indigo-700;
}
</style>
