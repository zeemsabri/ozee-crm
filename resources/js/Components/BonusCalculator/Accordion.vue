<script setup>
import { ref } from 'vue';
import { ChevronDown, ChevronUp } from 'lucide-react';

const props = defineProps({
    title: String,
});

const isOpen = ref(false);
</script>

<template>
    <div class="border border-gray-200 rounded-lg overflow-hidden shadow-lg">
        <button
            @click="isOpen = !isOpen"
            class="flex items-center justify-between w-full p-4 bg-gray-50 hover:bg-gray-100 transition-colors duration-150 text-left font-semibold text-lg"
        >
            {{ title }}
            <component :is="isOpen ? ChevronUp : ChevronDown" class="h-5 w-5 text-gray-500" />
        </button>
        <div v-show="isOpen" class="bg-white transition-all duration-300 ease-in-out">
            <slot></slot>
        </div>
    </div>
</template>
