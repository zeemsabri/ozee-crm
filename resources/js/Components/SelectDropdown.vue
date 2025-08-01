<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'; // Add nextTick here

const props = defineProps({
    modelValue: {
        type: [String, Number, Object, null], // Allow null for initial empty selection
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Select an option',
    },
    valueKey: {
        type: String,
        default: 'value',
    },
    labelKey: {
        type: String,
        default: 'label',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    maxHeight: {
        type: String,
        default: '250px',
    },
    width: {
        type: String,
        default: 'full',
    },
    labelClass: {
        type: String,
        default: 'text-xs'
    }
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const searchTerm = ref(''); // New reactive state for search term
const dropdownRef = ref(null);
const searchInputRef = ref(null); // Reference to the search input

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

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
        searchTerm.value = ''; // Clear search term when closing
    }
};

// Close dropdown when pressing escape
const handleKeyDown = (event) => {
    if (event.key === 'Escape' && isOpen.value) {
        isOpen.value = false;
        searchTerm.value = ''; // Clear search term when closing
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeyDown);
});

// Compute the selected option label
const selectedLabel = computed(() => {
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
        return props.placeholder;
    }

    const selectedOption = props.options.find(option =>
        option[props.valueKey] === props.modelValue
    );

    return selectedOption ? selectedOption[props.labelKey] : props.placeholder;
});

// Handle option selection
const selectOption = (option) => {
    emit('update:modelValue', option[props.valueKey]);
    emit('change', option[props.valueKey]);
    isOpen.value = false;
    searchTerm.value = ''; // Clear search term after selection
};

// Toggle dropdown and focus search input if opening
const toggleDropdown = () => {
    if (!props.disabled) {
        isOpen.value = !isOpen.value;
        if (isOpen.value) {
            // Use nextTick to ensure input is rendered before focusing
            nextTick(() => { //
                if (searchInputRef.value) {
                    searchInputRef.value.focus();
                }
            });
        }
    }
};

// Watch modelValue to ensure placeholder is shown correctly if modelValue becomes empty
watch(() => props.modelValue, (newVal) => {
    if (newVal === null || newVal === undefined || newVal === '') {
        searchTerm.value = ''; // Reset search if selection is cleared externally
    }
});
</script>

<template>
    <div ref="dropdownRef" class="relative" :class="{ 'w-full': width === 'full' }">
        <button
            type="button"
            @click="toggleDropdown"
            :disabled="disabled"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm mt-1 block w-full text-left px-3 py-2 text-gray-700 bg-white relative flex items-center justify-between"
            :class="{ 'opacity-50 cursor-not-allowed': disabled }"
        >
            <span :class="{ 'text-gray-500': !props.modelValue }">{{ selectedLabel }}</span>
            <svg
                class="h-4 w-4 text-gray-500"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <div
            v-show="isOpen"
            class="absolute z-50 mt-1 w-full rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5"
        >
            <div class="p-2 border-b border-gray-200">
                <input
                    ref="searchInputRef"
                    type="text"
                    v-model="searchTerm"
                    @input="isOpen = true"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    placeholder="Search..."
                    @click.stop=""
                />
            </div>

            <div
                class="py-1 overflow-y-auto"
                :style="{ maxHeight: maxHeight }"
            >
                <div
                    v-for="option in filteredOptions"
                    :key="option[valueKey]"
                    @click="selectOption(option)"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer"
                    :class="{ 'bg-gray-100': option[valueKey] === modelValue }"
                >
                    {{ option[labelKey] }}
                </div>
                <div
                    v-if="filteredOptions.length === 0"
                    class="block px-4 py-2 text-sm text-gray-500"
                >
                    No options available
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Scoped styles specific to this component can go here if needed */
</style>
