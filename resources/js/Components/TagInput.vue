<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
// Ensure you have a debounce utility, or use the one provided in Utils/debounce.js
import { debounce } from '@/Utils/debounce';
import axios from 'axios'; // Import axios for API calls

const props = defineProps({
    modelValue: {
        type: Array, // Expects an array of tag IDs: [1, 5, 10] or temp IDs like ['new_my-tag']
        default: () => [],
    },
    label: {
        type: String,
        default: 'Tags',
    },
    placeholder: {
        type: String,
        default: 'Add tags...',
    },
    error: {
        type: String,
        default: '',
    },
    // For displaying existing tags when editing a project.
    // This should be an array of objects: [{ id: 1, name: 'Tag One' }]
    initialTags: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const searchTerm = ref('');
const suggestions = ref([]); // Stores tag objects from API search results
const selectedTags = ref([]); // Stores tag objects that are currently selected/added
const showSuggestions = ref(false);
const inputRef = ref(null); // Reference to the TextInput for focus management

// Initialize selectedTags from initialTags prop once
watch(() => props.initialTags, (newTags) => {
    // Only initialize if newTags are provided and we haven't already populated selectedTags
    if (newTags && newTags.length > 0 && selectedTags.value.length === 0) {
        // Filter out duplicates based on id if initialTags has them, ensuring unique display
        const uniqueNewTags = newTags.filter(
            (newTag) => !selectedTags.value.some((selected) => selected.id === newTag.id)
        );
        selectedTags.value.push(...uniqueNewTags);
        updateModelValue(); // Emit updated modelValue after initialization
    }
}, { immediate: true });

// Function to update the v-model for parent component (emits array of IDs)
const updateModelValue = () => {
    emit('update:modelValue', selectedTags.value.map((tag) => tag.id));
};

// Debounced function to fetch tags from API
const fetchTags = debounce(async (query) => {
    if (query.length < 2) { // Require at least 2 characters for search to reduce API calls
        suggestions.value = [];
        showSuggestions.value = false;
        return;
    }
    try {
        const response = await axios.get(`/api/tags/search?query=${query}`);
        suggestions.value = response.data;
        showSuggestions.value = true;
    } catch (err) {
        console.error('Error fetching tags:', err);
        suggestions.value = [];
        showSuggestions.value = false;
    }
}, 300); // 300ms debounce delay

// Watch searchTerm and call debounced fetchTags
watch(searchTerm, (newSearchTerm) => {
    if (!props.disabled) { // Only fetch if not disabled
        fetchTags(newSearchTerm);
    }
});

// Add a selected tag from suggestions
const addExistingTag = (tag) => {
    if (!selectedTags.value.some((t) => t.id === tag.id)) {
        selectedTags.value.push(tag);
        searchTerm.value = ''; // Clear search term
        suggestions.value = []; // Clear suggestions
        showSuggestions.value = false; // Hide suggestions
        updateModelValue(); // Update parent v-model
    }
    nextTick(() => inputRef.value.focus()); // Re-focus input
};

// Add a new tag (if it doesn't exist in suggestions or selected tags)
const addNewTag = () => {
    if (props.disabled) return;

    const trimmedTerm = searchTerm.value.trim();
    if (!trimmedTerm) return;

    // Check if it already exists as a suggestion (case-insensitive)
    const existingSuggestion = suggestions.value.find(
        (s) => s.name.toLowerCase() === trimmedTerm.toLowerCase()
    );

    // Check if it's already selected (case-insensitive)
    const alreadySelected = selectedTags.value.some(
        (t) => t.name.toLowerCase() === trimmedTerm.toLowerCase()
    );

    if (existingSuggestion) {
        // If it's an existing suggestion, add it as an existing tag
        addExistingTag(existingSuggestion);
    } else if (!alreadySelected) {
        // If it's genuinely new, add it with a temporary string ID (e.g., 'new_my-new-tag')
        // The backend will assign a real numeric ID upon saving and creating it.
        const newTag = { id: `new_${trimmedTerm.replace(/\s+/g, '-').toLowerCase()}_${Date.now()}`, name: trimmedTerm };
        selectedTags.value.push(newTag);
        searchTerm.value = '';
        suggestions.value = [];
        showSuggestions.value = false;
        updateModelValue();
    } else {
        // Tag already selected, just clear search term
        searchTerm.value = '';
        suggestions.value = [];
        showSuggestions.value = false;
    }
    nextTick(() => inputRef.value.focus());
};

// Remove a selected tag
const removeTag = (tagToRemove) => {
    if (props.disabled) return;
    selectedTags.value = selectedTags.value.filter((tag) => tag.id !== tagToRemove.id);
    updateModelValue();
    nextTick(() => inputRef.value.focus());
};

// Handle backspace/delete keypress to remove last tag if search input is empty
const handleBackspaceDelete = () => {
    if (props.disabled) return;
    if (searchTerm.value === '' && selectedTags.value.length > 0) {
        removeTag(selectedTags.value[selectedTags.value.length - 1]);
    }
};

// Filter suggestions to exclude already selected tags
const filteredSuggestions = computed(() => {
    return suggestions.value.filter(
        (suggestion) => !selectedTags.value.some((selected) => selected.id === suggestion.id)
    );
});

// Close suggestions when clicking outside
const handleClickOutside = (event) => {
    if (inputRef.value && !inputRef.value.$el.contains(event.target)) {
        showSuggestions.value = false;
        searchTerm.value = ''; // Clear search term when focus leaves
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
            <span
                v-for="tag in selectedTags"
                :key="tag.id"
                class="inline-flex items-center px-3 py-1 text-sm font-medium bg-indigo-100 text-indigo-800 rounded-full shadow-sm"
            >
                {{ tag.name }}
                <button
                    v-if="!disabled"
                    type="button"
                    @click="removeTag(tag)"
                    class="ml-2 -mr-1 text-indigo-500 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-full p-0.5"
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
            <TextInput
                ref="inputRef"
                type="text"
                :placeholder="selectedTags.length === 0 ? placeholder : ''"
                v-model="searchTerm"
                @focus="showSuggestions = true"
                @keydown.enter.prevent="addNewTag"
                @keydown.tab="showSuggestions = false"
                @keydown.delete="handleBackspaceDelete"
                class="flex-grow min-w-[100px] border-none focus:ring-0 shadow-none p-0 bg-transparent text-gray-900"
                :disabled="disabled"
            />
        </div>

        <div
            v-if="showSuggestions && (filteredSuggestions.length > 0 || searchTerm.length >= 2 || (filteredSuggestions.length === 0 && searchTerm.length < 2)) && !disabled"
            class="absolute z-20 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto mt-1 transform-gpu"
        >
            <div
                v-for="suggestion in filteredSuggestions"
                :key="suggestion.id"
                @click="addExistingTag(suggestion)"
                class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer transition-colors duration-100"
            >
                {{ suggestion.name }}
            </div>
            <div
                v-if="!suggestions.some(s => s.name.toLowerCase() === searchTerm.toLowerCase()) && searchTerm.length >= 2"
                @click="addNewTag"
                class="px-4 py-2 text-sm text-indigo-700 bg-indigo-50 hover:bg-indigo-100 cursor-pointer border-t border-indigo-200 font-semibold transition-colors duration-100"
            >
                Add new tag: "{{ searchTerm }}"
            </div>
            <div
                v-if="filteredSuggestions.length === 0 && searchTerm.length < 2"
                class="px-4 py-2 text-sm text-gray-500"
            >
                Type at least 2 characters to search for tags.
            </div>
            <div
                v-if="filteredSuggestions.length === 0 && searchTerm.length >= 2 && !suggestions.some(s => s.name.toLowerCase() === searchTerm.toLowerCase())"
                class="px-4 py-2 text-sm text-gray-500"
            >
                No matching tags found. Press <span class="font-semibold text-indigo-600">Enter</span> to add "{{ searchTerm }}" as a new tag.
            </div>
        </div>

        <InputError :message="error" class="mt-2" />
    </div>
</template>

<style scoped>
/* Adjusting TextInput style for integration within tag pills */
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
</style>
