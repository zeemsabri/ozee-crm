<template>
    <div class="relative" ref="containerEl">
        <!-- Input row: current preview + text input + toggle button -->
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 flex items-center justify-center rounded-md bg-gray-100 border border-gray-200">
                <i v-if="modelValue" :class="normalized(modelValue)" class="text-indigo-600 text-lg"></i>
            </div>
            <input
                :value="displayText"
                @input="onInput($event.target.value)"
                @keydown.enter.prevent="commitCustom()"
                @keydown.escape.stop.prevent="closePanel()"
                placeholder="Search icons or paste FA class (e.g., 'fa-brands fa-github')"
                class="flex-1 border border-gray-200 rounded-lg p-2 focus:ring-2 focus:ring-indigo-500"
                aria-label="Icon search or paste"
                @focus="openPanel()"
            />
            <button type="button" class="px-2 py-1 text-xs border rounded-md hover:bg-gray-50" @click="toggleCustom()">
                {{ showCustom ? 'Hide custom' : 'Use custom' }}
            </button>
            <button type="button" class="p-2 border rounded-md hover:bg-gray-50" @click="togglePanel()" aria-label="Open icon picker">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>

        <!-- Popover panel -->
        <div v-if="open" class="absolute z-10 mt-2 w-full bg-white border border-gray-200 rounded-lg shadow-lg p-3">
            <!-- Custom entry -->
            <div v-if="showCustom" class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Paste any Font Awesome classes</label>
                <div class="flex items-center gap-2">
                    <input
                        v-model="customInput"
                        @keydown.enter.prevent="applyCustom()"
                        placeholder="e.g., fa-solid fa-truck, fa-brands fa-github"
                        class="flex-1 border border-gray-200 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500"
                    />
                    <button class="px-3 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700" @click="applyCustom()">Apply</button>
                </div>
                <p class="mt-1 text-[11px] text-gray-500">Tip: Add a style prefix like fa-solid, fa-regular, or fa-brands. We default to fa-solid if omitted.</p>
            </div>

            <!-- Search and grid -->
            <div class="flex items-center gap-2 mb-2">
                <input
                    v-model="search"
                    placeholder="Search curated icons..."
                    class="flex-1 border border-gray-200 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-indigo-500"
                    aria-label="Filter icons"
                />
                <span class="text-xs text-gray-500">{{ filteredIcons.length }} results</span>
            </div>

            <div class="grid grid-cols-6 gap-2 max-h-56 overflow-auto" role="listbox">
                <button
                    v-for="icon in filteredIcons"
                    :key="icon"
                    type="button"
                    class="flex flex-col items-center justify-center gap-1 p-2 border rounded-md hover:bg-indigo-50"
                    :class="{ 'ring-2 ring-indigo-500': isSelected(icon) }"
                    @click="selectIcon(icon)"
                    role="option"
                    :aria-selected="isSelected(icon)"
                    :title="icon"
                >
                    <i :class="normalized(icon)" class="text-gray-700 text-xl"></i>
                    <span class="text-[10px] truncate w-full text-gray-500">{{ short(icon) }}</span>
                </button>
            </div>

            <div v-if="!filteredIcons.length" class="p-4 text-center text-gray-500 text-sm">No icons found</div>

            <div class="mt-3 flex justify-between items-center text-[11px] text-gray-500">
                <span>Using Font Awesome Kit for previews</span>
                <button type="button" class="underline" @click="toggleCustom()">{{ showCustom ? 'Hide custom input' : 'Use custom icon' }}</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const open = ref(false);
const showCustom = ref(false);
const customInput = ref('');

// Small curated set to keep UI light; users can paste any FA class.
const icons = ref([
    'fa-star','fa-heart','fa-check','fa-bell','fa-cog','fa-user','fa-bolt','fa-chart-line','fa-rocket',
    'fa-thumbs-up','fa-circle-info','fa-lightbulb','fa-shield','fa-lock','fa-wand-magic-sparkles','fa-calendar','fa-comment','fa-envelope'
]);

function normalized(icon) {
    if (!icon) return '';
    const trimmed = icon.trim();
    const hasStyle = /(fa-solid|fa-regular|fa-light|fa-thin|fa-brands|fa-duotone)/.test(trimmed);
    return hasStyle ? trimmed : `fa-solid ${trimmed}`;
}

const filteredIcons = computed(() =>
    icons.value.filter((icon) => icon.toLowerCase().includes(search.value.toLowerCase()))
);

// Only show current search text in the input; do not auto-fill with selected value to avoid filtering lock
const displayText = computed(() => search.value);

function onInput(val) {
    search.value = val;
    open.value = true;
}

function isSelected(icon) {
    const mv = props.modelValue || '';
    if (!mv) return false;
    // compare without style prefix
    const base = mv.split(' ').pop();
    return base === icon.replace(/^.*\s/, '');
}

function short(icon) {
    return icon.replace('fa-', '');
}

function selectIcon(icon) {
    emit('update:modelValue', icon);
    // Clear search so when user reopens, full list is visible
    search.value = '';
    open.value = false;
}

function commitCustom(fromBlur = false) {
    const val = (search.value || '').trim();
    if (!val) {
        if (fromBlur) open.value = false;
        return;
    }
    const valueToEmit = normalized(val);
    if (valueToEmit !== props.modelValue) emit('update:modelValue', valueToEmit);
    if (fromBlur) open.value = false;
}

function applyCustom() {
    const val = (customInput.value || search.value || '').trim();
    if (!val) return;
    const valueToEmit = normalized(val);
    if (valueToEmit !== props.modelValue) emit('update:modelValue', valueToEmit);
    open.value = false;
}

function openPanel() {
    open.value = true;
    if (!showCustom.value) search.value = '';
}
function togglePanel() {
    const willOpen = !open.value;
    open.value = willOpen;
    if (willOpen && !showCustom.value) {
        // When opening the grid, ensure search is empty to show all icons
        search.value = '';
    }
}
function closePanel() { open.value = false; }
function toggleCustom() { showCustom.value = !showCustom.value; if (showCustom.value) customInput.value = props.modelValue || ''; }

// click outside to close
function onDocClick(e) {
    const root = containerEl.value;
    if (!root) return;
    if (!root.contains(e.target)) closePanel();
}
const containerEl = ref(null);

onMounted(() => {
    document.addEventListener('mousedown', onDocClick);
    // Ensure FA kit is available in editor context for previews
    const existing = Array.from(document.scripts).find(s => s.src.includes('kit.fontawesome.com'));
    if (!existing) {
        const faScript = document.createElement('script');
        faScript.src = 'https://kit.fontawesome.com/6afed830a9.js';
        faScript.crossOrigin = 'anonymous';
        document.head.appendChild(faScript);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick);
});
</script>

<style scoped>
/* Grid buttons appear consistent */
</style>
