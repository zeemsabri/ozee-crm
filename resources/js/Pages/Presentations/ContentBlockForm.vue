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

        <!-- Image Block Form -->
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

        <!-- Step Card Form -->
        <div v-else-if="block.block_type === 'step_card'" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Step Number</label>
                <input
                    v-model.number="local.step_number"
                    @input="emitUpdate"
                    type="number"
                    placeholder="e.g., 1"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Step number"
                />
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Title</label>
                <input
                    v-model="local.title"
                    @input="emitUpdate"
                    placeholder="Step Title"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Step title"
                />
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Description</label>
                <textarea
                    v-model="local.description"
                    @input="emitUpdate"
                    placeholder="Step description"
                    class="w-full border border-gray-200 rounded-lg p-2 h-24 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Step description"
                ></textarea>
            </div>
        </div>

        <!-- Slogan Block Form -->
        <div v-else-if="block.block_type === 'slogan'" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Slogan Text</label>
                <input
                    v-model="local.text"
                    @input="emitUpdate"
                    placeholder="e.g., Innovate. Optimize. Grow."
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Slogan text"
                />
            </div>
        </div>

        <!-- Pricing Table Form -->
        <div v-else-if="block.block_type === 'pricing_table'" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Title</label>
                <input
                    v-model="local.title"
                    @input="emitUpdate"
                    placeholder="e.g., Pricing & Payment Schedule"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Pricing title"
                />
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Price Text</label>
                <input
                    v-model="local.price"
                    @input="emitUpdate"
                    placeholder="e.g., AUD 7,500 (+GST)"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Price text"
                />
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Payment Schedule (one per line)</label>
                <textarea
                    v-model="local.payment_schedule"
                    @input="emitUpdateArray"
                    placeholder="e.g., 25% upon project confirmation"
                    class="w-full border border-gray-200 rounded-lg p-2 h-24 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Payment schedule list"
                ></textarea>
            </div>
        </div>

        <!-- Timeline Table Form -->
        <div v-else-if="block.block_type === 'timeline_table'" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Title</label>
                <input
                    v-model="local.title"
                    @input="emitUpdate"
                    placeholder="e.g., Project Timeline"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Timeline title"
                />
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">Timeline (Phase | Duration, one per line)</label>
                <textarea
                    v-model="local.timeline"
                    @input="emitUpdateTimeline"
                    placeholder="e.g., Planning | 1-2 Weeks"
                    class="w-full border border-gray-200 rounded-lg p-2 h-24 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Timeline list"
                ></textarea>
            </div>
        </div>

        <!-- Details List Form -->
        <div v-else-if="block.block_type === 'details_list'" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">Details (one item per line)</label>
                <textarea
                    v-model="local.items"
                    @input="emitUpdateDetailsList"
                    placeholder="e.g., Prepared for: Benjamin Castledine"
                    class="w-full border border-gray-200 rounded-lg p-2 h-24 focus:ring-2 focus:ring-indigo-500"
                    aria-label="Details list"
                ></textarea>
            </div>
        </div>

        <!-- List with Icons Form -->
        <div v-else-if="block.block_type === 'list_with_icons'" class="space-y-4">
            <label class="block text-sm font-semibold text-gray-700">List Items (one per line)</label>
            <textarea
                v-model="local.items"
                @input="emitUpdateListWithIcons"
                class="w-full border border-gray-200 rounded-lg p-2 h-32 focus:ring-2 focus:ring-indigo-500"
                placeholder="Enter each list item on a new line."
            ></textarea>
        </div>

        <!-- Feature List Form -->
        <div v-else-if="block.block_type === 'feature_list'" class="space-y-4">
            <label class="block text-sm font-semibold text-gray-700">Feature Items (Title | Description, one per line)</label>
            <textarea
                v-model="local.items"
                @input="emitUpdateFeatureList"
                class="w-full border border-gray-200 rounded-lg p-2 h-32 focus:ring-2 focus:ring-indigo-500"
                placeholder="Title | Description of the feature."
            ></textarea>
        </div>

        <!-- Image Block Form -->
        <div v-else-if="block.block_type === 'image_block'" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700">Title</label>
                <input
                    v-model="local.title"
                    @input="emitUpdate"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    placeholder="Image Title"
                />
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700">Image URL</label>
                <input
                    v-model="local.url"
                    @input="emitUpdate"
                    class="w-full border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                    placeholder="e.g., https://example.com/image.jpg"
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

