<script setup>
import { ref, computed, watch, nextTick } from 'vue';

// Define props for the component
const props = defineProps({
    modelValue: { // Used for v-model binding, holds array of selected client IDs
        type: Array,
        default: () => [],
    },
    options: { // The array of all available client objects { id: ..., name: ... }
        type: Array,
        default: () => [],
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Select items',
    },
    labelKey: { // Key for the display label in options (e.g., 'name')
        type: String,
        default: 'name',
    },
    trackBy: { // Key for the unique identifier in options (e.g., 'id')
        type: String,
        default: 'id',
    },
});

// Define emits for the component
const emit = defineEmits(['update:modelValue']);

// Reactive state for the dropdown
const isDropdownOpen = ref(false);
const searchTerm = ref('');
const inputRef = ref(null);

// Computed property to filter options based on search term
const filteredOptions = computed(() => {
    if (!searchTerm.value) {
        return props.options;
    }
    const lowerSearchTerm = searchTerm.value.toLowerCase();
    return props.options.filter(item =>
        item[props.labelKey].toLowerCase().includes(lowerSearchTerm)
    );
});

// Watch for changes in modelValue to ensure internal state is in sync if parent updates it
watch(() => props.modelValue, (newVal) => {
    // Optional: If you need to react to external changes to modelValue, do so here.
    // For this simple multi-select, the internal toggle handles it.
}, { deep: true });


// Function to toggle selection of an item
const toggleSelection = (item) => {
    const currentSelection = [...props.modelValue]; // Create a mutable copy
    const index = currentSelection.findIndex(id => id === item[props.trackBy]);

    if (index === -1) {
        // Add item if not already selected
        currentSelection.push(item[props.trackBy]);
    } else {
        // Remove item if already selected
        currentSelection.splice(index, 1);
    }
    emit('update:modelValue', currentSelection); // Emit updated selection
    searchTerm.value = ''; // Clear search term after selection
};

// Function to get the label of a selected item ID
const getSelectedLabel = (id) => {
    const selectedItem = props.options.find(item => item[props.trackBy] === id);
    return selectedItem ? selectedItem[props.labelKey] : '';
};

// Check if an item is selected for styling purposes
const isSelected = (itemId) => {
    return props.modelValue.includes(itemId);
};
</script>

<template>
    <div class="relative" v-click-outside="() => { isDropdownOpen = false }">
        <!-- Input/Display area -->
        <div
            class="flex flex-wrap items-center w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm cursor-pointer min-h-[38px] bg-white focus-within:border-indigo-500 focus-within:ring-indigo-500"
            @click="() => { isDropdownOpen = !isDropdownOpen; nextTick(() => inputRef && inputRef.focus && inputRef.focus()); }"
        >
            <div v-for="id in modelValue" :key="id"
                 class="inline-flex items-center bg-indigo-100 text-indigo-800 text-xs font-medium px-2.5 py-0.5 rounded mr-2 mb-1"
            >
                {{ getSelectedLabel(id) }}
                <button type="button" @click.stop="toggleSelection(options.find(opt => opt[trackBy] === id))" class="ml-1 text-indigo-800 hover:text-indigo-900 focus:outline-none">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <input
                type="text"
                ref="inputRef"
                v-model="searchTerm"
                @input="isDropdownOpen = true"
                class="flex-grow border-none focus:ring-0 p-0 text-sm placeholder-gray-400 bg-transparent outline-none"
                :placeholder="modelValue.length === 0 ? placeholder : 'Search...'"
            >
        </div>

        <!-- Dropdown list -->
        <div v-if="isDropdownOpen"
             class="absolute z-50 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 max-h-60 overflow-y-auto"
        >
            <ul class="py-1">
                <li v-if="filteredOptions.length === 0" class="px-4 py-2 text-gray-500 text-sm">No items found.</li>
                <li v-for="item in filteredOptions" :key="item[trackBy]"
                    class="px-4 py-2 cursor-pointer hover:bg-gray-100 text-sm"
                    :class="{ 'bg-indigo-50 text-indigo-900': isSelected(item[trackBy]) }"
                    @click="toggleSelection(item)"
                >
                    {{ item[labelKey] }}
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
/* Scoped styles specific to this component can go here if needed */
/* By default, Tailwind classes handle most styling. */
</style>
