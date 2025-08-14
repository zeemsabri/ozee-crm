<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';
import { debounce } from '@/Utils/debounce';

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  label: { type: String, default: 'Type' },
  placeholder: { type: String, default: 'Select or type a value' },
  error: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  // API endpoints
  searchUrl: { type: String, required: true }, // e.g., '/api/transaction-types/search'
});

const emit = defineEmits(['update:modelValue', 'change']);

const searchTerm = ref('');
const selectedItem = ref(null);
const showSuggestions = ref(false);
const suggestions = ref([]);
const inputRef = ref(null);

const fetchSuggestionsRaw = async (query) => {
  try {
    const url = `${props.searchUrl}?query=${encodeURIComponent(query || '')}`;
    const { data } = await axios.get(url);
    suggestions.value = Array.isArray(data) ? data.map(i => ({ value: i.id, label: i.name })) : [];
    showSuggestions.value = true;
  } catch (e) {
    suggestions.value = [];
    showSuggestions.value = false;
  }
};

const fetchSuggestions = debounce(fetchSuggestionsRaw, 250);

watch(() => props.modelValue, (val) => {
  if (!val) {
    selectedItem.value = null;
    return;
  }
  if (typeof val === 'string' && val.startsWith('new_')) {
    // Keep it as a pseudo option
    const parts = val.split('_');
    parts.shift();
    if (parts.length > 0) parts.pop();
    const label = parts.join(' ').replace(/-/g, ' ');
    selectedItem.value = { value: val, label };
  } else {
    // For numeric IDs, attempt to match from suggestions list if present
    const found = suggestions.value.find(s => String(s.value) === String(val));
    if (found) selectedItem.value = found;
  }
}, { immediate: true });

const selectItem = (item) => {
  if (props.disabled) return;
  selectedItem.value = item;
  emit('update:modelValue', item.value);
  emit('change', item.value);
  searchTerm.value = '';
  showSuggestions.value = false;
  nextTick(() => inputRef.value?.focus());
};

const createNew = () => {
  if (props.disabled) return;
  const trimmed = searchTerm.value.trim();
  if (!trimmed) return;
  const newValue = `new_${trimmed.replace(/\s+/g, '-').toLowerCase()}_${Date.now()}`;
  const item = { value: newValue, label: trimmed };
  selectedItem.value = item;
  emit('update:modelValue', item.value);
  emit('change', item.value);
  searchTerm.value = '';
  showSuggestions.value = false;
  nextTick(() => inputRef.value?.focus());
};

const removeSelected = (e) => {
  e?.stopPropagation?.();
  if (props.disabled) return;
  selectedItem.value = null;
  emit('update:modelValue', null);
  emit('change', null);
  searchTerm.value = '';
  showSuggestions.value = false;
  nextTick(() => inputRef.value?.focus());
};

const handleKeydown = (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    createNew();
  } else if (e.key === 'Escape') {
    showSuggestions.value = false;
    searchTerm.value = '';
    inputRef.value?.blur?.();
  } else if (e.key === 'Backspace' && searchTerm.value === '' && selectedItem.value) {
    removeSelected(e);
  }
};

const handleInput = (e) => {
  searchTerm.value = e.target.value;
  showSuggestions.value = true;
  fetchSuggestions(searchTerm.value);
};

const handleClickOutside = (event) => {
  const el = inputRef.value?.$el || inputRef.value;
  if (el && !el.contains(event.target) && !event.target.closest('.basic-prop-suggestions')) {
    showSuggestions.value = false;
    searchTerm.value = '';
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  // initial load
  fetchSuggestions('');
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
  <div class="relative">
    <InputLabel :value="label" class="mb-1" />

    <div class="flex flex-wrap items-center gap-2 mb-2 p-2 border border-gray-300 rounded-lg bg-white min-h-[44px] focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 transition-all duration-150">
      <span v-if="selectedItem" class="inline-flex items-center px-3 py-1 text-sm font-medium bg-indigo-100 text-indigo-800 rounded-full shadow-sm">
        {{ selectedItem.label }}
        <button v-if="!disabled" type="button" @click="removeSelected" class="ml-2 -mr-1 text-indigo-500 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-full p-0.5" aria-label="Remove selected">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </span>

      <TextInput
        ref="inputRef"
        type="text"
        :placeholder="selectedItem ? '' : placeholder"
        v-model="searchTerm"
        @focus="showSuggestions = true"
        @keydown="handleKeydown"
        @input="handleInput"
        class="flex-grow min-w-[150px] border-none focus:ring-0 shadow-none p-0 bg-transparent text-gray-900"
        :disabled="disabled"
      />
    </div>

    <div v-if="showSuggestions && !disabled" class="absolute z-20 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto mt-1 transform-gpu basic-prop-suggestions">
      <div v-for="option in suggestions" :key="option.value" @mousedown.stop.prevent="selectItem(option)" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer transition-colors duration-100" :class="{ 'bg-indigo-50 text-indigo-700': selectedItem && option.value === selectedItem.value }">
        {{ option.label }}
      </div>
      <div v-if="searchTerm.trim() !== '' && !suggestions.some(opt => opt.label.toLowerCase() === searchTerm.toLowerCase())" @mousedown.stop.prevent="createNew" class="px-4 py-2 text-sm text-indigo-700 bg-indigo-50 hover:bg-indigo-100 cursor-pointer border-t border-indigo-200 font-semibold transition-colors duration-100">
        Add new: "{{ searchTerm.trim() }}"
      </div>
      <div v-if="suggestions.length === 0 && searchTerm.trim() === '' && !selectedItem" class="px-4 py-2 text-sm text-gray-500">
        Start typing to search or select an option.
      </div>
    </div>

    <InputError :message="error" class="mt-2" />
  </div>
</template>

<style scoped>
.p-0 { padding: 0 !important; }
.shadow-none { box-shadow: none !important; }
.border-none { border: none !important; }
.focus\:ring-0:focus { outline: none !important; box-shadow: none !important; border-color: transparent !important; }
.bg-transparent { background-color: transparent !important; }
</style>
