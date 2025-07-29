<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import TextInput from '@/Components/TextInput.vue'; // Using TextInput for the main input
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    modelValue: {
        type: String, // Expects a single project type value (slug or new_ prefix)
        default: null,
    },
    label: {
        type: String,
        default: 'Project Type',
    },
    placeholder: {
        type: String,
        default: 'Select or type a project type',
    },
    error: {
        type: String,
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
});

const emit = defineEmits(['update:modelValue', 'change']);

const searchTerm = ref(''); // What the user is typing for search
const selectedProjectType = ref(null); // Stores the single selected project type object {value, label}
const showSuggestions = ref(false); // Controls visibility of suggestions dropdown
const inputRef = ref(null); // Reference to the TextInput element

// Hardcoded list of common project types
const projectTypeOptions = computed(() => [
    { value: 'wix-website', label: 'Wix Website' },
    { value: 'shopify-website', label: 'Shopify Website' },
    { value: 'wordpress-website', label: 'WordPress Website' },
    { value: 'seo-service', label: 'SEO Service' },
    { value: 'social-media-management', label: 'Social Media Management' },
    { value: 'google-ads-campaign', label: 'Google Ads Campaign' },
    { value: 'mobile-app-development', label: 'Mobile App Development' },
    { value: 'custom-web-application', label: 'Custom Web Application' },
    { value: 'e-commerce-solution', label: 'E-commerce Solution' },
    { value: 'branding-identity', label: 'Branding & Identity' },
    { value: 'content-creation', label: 'Content Creation' },
    { value: 'email-marketing', label: 'Email Marketing' },
    { value: 'crm-integration', label: 'CRM Integration' },
    { value: 'data-analytics', label: 'Data Analytics' },
    { value: 'ui-ux-design', label: 'UI/UX Design' },
    // Add more common types here
]);

// Helper to find an option by its value (including new_ prefixed ones)
const findOptionByValue = (value) => {
    if (!value) return null;

    // First, check predefined options
    const foundPredefined = projectTypeOptions.value.find(opt => opt.value === value);
    if (foundPredefined) {
        return foundPredefined;
    }

    // If it's a new_ prefixed value, reconstruct its label for display
    if (typeof value === 'string' && value.startsWith('new_')) {
        const parts = value.split('_');
        parts.shift(); // remove 'new'
        if (parts.length > 0) parts.pop(); // remove timestamp if it exists
        const label = parts.join(' ').replace(/-/g, ' '); // Convert slug-like to readable
        return { value: value, label: label };
    }
    return null;
};

// Initialize selectedProjectType from modelValue on mount and watch
watch(() => props.modelValue, (newValue) => {
    selectedProjectType.value = findOptionByValue(newValue);
    // When modelValue changes, ensure searchTerm is cleared to show only the chip
    searchTerm.value = '';
}, { immediate: true });

// Filter suggestions based on searchTerm
const filteredSuggestions = computed(() => {
    const lowerSearchTerm = searchTerm.value.toLowerCase();
    if (!lowerSearchTerm) {
        // When search term is empty, show all options, but prioritize selected one (if any)
        if (selectedProjectType.value) {
            // Put selected at top, then others
            return [selectedProjectType.value, ...projectTypeOptions.value.filter(opt => opt.value !== selectedProjectType.value.value)];
        }
        return projectTypeOptions.value;
    }
    // Filter out items that are already selected (should only be one for single select)
    return projectTypeOptions.value.filter(item =>
        item.label.toLowerCase().includes(lowerSearchTerm) &&
        (!selectedProjectType.value || item.value !== selectedProjectType.value.value)
    );
});

// Select an existing project type
const selectProjectType = (projectType) => {
    if (props.disabled) return;
    selectedProjectType.value = projectType;
    emit('update:modelValue', projectType.value);
    emit('change', projectType.value);
    searchTerm.value = ''; // Clear search term after selection - THIS IS KEY FOR DUPLICATE TEXT
    showSuggestions.value = false; // Hide suggestions
    nextTick(() => inputRef.value.focus()); // Re-focus the input
};

