<script setup>
import { defineProps } from 'vue';

const props = defineProps(['announcements']);
</script>

<template>
    <div id="announcements" class="section">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Agency Announcements</h2>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <ul class="space-y-4">
                <li v-if="props.announcements.length === 0" class="text-gray-500">No new announcements at this time.</li>
                <li v-for="ann in props.announcements" :key="ann.id" class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <p class="font-semibold text-blue-800">{{ ann.title }}</p>
                    <p class="text-sm text-gray-700 mt-1">{{ ann.message }}</p>
                    <p class="text-xs text-gray-500 mt-2">Posted: {{ ann.date ? new Date(ann.date).toLocaleDateString() : 'N/A' }}</p>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
/* Add any specific styles here if needed, or rely on Tailwind CSS */
</style>
