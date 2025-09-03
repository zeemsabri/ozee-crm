<template>
    <div class="bg-white rounded-lg shadow-md p-6 space-y-4 transition-all duration-200">
        <!-- Heading Block Form -->
        <div v-if="block.block_type === 'heading'" class="flex items-center gap-3">
            <select
                v-model.number="local.level"
                @change="emitUpdate"
                class="border border-gray-200 rounded-lg p-2 bg-gray-50 focus:ring-2 focus:ring-indigo-500"
                aria-label="Heading level"
            >
                <option v-for="n in 3" :key="n" :value="n">H{{ n }}</option>
            </select>
            <input
                v-model="local.text"
                @input="emitUpdate"
                placeholder="Heading text"
                class="flex-1 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                aria-label="Heading text"
            />
        </div>

        <!-- Paragraph Block Form -->
        <div v-else-if="block.block_type === 'paragraph'">
            <label class="block text-sm text-gray-600 mb-1">Paragraph</label>
            <tiptap-editor
                v-model="local.text"
                @update:modelValue="emitUpdate"
                placeholder="Type your paragraph..."
                class="border border-gray-200 rounded-lg"
                aria-label="Paragraph editor"
            />
        </div>

        <!-- Feature Card Block Form -->
        <div v-else-if="block.block_type === 'feature_card'" class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Icon</label>
                <icon-picker
                    v-model="local.icon"
                    @update:modelValue="emitUpdate"
                    class="border border-gray-200 rounded-lg p-2"
                    aria-label="Icon selector"
                />
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Title</label>
                <input
                    v-model="local.title"
                    @input="emitUpdate"
                    placeholder="Feature title"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Feature title"
                />
            </div>
            <div class="col-span-2">
                <label class="block text-sm text-gray-600 mb-1">Description</label>
                <textarea
                    v-model="local.description"
                    @input="emitUpdate"
                    placeholder="Feature description"
                    class="w-full border border-gray-200 rounded-lg p-2 h-24 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Feature description"
                ></textarea>
            </div>
        </div>

        <!-- Image Block Form - NEW -->
        <div v-else-if="block.block_type === 'image'" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Image URL</label>
                <input
                    v-model="local.url"
                    @input="emitUpdate"
                    placeholder="e.g., https://example.com/image.jpg"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Image URL"
                />
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Alternative Text</label>
                <input
                    v-model="local.alt"
                    @input="emitUpdate"
                    placeholder="Describe the image for accessibility"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Alternative text for image"
                />
            </div>
        </div>

        <!-- Fallback for Unsupported Block Type -->
        <div v-else class="text-red-500 italic">Unsupported block type: {{ block.block_type }}</div>

        <!-- AI Suggestion Button -->
<!--        <button-->
<!--            @click="suggestAI"-->
<!--            class="btn btn-secondary text-sm flex items-center gap-2"-->
<!--            aria-label="Generate content with AI"-->
<!--        >-->
<!--            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">-->
<!--                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>-->
<!--            </svg>-->
<!--            Suggest with AI-->
<!--        </button>-->
    </div>
</template>

<script setup>
import { reactive, watch } from 'vue';
import { debounce } from '@/Utils/debounce';
import TiptapEditor from './Components/TiptapEditor.vue';
import IconPicker from './Components/IconPicker.vue';

const props = defineProps({ block: { type: Object, required: true } });
const emit = defineEmits(['update']);

const local = reactive({ ...(props.block.content_data || {}) });

watch(() => props.block.content_data, (v) => {
    Object.assign(local, v || {});
});

const debounced = debounce(() => {
    emit('update', { ...local });
}, 300);

function emitUpdate() {
    debounced();
}

function suggestAI() {
    // Placeholder for AI content suggestion (e.g., call xAI API)
    console.log('Request AI suggestion for:', props.block.block_type);
}
</script>

<style scoped>
.btn {
    @apply px-4 py-2 rounded-lg transition-colors;
}
.btn-secondary {
    @apply bg-indigo-100 text-indigo-700 hover:bg-indigo-200;
}
</style>
