<template>
    <div class="relative">
        <input
            :value="displayText"
            @input="onInput($event.target.value)"
            placeholder="Search or choose icon..."
            class="w-full border border-gray-200 rounded-lg p-2 pl-9 pr-8 focus:ring-2 focus:ring-indigo-500"
            aria-label="Icon search"
            @focus="showDropdown = true"
            @blur="delayHide"
        />
        <i v-if="modelValue" :class="modelValue" class="absolute left-2 top-1/2 -translate-y-1/2 text-indigo-500"></i>
        <svg class="absolute right-2 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
        <div
            v-if="showDropdown"
            class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto"
            role="listbox"
        >
            <div
                v-for="icon in filteredIcons"
                :key="icon"
                class="flex items-center gap-2 p-2 hover:bg-indigo-50 cursor-pointer"
                @click="selectIcon(icon)"
                role="option"
                :aria-selected="modelValue === icon"
            >
                <i :class="icon" class="text-indigo-500"></i>
                <span>{{ icon }}</span>
            </div>
            <div v-if="!filteredIcons.length" class="p-2 text-gray-500">No icons found</div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');
const showDropdown = ref(false);
const icons = ref(['fa-star', 'fa-heart', 'fa-check', 'fa-bell', 'fa-cog', 'fa-user']); // Example icons (replace with Font Awesome or similar)

const filteredIcons = computed(() =>
    icons.value.filter((icon) => icon.toLowerCase().includes(search.value.toLowerCase()))
);

const displayText = computed(() => search.value || props.modelValue || '');

function onInput(val) {
    search.value = val;
    showDropdown.value = true;
}

function selectIcon(icon) {
    emit('update:modelValue', icon);
    search.value = icon; // show the selection in the input
    showDropdown.value = false;
}

function delayHide() {
    setTimeout(() => { showDropdown.value = false; }, 200);
}
</script>

<style scoped>
/* Assumes Font Awesome is included via CDN or npm */
</style>
