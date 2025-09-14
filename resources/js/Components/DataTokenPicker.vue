<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  groups: { type: Array, default: () => [] }, // [{ name, fields: [{ label, value }] }]
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  maxHeight: { type: String, default: '300px' },
  minWidth: { type: [String, Number], default: 320 }, // ensure readable width
  maxPanelWidth: { type: [String, Number], default: 480 },
});

const emit = defineEmits(['select']);

const isOpen = ref(false);
const triggerRef = ref(null);
const portalRef = ref(null);
const coords = ref({ top: 0, left: 0, width: 0 });
const search = ref('');

const toPx = (v) => {
  if (typeof v === 'number') return v;
  const n = parseInt(String(v).replace('px',''));
  return isNaN(n) ? 0 : n;
};

function open() {
  if (props.disabled) return;
  isOpen.value = !isOpen.value;
  nextTick(() => positionPanel());
}

function positionPanel() {
  const el = triggerRef.value;
  if (!el) return;
  const rect = el.getBoundingClientRect();
  const margin = 8;
  const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
  const desired = Math.max(rect.width, toPx(props.minWidth));
  const maxW = Math.min(toPx(props.maxPanelWidth), viewportWidth - margin * 2);
  const width = Math.min(Math.max(desired, toPx(props.minWidth)), maxW);
  let left = rect.left + window.scrollX;
  // Shift left if overflowing viewport
  if (left + width + margin > viewportWidth + window.scrollX) {
    left = viewportWidth + window.scrollX - width - margin;
    if (left < margin) left = margin;
  }
  coords.value = { top: rect.bottom + window.scrollY, left, width };
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

const flatItems = computed(() => {
  const items = [];
  (props.groups || []).forEach((g, gi) => {
    (g.fields || []).forEach((f, fi) => {
      items.push({ groupIndex: gi, itemIndex: fi, group: g.name || 'Data', label: f.label, value: f.value });
    });
  });
  return items;
});

const filteredGroups = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return props.groups;
  const list = [];
  (props.groups || []).forEach(g => {
    const fields = (g.fields || []).filter(f => String(f.label || '').toLowerCase().includes(q));
    if (fields.length) list.push({ name: g.name, fields });
  });
  return list;
});

function selectValue(val) {
  emit('select', val);
  isOpen.value = false;
}
</script>

<template>
  <div class="relative inline-block" ref="triggerRef">
    <button type="button" @click="open" :disabled="disabled" aria-label="Insert data"
            class="inline-flex items-center justify-center rounded-full border border-gray-300 bg-white p-1.5 text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-60">
      <!-- Plus icon -->
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
      </svg>
    </button>

    <teleport to="body">
      <div v-if="isOpen" ref="portalRef"
           class="fixed z-[9999] bg-white rounded-md shadow-xl ring-1 ring-black ring-opacity-5"
           :style="{ top: coords.top + 'px', left: coords.left + 'px', width: coords.width + 'px', maxWidth: 'min(480px, 96vw)' }">
        <div class="p-2 border-b">
          <input type="text" v-model="search" placeholder="Search fields..."
                 class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:ring-indigo-500 focus:border-indigo-500" />
        </div>
        <div class="max-h-[calc(100vh-180px)] overflow-auto" :style="{ maxHeight: maxHeight }">
          <template v-if="(filteredGroups && filteredGroups.length)">
            <div v-for="group in filteredGroups" :key="group.name" class="py-1">
              <div class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 bg-gray-50">{{ group.name }}</div>
              <button
                v-for="field in (group.fields || [])"
                :key="field.value"
                type="button"
                class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50"
                @click="selectValue(field.value)"
              >
                {{ field.label }}
              </button>
            </div>
          </template>
          <div v-else class="px-3 py-2 text-xs text-gray-500">No data available</div>
        </div>
      </div>
    </teleport>
  </div>
</template>
