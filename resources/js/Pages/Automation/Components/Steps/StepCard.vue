<script setup>
import { TrashIcon } from 'lucide-vue-next';

const props = defineProps({
    icon: String,
    title: String,
    // The presence of the onDelete function determines if the delete button is shown.
    onDelete: Function,
});

const emit = defineEmits(['delete']);
</script>

<template>
    <div class="bg-white rounded-lg shadow-md border border-gray-200 w-full max-w-md">
        <div class="flex items-center justify-between p-3 bg-gray-50 border-b rounded-t-lg">
            <div class="flex items-center space-x-2">
                <span class="text-xl">{{ icon }}</span>
                <h3 class="font-bold text-gray-700">{{ title }}</h3>
            </div>
            <button v-if="props.onDelete" @click="$emit('delete')" class="text-gray-400 hover:text-red-500 p-1 rounded-md">
                <TrashIcon class="h-4 w-4" />
            </button>
        </div>
        <div class="p-4 space-y-4">
            <slot />
        </div>
    </div>
</template>
