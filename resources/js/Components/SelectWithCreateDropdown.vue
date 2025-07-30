<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios'; // Assuming axios is globally available or imported

const props = defineProps({
    modelValue: {
        type: [String, Number], // Can be slug, new_ prefix, or actual ID
        default: null,
    },
    label: {
        type: String,
        default: 'Item Type',
    },
    placeholder: {
        type: String,
        default: 'Select or type an item type',
    },
    error: {
        type: String, // For validation errors passed from parent
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    apiEndpoint: { // New prop: API endpoint for creating new items
        type: String,
        required: true,
    },
    initialOptions: { // New prop: Initial list of options from parent
        type: Array,
        default: () => [],
    },
    // Optional: Prop to pass a global loading state from parent if needed
    isParentLoading: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['update:modelValue', 'change']);

const searchTerm = ref('');
const selectedItemType = ref(null); // Stores the single selected item type object {value, label}
const showSuggestions = ref(false);
const inputRef = ref(null);
const internalOptions = ref([]); // Reactive list of all options (initial + newly created)
const apiError = ref(''); // For errors from API calls
const creatingNewItem = ref(false); // Local loading state for API call

// Initialize internalOptions from initialOptions prop
watch(() => props.initialOptions, (newOptions) => {
    internalOptions.value = [...newOptions];
    // Re-evaluate selected item type if initialOptions change
    selectedItemType.value = findOptionByValue(props.modelValue);
}, { immediate: true, deep: true });

// Helper to find an option by its value (ID or slug/new_ prefix)
const findOptionByValue = (value) => {
    if (value === null || value === undefined || value === '') return null;

    // Try to find in the current internal options (which includes fetched and newly created)
    const found = internalOptions.value.find(opt => opt.value === value);
    if (found) {
        return found;
    }

    // If not found in internalOptions, and it's a 'new_' prefixed value (temporary client-side ID)
    // This handles cases where modelValue might be set to a new_ prefixed value before save.
    if (typeof value === 'string' && value.startsWith('new_')) {
        const parts = value.split('_');
        parts.shift(); // remove 'new'
        if (parts.length > 0) parts.pop(); // remove timestamp if it exists
        const label = parts.join(' ').replace(/-/g, ' '); // Convert slug-like to readable
        return { value: value, label: label };
    }
    return null;
};

// Initialize selectedItemType from modelValue on mount and watch
watch(() => props.modelValue, (newValue) => {
    selectedItemType.value = findOptionByValue(newValue);
    // When modelValue changes, ensure searchTerm is cleared to show only the chip
    searchTerm.value = '';
}, { immediate: true });

// Filter suggestions based on searchTerm
const filteredSuggestions = computed(() => {
    const lowerSearchTerm = searchTerm.value.toLowerCase();
    if (!lowerSearchTerm) {
        // When search term is empty, show all options, but prioritize selected one (if any)
        if (selectedItemType.value) {
            // Put selected at top, then others
            return [selectedItemType.value, ...internalOptions.value.filter(opt => opt.value !== selectedItemType.value.value)];
        }
        return internalOptions.value;
    }
    // Filter out items that are already selected (should only be one for single select)
    return internalOptions.value.filter(item =>
        item.label.toLowerCase().includes(lowerSearchTerm) &&
        (!selectedItemType.value || item.value !== selectedItemType.value.value)
    );
});

// Select an existing item type
const selectItemType = (itemType) => {
    if (props.disabled || creatingNewItem.value || props.isParentLoading) return;
    selectedItemType.value = itemType;
    emit('update:modelValue', itemType.value);
    emit('change', itemType.value);
    searchTerm.value = '';
    showSuggestions.value = false;
    nextTick(() => inputRef.value.focus());
};

// Create a new custom item type via API
const createNewItem = async () => {
    if (props.disabled || creatingNewItem.value || props.isParentLoading) return;

    const trimmedTerm = searchTerm.value.trim();
    if (!trimmedTerm) return;

    // Check if it already exists in current options (case-insensitive)
    const existingOption = internalOptions.value.find(
        (opt) => opt.label.toLowerCase() === trimmedTerm.toLowerCase()
    );

    if (existingOption) {
        selectItemType(existingOption);
        return;
    }

    // Check if the current selectedItemType already represents this new type (to prevent redundant emissions)
    const isAlreadySelectedAsNew = selectedItemType.value &&
        typeof selectedItemType.value.value === 'string' &&
        selectedItemType.value.value.startsWith('new_') &&
        selectedItemType.value.label.toLowerCase() === trimmedTerm.toLowerCase();

    if (isAlreadySelectedAsNew) {
        // If it's already selected as a client-side new_ type, just close suggestions
        showSuggestions.value = false;
        return;
    }

    apiError.value = ''; // Clear previous API errors
    creatingNewItem.value = true; // Start local loading indicator

    try {
        // Make API call to create the new item
        const response = await axios.post(props.apiEndpoint, { name: trimmedTerm });

        // Assuming backend returns the new item with its ID and label
        // Adjust response.data.item if your API returns different structure (e.g., response.data.project_type)
        const newItem = response.data.item || response.data.project_type; // Fallback for common naming

        if (newItem && newItem.id && newItem.name) {
            const newOption = { value: newItem.id, label: newItem.name };

            // Add the new item to internal options if it's not already there
            if (!internalOptions.value.some(opt => opt.value === newOption.value)) {
                internalOptions.value.push(newOption);
            }

            selectItemType(newOption); // Select the newly created item
        } else {
            apiError.value = 'API did not return expected new item data.';
        }
    } catch (error) {
        console.error('Error creating new item:', error);
        if (error.response && error.response.data && error.response.data.message) {
            apiError.value = error.response.data.message;
        } else {
            apiError.value = 'Failed to create new item. Please try again.';
        }
    } finally {
        creatingNewItem.value = false; // Stop local loading indicator
    }
};

// Remove the currently selected item type
const removeSelectedItemType = (event) => {
    event.stopPropagation();
    if (props.disabled || creatingNewItem.value || props.isParentLoading) return;
    selectedItemType.value = null;
    emit('update:modelValue', null);
    emit('change', null);
    searchTerm.value = '';
    showSuggestions.value = false;
    nextTick(() => inputRef.value.focus());
};

// Handle keyboard navigation (Enter key for selection/creation, Escape to close, Backspace for clear)
const handleKeydown = (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        createNewItem(); // Attempt to create or select
    } else if (event.key === 'Escape') {
        showSuggestions.value = false;
        searchTerm.value = '';
        if (inputRef.value) {
            inputRef.value.blur();
        }
    } else if (event.key === 'Backspace' && searchTerm.value === '' && selectedItemType.value) {
        removeSelectedItemType(event);
    }
};

// Handle input changes
const handleInput = (event) => {
    searchTerm.value = event.target.value;
    showSuggestions.value = true;
    apiError.value = ''; // Clear API error when user starts typing again
};

// Close suggestions when clicking outside
const handleClickOutside = (event) => {
    const inputElement = inputRef.value?.$el || inputRef.value;

    if (inputElement && !inputElement.contains(event.target) &&
        !event.target.closest('.item-type-suggestions')) {
        showSuggestions.value = false;
        // If an item is selected, revert searchTerm to empty after clicking outside
        if (selectedItemType.value) {
            searchTerm.value = '';
        } else {
            // If nothing is selected and user typed something, clear it on click outside
            searchTerm.value = '';
        }
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative">
        <InputLabel :value="label" class="mb-1" :required="required" />

        <div
            class="flex flex-wrap items-center gap-2 mb-2 p-2 border rounded-lg bg-white min-h-[44px] transition-all duration-150"
            :class="{
                'border-gray-300 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500': !error,
                'border-red-500 ring-red-500': error,
                'bg-gray-100 cursor-not-allowed': disabled || creatingNewItem || isParentLoading,
                'opacity-75': disabled || creatingNewItem || isParentLoading
            }"
        >
            <!-- Display the selected item type as a "chip" -->
            <span
                v-if="selectedItemType"
                class="inline-flex items-center px-3 py-1 text-sm font-medium bg-indigo-100 text-indigo-800 rounded-full shadow-sm"
            >
                {{ selectedItemType.label }}
                <button
                    v-if="!disabled && !creatingNewItem && !isParentLoading"
                    type="button"
                    @click="removeSelectedItemType"
                    class="ml-2 -mr-1 text-indigo-500 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-full p-0.5"
                    aria-label="Remove selected item type"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        ></path>
                    </svg>
                </button>
            </span>

            <!-- Input field for searching/typing -->
            <TextInput
                ref="inputRef"
                type="text"
                :placeholder="selectedItemType ? '' : placeholder"
                v-model="searchTerm"
                @keydown="handleKeydown"
                @input="handleInput"
                class="flex-grow min-w-[150px] border-none focus:ring-0 shadow-none p-0 bg-transparent text-gray-900"
                :disabled="disabled || creatingNewItem || isParentLoading"
            />

            <!-- Loading spinner for API call -->
            <div v-if="creatingNewItem" class="ml-2">
                <svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <!-- Suggestions Dropdown -->
        <div
            v-if="showSuggestions && !disabled && !creatingNewItem && !isParentLoading"
            class="absolute z-20 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto mt-1 transform-gpu item-type-suggestions"
        >
            <div
                v-for="option in filteredSuggestions"
                :key="option.value"
                @click="selectItemType(option)"
                class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer transition-colors duration-100"
                :class="{ 'bg-indigo-50 text-indigo-700': selectedItemType && option.value === selectedItemType.value }"
            >
                {{ option.label }}
            </div>
            <!-- Option to create new if search term doesn't match an existing one and is not empty -->
            <div
                v-if="searchTerm.trim() !== '' && !internalOptions.some(opt => opt.label.toLowerCase() === searchTerm.toLowerCase())"
                @click="createNewItem"
                class="px-4 py-2 text-sm text-indigo-700 bg-indigo-50 hover:bg-indigo-100 cursor-pointer border-t border-indigo-200 font-semibold transition-colors duration-100"
            >
                Add new type: "{{ searchTerm.trim() }}"
            </div>
            <div
                v-if="filteredSuggestions.length === 0 && searchTerm.trim() !== ''"
                class="px-4 py-2 text-sm text-gray-500"
            >
                No matching options found. Press <span class="font-semibold text-indigo-600">Enter</span> to add "{{ searchTerm.trim() }}" as a new type.
            </div>
            <div
                v-if="filteredSuggestions.length === 0 && searchTerm.trim() === '' && !selectedItemType"
                class="px-4 py-2 text-sm text-gray-500"
            >
                Start typing to search or select a type.
            </div>
        </div>

        <InputError :message="error || apiError" class="mt-2" />
    </div>
</template>

<style scoped>
/* Adjusting TextInput style for integration within the "chip" container */
.p-0 {
    padding: 0 !important;
}
.shadow-none {
    box-shadow: none !important;
}
.border-none {
    border: none !important;
}
.focus\:ring-0:focus {
    outline: none !important;
    box-shadow: none !important;
    border-color: transparent !important;
}
.bg-transparent {
    background-color: transparent !important;
}
/* Ensure the overall container (flex-wrap) has appropriate min-height */
div.flex-wrap {
    min-height: 44px; /* Adjust as needed for consistent height with other inputs */
}
</style>
