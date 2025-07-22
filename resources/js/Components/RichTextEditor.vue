<script setup>
import { ref, onMounted, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Write your content here...'
    },
    height: {
        type: String,
        default: '300px'
    }
});

const emit = defineEmits(['update:modelValue']);

const editorContent = ref('');
const editorRef = ref(null);

// Initialize editor content from modelValue
onMounted(() => {
    if (editorRef.value) {
        editorRef.value.innerHTML = props.modelValue;
    }
});

// Watch for changes in the editor content
const handleInput = () => {
    if (editorRef.value) {
        emit('update:modelValue', editorRef.value.innerHTML);
    }
};

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
    if (editorRef.value && newValue !== editorRef.value.innerHTML) {
        editorRef.value.innerHTML = newValue;
    }
});

// Toolbar button handlers
const formatText = (command, value = null) => {
    document.execCommand(command, false, value);
    handleInput();
    editorRef.value.focus();
};
</script>

<template>
    <div class="rich-text-editor">
        <div class="toolbar">
            <button type="button" @click="formatText('bold')" class="toolbar-button" title="Bold">
                <strong>B</strong>
            </button>
            <button type="button" @click="formatText('italic')" class="toolbar-button" title="Italic">
                <em>I</em>
            </button>
            <button type="button" @click="formatText('underline')" class="toolbar-button" title="Underline">
                <u>U</u>
            </button>
            <button type="button" @click="formatText('insertUnorderedList')" class="toolbar-button" title="Bullet List">
                • List
            </button>
            <button type="button" @click="formatText('insertOrderedList')" class="toolbar-button" title="Numbered List">
                1. List
            </button>
            <button type="button" @click="formatText('createLink', prompt('Enter link URL'))" class="toolbar-button" title="Insert Link">
                Link
            </button>
        </div>
        <div
            ref="editorRef"
            contenteditable="true"
            class="editor-content"
            :style="{ height: height }"
            @input="handleInput"
            :placeholder="placeholder"
        ></div>
    </div>
</template>

<style scoped>
.rich-text-editor {
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    overflow: hidden;
}

.toolbar {
    display: flex;
    padding: 0.5rem;
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.toolbar-button {
    margin-right: 0.5rem;
    padding: 0.25rem 0.5rem;
    background-color: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.25rem;
    cursor: pointer;
}

.toolbar-button:hover {
    background-color: #f1f5f9;
}

.editor-content {
    padding: 0.75rem;
    min-height: 150px;
    outline: none;
    overflow-y: auto;
}

.editor-content:empty:before {
    content: attr(placeholder);
    color: #9ca3af;
    pointer-events: none;
}
</style>