// Create a new custom project type
const createNewProjectType = () => {
    if (props.disabled) return;

    const trimmedTerm = searchTerm.value.trim();
    if (!trimmedTerm) return;

    // Check if it already exists in predefined options (case-insensitive)
    const existingOption = projectTypeOptions.value.find(
        (opt) => opt.label.toLowerCase() === trimmedTerm.toLowerCase()
    );

    if (existingOption) {
        selectProjectType(existingOption); // Select existing one if match found
        return;
    }

    // Check if the current selectedProjectType already represents this new type (to prevent redundant emissions)
    const isAlreadySelectedAsNew = selectedProjectType.value &&
        selectedProjectType.value.value.startsWith('new_') &&
        selectedProjectType.value.label.toLowerCase() === trimmedTerm.toLowerCase();

    if (!isAlreadySelectedAsNew) {
        const newTypeValue = `new_${trimmedTerm.replace(/\s+/g, '-').toLowerCase()}_${Date.now()}`;
        const newType = { value: newTypeValue, label: trimmedTerm };
        selectedProjectType.value = newType;
        emit('update:modelValue', newType.value);
        emit('change', newType.value);
        searchTerm.value = ''; // Clear search term after creation - THIS IS KEY FOR DUPLICATE TEXT
    }

    showSuggestions.value = false; // Hide suggestions
    nextTick(() => inputRef.value.focus()); // Re-focus the input
};

// Remove the currently selected project type
const removeSelectedProjectType = (event) => {
    event.stopPropagation(); // Prevent dropdown from opening when clearing
    if (props.disabled) return;
    selectedProjectType.value = null;
    emit('update:modelValue', null);
    emit('change', null);
    searchTerm.value = ''; // Clear search term
    showSuggestions.value = false; // Ensure suggestions are hidden
    nextTick(() => inputRef.value.focus()); // Focus input for new selection
};

// Handle keyboard navigation (Enter key for selection/creation, Escape to close, Backspace for clear)
const handleKeydown = (event) => {
    if (event.key === 'Enter') {
        event.preventDefault(); // Prevent form submission
        createNewProjectType(); // Attempt to create or select
    } else if (event.key === 'Escape') {
        showSuggestions.value = false;
        searchTerm.value = ''; // Clear search term when escaping
        if (inputRef.value) {
            inputRef.value.blur(); // Blur the input
        }
    } else if (event.key === 'Backspace' && searchTerm.value === '' && selectedProjectType.value) {
        // Allow backspace to clear the selected tag if input is empty
        removeSelectedProjectType(event);
    }
};

// Handle input changes
const handleInput = (event) => {
    searchTerm.value = event.target.value;
    showSuggestions.value = true; // Always show suggestions when typing
};

// Close suggestions when clicking outside
const handleClickOutside = (event) => {
    // Check if the click is outside the input and outside the suggestions container
    // Use inputRef.value.$el if inputRef is a component, otherwise inputRef.value
    const inputElement = inputRef.value?.$el || inputRef.value;

    if (inputElement && !inputElement.contains(event.target) &&
        !event.target.closest('.project-type-suggestions')) {
        showSuggestions.value = false;
        // If an item is selected, revert searchTerm to empty after clicking outside
        if (selectedProjectType.value) {
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
        <InputLabel :value="label" class="mb-1" />

        <div class="flex flex-wrap items-center gap-2 mb-2 p-2 border border-gray-300 rounded-lg bg-white min-h-[44px] focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 transition-all duration-150">
            <!-- Display the selected project type as a "chip" similar to tags -->
            <span
                v-if="selectedProjectType"
                class="inline-flex items-center px-3 py-1 text-sm font-medium bg-indigo-100 text-indigo-800 rounded-full shadow-sm"
            >
                {{ selectedProjectType.label }}
                <button
                    v-if="!disabled"
                    type="button"
                    @click="removeSelectedProjectType"
                    class="ml-2 -mr-1 text-indigo-500 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-full p-0.5"
                    aria-label="Remove selected project type"
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
                :placeholder="selectedProjectType ? '' : placeholder"
                v-model="searchTerm"
                @keydown="handleKeydown"
                @input="handleInput"
                class="flex-grow min-w-[150px] border-none focus:ring-0 shadow-none p-0 bg-transparent text-gray-900"
                :disabled="disabled"
            />
        </div>

        <!-- Suggestions Dropdown -->
        <div
            v-if="showSuggestions && !disabled"
            class="absolute z-20 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto mt-1 transform-gpu project-type-suggestions"
        >
            <div
                v-for="option in filteredSuggestions"
                :key="option.value"
                @click="selectProjectType(option)"
                class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer transition-colors duration-100"
                :class="{ 'bg-indigo-50 text-indigo-700': selectedProjectType && option.value === selectedProjectType.value }"
            >
                {{ option.label }}
            </div>
            <!-- Option to create new if search term doesn't match an existing one and is not empty -->
            <div
                v-if="searchTerm.trim() !== '' && !projectTypeOptions.some(opt => opt.label.toLowerCase() === searchTerm.toLowerCase())"
                @click="createNewProjectType"
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
                v-if="filteredSuggestions.length === 0 && searchTerm.trim() === '' && !selectedProjectType"
                class="px-4 py-2 text-sm text-gray-500"
            >
                Start typing to search or select a type.
            </div>
        </div>

        <InputError :message="error" class="mt-2" />
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