const local = reactive({});

function applyViewMapping(v) {
    const data = v || {};
    // Assign base data first
    Object.assign(local, data);
    // Then override array-shaped fields with user-friendly strings
    if (Array.isArray(data.payment_schedule)) {
        local.payment_schedule = data.payment_schedule.join('\n');
    }
    if (Array.isArray(data.timeline)) {
        // Support both seeded keys (phase, duration) and potential minified keys (ph, dur)
        local.timeline = data.timeline
            .map(t => `${(t && (t.phase ?? t.ph)) || ''} | ${(t && (t.duration ?? t.dur)) || ''}`)
            .join('\n');
    }
    if (Array.isArray(data.items)) {
        if (props.block.block_type === 'feature_list') {
            // Support both standard keys (title, description) and minified (t, dsc)
            local.items = data.items
                .map(item => `${(item && (item.title ?? item.t)) || ''} | ${(item && (item.description ?? item.dsc)) || ''}`)
                .join('\n');
        } else {
            local.items = data.items.join('\n');
        }
    }
}

// Initialize local with a mapped view of content_data
applyViewMapping(props.block.content_data);

// Keep local in sync when content_data changes
watch(() => props.block.content_data, (v) => { applyViewMapping(v); });

function buildPayload() {
    const data = { ...local };
    switch (props.block.block_type) {
        case 'pricing_table':
            if (typeof data.payment_schedule === 'string') {
                data.payment_schedule = data.payment_schedule.split('\n').map(s => s.trim()).filter(Boolean);
            }
            break;
        case 'timeline_table':
            if (typeof data.timeline === 'string') {
                data.timeline = data.timeline.split('\n').map(line => {
                    const parts = line.split('|').map(p => p.trim());
                    return { phase: parts[0], duration: parts[1] };
                }).filter(t => t.phase && t.duration);
            }
            break;
        case 'details_list':
        case 'list_with_icons':
            if (typeof data.items === 'string') {
                data.items = data.items.split('\n').map(s => s.trim()).filter(Boolean);
            }
            break;
        case 'feature_list':
            if (typeof data.items === 'string') {
                data.items = data.items.split('\n').map(line => {
                    const parts = line.split('|').map(p => p.trim());
                    return { title: parts[0], description: parts[1] };
                }).filter(it => it.title && it.description);
            }
            break;
    }
    return data;
}

const debounced = debounce(() => {
    emit('update', buildPayload());
}, 300);

function emitUpdate() {
    debounced();
}

function emitUpdateArray() {
    const arr = local.payment_schedule.split('\n').map(item => item.trim()).filter(Boolean);
    emit('update', { ...local, payment_schedule: arr });
}

function emitUpdateTimeline() {
    const timeline = local.timeline.split('\n').map(line => {
        const parts = line.split('|').map(p => p.trim());
        return { phase: parts[0], duration: parts[1] };
    }).filter(t => t.phase && t.duration);
    emit('update', { ...local, timeline });
}

function emitUpdateDetailsList() {
    const items = local.items.split('\n').map(item => item.trim()).filter(Boolean);
    emit('update', { ...local, items });
}

function emitUpdateListWithIcons() {
    const items = local.items.split('\n').map(item => item.trim()).filter(Boolean);
    emit('update', { ...local, items });
}

function emitUpdateFeatureList() {
    const items = local.items.split('\n').map(line => {
        const parts = line.split('|').map(p => p.trim());
        return { title: parts[0], description: parts[1] };
    }).filter(t => t.title && t.description);
    emit('update', { ...local, items });
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
