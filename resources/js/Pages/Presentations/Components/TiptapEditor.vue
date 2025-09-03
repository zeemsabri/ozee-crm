<template>
    <div class="tiptap-editor border border-gray-200 rounded-lg p-2" :class="{ 'focus:ring-2 focus:ring-indigo-500': focused }">
        <editor-content :editor="editor" />
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder'; // Add this line

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Type here...' },
});
const emit = defineEmits(['update:modelValue']);

const focused = ref(false);
const editor = ref(null);

onMounted(() => {
    editor.value = new Editor({
        extensions: [StarterKit, Placeholder.configure({ placeholder: props.placeholder })],
        content: props.modelValue,
        onUpdate: ({ editor }) => {
            emit('update:modelValue', editor.getHTML());
        },
        onFocus: () => { focused.value = true; },
        onBlur: () => { focused.value = false; },
    });
});

onBeforeUnmount(() => {
    if (editor.value) editor.value.destroy();
});

watch(() => props.modelValue, (newValue) => {
    if (editor.value && newValue !== editor.value.getHTML()) {
        editor.value.commands.setContent(newValue);
    }
});
</script>

<style scoped>
.tiptap-editor :deep(.ProseMirror) {
    @apply min-h-[100px] outline-none p-2;
}
.tiptap-editor :deep(.ProseMirror p.is-empty::before) {
    @apply text-gray-400;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
