<template>
    <div class="relative">
        <input
            v-model="search"
            @input="debouncedSearch"
            placeholder="Type to search by name, company, or email..."
            class="w-full border border-gray-200 rounded-lg p-2 pr-8 focus:ring-2 focus:ring-indigo-500"
            aria-label="Search presentable"
        />
        <svg class="absolute right-2 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
        <div
            v-if="showDropdown"
            class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto"
            role="listbox"
        >
            <div
                v-for="item in results"
                :key="item.id"
                class="p-2 hover:bg-indigo-50 cursor-pointer"
                @click="selectItem(item.id)"
                role="option"
                :aria-selected="modelValue === item.id"
            >
                {{ item.name || `Item #${item.id}` }}
            </div>
            <div v-if="!results.length" class="p-2 text-gray-500">No results found</div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { debounce } from 'lodash';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    type: { type: String, required: true },
    api: { type: Function, required: true },
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const results = ref([]);
const showDropdown = ref(false);

const debouncedSearch = debounce(async () => {
    if (search.value) {
        results.value = await props.api(search.value, props.type);
        showDropdown.value = true;
    } else {
        results.value = [];
        showDropdown.value = false;
    }
}, 300);

function selectItem(id) {
    emit('update:modelValue', id);
    showDropdown.value = false;
    search.value = '';
}

watch(() => props.modelValue, () => {
    showDropdown.value = false;
    search.value = '';
});
</script>

<style scoped>
/* Tailwind handles styling */
</style>
