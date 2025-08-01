<template>
    <div class="relative w-full">
        <textarea
            :id="id"
            ref="textareaRef"
            :value="modelValue"
            @input="handleInput"
            @keydown.enter.prevent="handleEnter"
            @keydown.up.prevent="handleArrowKey(-1)"
            @keydown.down.prevent="handleArrowKey(1)"
            @keydown.escape.prevent="handleEscape"
            @blur="hideDropdown"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            :rows="rows"
            :required="required"
        ></textarea>

        <div
            v-if="showDropdown"
            class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg"
            :style="{ top: dropdownPosition.top, left: dropdownPosition.left }"
        >
            <ul class="max-h-60 overflow-y-auto">
                <li
                    v-for="(placeholder, index) in filteredPlaceholders"
                    :key="placeholder.name"
                    @mousedown.prevent="insertPlaceholder(placeholder.name)"
                    class="cursor-pointer px-4 py-2 hover:bg-indigo-50"
                    :class="{ 'bg-indigo-100': highlightedIndex === index }"
                >
                    <span class="font-medium text-gray-900">{{ placeholder.name }}</span>
                    <p class="text-xs text-gray-500">{{ placeholder.description }}</p>
                </li>
                <li v-if="!filteredPlaceholders.length" class="px-4 py-2 text-gray-500 text-sm">
                    No placeholders found.
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, computed, watch, nextTick } from 'vue';

const props = defineProps({
    id: String,
    modelValue: String,
    definitions: {
        type: Array,
        default: () => [],
    },
    rows: {
        type: Number,
        default: 8,
    },
    required: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const textareaRef = ref(null);
const showDropdown = ref(false);
const searchTerm = ref('');
const highlightedIndex = ref(-1);

const dropdownPosition = ref({
    top: 'auto',
    left: 'auto',
});

// Computed property to filter placeholders based on the search term
const filteredPlaceholders = computed(() => {
    if (!searchTerm.value) {
        return props.definitions;
    }
    const lowerSearch = searchTerm.value.toLowerCase();
    return props.definitions.filter(p =>
        p.name.toLowerCase().includes(lowerSearch)
    );
});

// Watch for changes in the text to correctly position the dropdown
watch(() => props.modelValue, () => {
    if (showDropdown.value) {
        nextTick(() => positionDropdown());
    }
});

// Function to handle all user input
const handleInput = (event) => {
    emit('update:modelValue', event.target.value);
    const cursorPosition = event.target.selectionStart;
    const textBeforeCursor = event.target.value.substring(0, cursorPosition);
    const lastOpenBrackets = textBeforeCursor.lastIndexOf('{{');

    if (lastOpenBrackets !== -1 && cursorPosition - lastOpenBrackets > 1) {
        const potentialSearchTerm = textBeforeCursor.substring(lastOpenBrackets + 2);
        if (!potentialSearchTerm.includes('}')) {
            searchTerm.value = potentialSearchTerm.trim();
            showDropdown.value = true;
            highlightedIndex.value = 0;
            nextTick(() => positionDropdown());
            return;
        }
    }
    hideDropdown();
};

const handleEnter = () => {
    if (showDropdown.value && highlightedIndex.value !== -1) {
        insertPlaceholder(filteredPlaceholders.value[highlightedIndex.value].name);
    } else {
        // Allow a normal new line if dropdown is not active
        const textarea = textareaRef.value;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const newText = textarea.value.substring(0, start) + '\n' + textarea.value.substring(end);
        emit('update:modelValue', newText);

        nextTick(() => {
            const newCursorPosition = start + 1;
            textarea.setSelectionRange(newCursorPosition, newCursorPosition);
        });
    }
};

const handleArrowKey = (direction) => {
    if (showDropdown.value) {
        moveHighlight(direction);
    }
};

const handleEscape = () => {
    if (showDropdown.value) {
        hideDropdown();
    }
};

const hideDropdown = () => {
    showDropdown.value = false;
    searchTerm.value = '';
    highlightedIndex.value = -1;
};

const insertPlaceholder = (name) => {
    const textarea = textareaRef.value;
    const cursorPosition = textarea.selectionStart;
    const textBeforeCursor = textarea.value.substring(0, cursorPosition);
    const lastOpenBrackets = textBeforeCursor.lastIndexOf('{{');

    const newText = textarea.value.substring(0, lastOpenBrackets) + `{{ ${name} }}` + textarea.value.substring(cursorPosition);

    emit('update:modelValue', newText);

    nextTick(() => {
        const newCursorPosition = lastOpenBrackets + `{{ ${name} }}`.length;
        textarea.setSelectionRange(newCursorPosition, newCursorPosition);
        textarea.focus();
    });

    hideDropdown();
};

const moveHighlight = (direction) => {
    if (!filteredPlaceholders.value.length) return;

    let newIndex = highlightedIndex.value + direction;
    if (newIndex < 0) {
        newIndex = filteredPlaceholders.value.length - 1;
    } else if (newIndex >= filteredPlaceholders.value.length) {
        newIndex = 0;
    }
    highlightedIndex.value = newIndex;
};

const positionDropdown = () => {
    const textarea = textareaRef.value;
    const cursorPosition = textarea.selectionStart;
    const textBeforeCursor = textarea.value.substring(0, cursorPosition);
    const lines = textBeforeCursor.split('\n');
    const lineNum = lines.length - 1;
    const charInLine = lines[lineNum].length;

    const lineHeight = parseFloat(window.getComputedStyle(textarea).lineHeight);
    const charWidth = parseFloat(window.getComputedStyle(textarea).fontSize) * 0.6; // Rough estimate for monospace font

    dropdownPosition.value.top = `${textarea.offsetTop + (lineNum + 1) * lineHeight}px`;
    dropdownPosition.value.left = `${textarea.offsetLeft + charInLine * charWidth}px`;
};
</script>
