<script setup>
import { defineEmits } from 'vue';

const props = defineProps({
    activeFilter: String,
});

const emits = defineEmits(['update:filter']);

function setFilter(filter) {
    emits('update:filter', filter);
}
</script>

<template>
    <div class="flex justify-start space-x-2 p-1 bg-gray-200 rounded-xl overflow-x-auto">
        <button
            :class="{'bg-indigo-600 text-white font-semibold': activeFilter === 'all', 'bg-white text-gray-700 font-medium hover:bg-gray-100': activeFilter !== 'all'}"
            class="px-4 py-2 text-sm rounded-lg whitespace-nowrap transition-all-colors"
            @click="setFilter('all')">
            All Projects
        </button>
        <button
            :class="{'bg-indigo-600 text-white font-semibold': activeFilter === 'manager', 'bg-white text-gray-700 font-medium hover:bg-gray-100': activeFilter !== 'manager'}"
            class="px-4 py-2 text-sm rounded-lg whitespace-nowrap transition-all-colors"
            @click="setFilter('manager')">
            Projects I Manage
        </button>
        <button
            :class="{'bg-indigo-600 text-white font-semibold': activeFilter === 'contributor', 'bg-white text-gray-700 font-medium hover:bg-gray-100': activeFilter !== 'contributor'}"
            class="px-4 py-2 text-sm rounded-lg whitespace-nowrap transition-all-colors"
            @click="setFilter('contributor')">
            My Projects
        </button>
    </div>
</template>
