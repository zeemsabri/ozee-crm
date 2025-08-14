<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    title: String,
    value: String,
    percentage: Number,
});
</script>

<template>
    <div class="bg-white p-6 rounded-lg shadow-xl border-t-4 border-blue-500">
        <h3 class="text-md font-semibold text-gray-500">{{ title }}</h3>
        <p class="text-4xl font-bold mt-2 text-gray-900">{{ value }}</p>
        <p v-if="percentage !== undefined" :class="percentage > 0 ? 'text-green-500' : 'text-red-500'" class="text-sm mt-1">
            {{ percentage.toFixed(2) }}% distributed
        </p>
    </div>
</template>
