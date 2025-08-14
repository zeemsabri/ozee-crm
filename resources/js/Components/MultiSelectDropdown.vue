<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { ChevronDownIcon, XMarkIcon, CheckIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: {
        type: [String, Number, Object, Array, null],
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
    isMulti: {
        type: Boolean,
        default: false,
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
const searchTerm = ref('');
const dropdownRef = ref(null);
const searchInputRef = ref(null);

const filteredOptions = computed(() => {
    if (!searchTerm.value) {
        return props.options;
    }
    const lowerSearchTerm = searchTerm.value.toLowerCase();
    return props.options.filter(item =>
        item[props.labelKey].toLowerCase().includes(lowerSearchTerm)
    );
});

// For multi-select, check if an option is selected
const isSelected = (option) => {
    if (!props.isMulti || !props.modelValue) return false;
    return Array.isArray(props.modelValue) && props.modelValue.includes(option[props.valueKey]);
};

// Handle option selection
const selectOption = (option) => {
    if (props.isMulti) {
        let newValues = props.modelValue ? [...props.modelValue] : [];
        const value = option[props.valueKey];
        const index = newValues.indexOf(value);
        if (index > -1) {
            newValues.splice(index, 1);
        } else {
            newValues.push(value);
        }
        emit('update:modelValue', newValues);
        emit('change', newValues);
    } else {
        emit('update:modelValue', option[props.valueKey]);
        emit('change', option[props.valueKey]);
        isOpen.value = false;
        searchTerm.value = '';
    }
};

const removeTag = (valueToRemove) => {
    if (!props.isMulti || !props.modelValue) return;
    const newValues = props.modelValue.filter(v => v !== valueToRemove);
    emit('update:modelValue', newValues);
    emit('change', newValues);
};

const selectedLabels = computed(() => {
    if (!props.isMulti) {
        return props.placeholder;
    }
    const selectedOptions = props.options.filter(option => Array.isArray(props.modelValue) && props.modelValue.includes(option[props.valueKey]));
    return selectedOptions.map(option => option[props.labelKey]);
});

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
        searchTerm.value = '';
    }
};

const handleKeyDown = (event) => {
    if (event.key === 'Escape' && isOpen.value) {
        isOpen.value = false;
        searchTerm.value = '';
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

const toggleDropdown = () => {
    if (!props.disabled) {
        isOpen.value = !isOpen.value;
        if (isOpen.value) {
            nextTick(() => {
                if (searchInputRef.value) {
                    searchInputRef.value.focus();
                }
            });
        }
    }
};

watch(() => props.modelValue, (newVal) => {
    if (newVal === null || newVal === undefined || (props.isMulti && Array.isArray(newVal) && newVal.length === 0)) {
        searchTerm.value = '';
    }
}, { deep: true });
</script>

<template>
    <div ref="dropdownRef" class="relative" :class="{ 'w-full': width === 'full' }">
        <button
            type="button"
            @click="toggleDropdown"
            :disabled="disabled"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm mt-1 block w-full text-left px-3 py-2 text-gray-700 bg-white relative flex items-center justify-between"
            :class="{ 'opacity-50 cursor-not-allowed': disabled }"
        >
            <div v-if="isMulti" class="flex flex-wrap gap-1 items-center">
                <span v-if="selectedLabels.length === 0" class="text-gray-500">{{ placeholder }}</span>
                <span v-for="label in selectedLabels" :key="label" class="bg-indigo-100 text-indigo-800 px-2 py-1 rounded-full text-xs font-medium flex items-center gap-1">
                    {{ label }}
                    <XMarkIcon class="w-3 h-3 cursor-pointer hover:text-indigo-600" @click.stop="removeTag(options.find(o => o[labelKey] === label)[valueKey])" />
                </span>
            </div>
            <span v-else :class="{ 'text-gray-500': !modelValue }">{{ selectedLabels }}</span>
            <ChevronDownIcon
                class="h-5 w-5 text-gray-500"
            />
        </button>

        <div
            v-show="isOpen"
            class="absolute z-50 mt-1 w-full rounded-xl shadow-lg bg-white ring-1 ring-black ring-opacity-5"
        >
            <div class="p-2 border-b border-gray-200">
                <input
                    ref="searchInputRef"
                    type="text"
                    v-model="searchTerm"
                    @input="isOpen = true"
                    class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
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
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer flex items-center justify-between"
                    :class="{ 'bg-gray-100': isSelected(option) }"
                >
                    {{ option[labelKey] }}
                    <CheckIcon v-if="isSelected(option)" class="w-4 h-4 text-indigo-600" />
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
