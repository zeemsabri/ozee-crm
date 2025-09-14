<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  options: { type: Array, default: () => [] }, // [{ value, label }]
  placeholder: { type: String, default: 'Select...' },
  valueKey: { type: String, default: 'value' },
  labelKey: { type: String, default: 'label' },
  maxHeight: { type: String, default: '260px' },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const isOpen = ref(false);
const triggerRef = ref(null);
const portalRef = ref(null);
const search = ref('');
const coords = ref({ top: 0, left: 0, width: 0 });

const selectedValues = computed(() => Array.isArray(props.modelValue) ? props.modelValue : []);
const selectedLabels = computed(() => {
  const map = new Map(props.options.map(o => [o[props.valueKey], o[props.labelKey]]));
  return selectedValues.value.map(v => map.get(v)).filter(Boolean);
});

const filteredOptions = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return props.options;
  return props.options.filter(o => String(o[props.labelKey] || '').toLowerCase().includes(q));
});

function open() {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
  nextTick(() => positionPanel());
}

function positionPanel() {
  const el = triggerRef.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  coords.value = { top: rect.bottom + window.scrollY, left: rect.left + window.scrollX, width: rect.width };
}

function onWindowChange() {
  if (isOpen.value) positionPanel();
}

function outsideClick(e) {
  const clickedTrigger = triggerRef.value && triggerRef.value.contains(e.target);
  const clickedPanel = portalRef.value && portalRef.value.contains(e.target);
  if (!clickedTrigger && !clickedPanel) {
    isOpen.value = false;
  }
}

function toggleValue(val) {
  const set = new Set(selectedValues.value);
  if (set.has(val)) set.delete(val); else set.add(val);
  const arr = Array.from(set);
  emit('update:modelValue', arr);
  emit('change', arr);
}

function removeTag(val) {
  toggleValue(val);
}

onMounted(() => {
  window.addEventListener('scroll', onWindowChange, true);
  window.addEventListener('resize', onWindowChange);
  document.addEventListener('click', outsideClick);
});

onUnmounted(() => {
  window.removeEventListener('scroll', onWindowChange, true);
  window.removeEventListener('resize', onWindowChange);
  document.removeEventListener('click', outsideClick);
});
</script>

<template>
  <div class="relative" ref="triggerRef">
    <button type="button" @click="open" :disabled="disabled"
            class="w-full inline-flex items-center justify-between rounded-md border border-gray-300 bg-white px-2 py-1.5 text-left text-sm shadow-sm hover:bg-gray-50 disabled:opacity-60">
      <span class="flex flex-wrap gap-1 items-center">
        <template v-if="selectedLabels.length === 0">
          <span class="text-gray-500">{{ placeholder }}</span>
        </template>
        <template v-else>
          <span v-for="(label, idx) in selectedLabels" :key="idx" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs">
            {{ label }}
            <svg @click.stop="removeTag(selectedValues[idx])" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 cursor-pointer" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 8.586 5.293 3.879A1 1 0 1 0 3.879 5.293L8.586 10l-4.707 4.707a1 1 0 1 0 1.414 1.414L10 11.414l4.707 4.707a1 1 0 0 0 1.414-1.414L11.414 10l4.707-4.707a1 1 0 1 0-1.414-1.414L10 8.586Z" clip-rule="evenodd"/></svg>
          </span>
        </template>
      </span>
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.25 8.29a.75.75 0 0 1-.02-1.08Z" clip-rule="evenodd"/></svg>
    </button>

    <teleport to="body">
      <div v-if="isOpen" ref="portalRef"
           class="fixed z-[9999] bg-white rounded-md shadow-xl ring-1 ring-black ring-opacity-5"
           :style="{ top: coords.top + 'px', left: coords.left + 'px', width: coords.width + 'px' }">
        <div class="p-2 border-b">
          <input type="text" v-model="search" placeholder="Search..."
                 class="w-full border border-gray-200 rounded px-2 py-1 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
        </div>
        <div class="max-h-[calc(100vh-180px)] overflow-auto" :style="{ maxHeight: maxHeight }">
          <label v-for="opt in filteredOptions" :key="opt[valueKey]"
                 class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 cursor-pointer select-none">
            <input type="checkbox" class="rounded border-gray-300" :checked="selectedValues.includes(opt[valueKey])" @change="toggleValue(opt[valueKey])" />
            <span>{{ opt[labelKey] }}</span>
          </label>
          <div v-if="filteredOptions.length === 0" class="px-3 py-2 text-xs text-gray-500">No options</div>
        </div>
      </div>
    </teleport>
  </div>
</template>
