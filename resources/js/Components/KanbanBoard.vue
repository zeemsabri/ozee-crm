<script setup>
import { computed } from 'vue';

const props = defineProps({
  columns: { type: Array, required: true }, // [{ key, title }]
  itemsByColumn: { type: Object, required: true }, // { [key]: Array<any> }
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['move', 'drop']);

const onDragOver = (e) => {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
};

const onDropTo = (e, toKey) => {
  try {
    const text = e.dataTransfer.getData('text/plain');
    const data = JSON.parse(text);
    emit('drop', { data, to: toKey });
    if (data && (data.id != null)) {
      emit('move', { id: data.id, to: toKey });
    }
  } catch (_) {
    // ignore invalid payloads but still emit a generic drop without data
    emit('drop', { data: null, to: toKey });
  }
};

const countIn = (key) => (props.itemsByColumn?.[key]?.length ?? 0);
</script>

<template>
  <div class="flex gap-4 overflow-x-auto">
    <div
      v-for="col in columns"
      :key="col.key"
      class="flex-1 min-w-[260px] bg-gray-50 rounded-lg border border-gray-200 p-3"
      @dragover="onDragOver"
      @drop="(e) => onDropTo(e, col.key)"
    >
      <div class="flex items-center justify-between mb-2">
        <slot name="column-header" :column="col">
          <h3 class="text-sm font-semibold text-gray-700">{{ col.title }}</h3>
        </slot>
        <span class="text-xs text-gray-500">{{ countIn(col.key) }}</span>
      </div>

      <div v-if="loading" class="text-xs text-gray-500 p-2">Loading...</div>
      <div class="space-y-2">
        <template v-for="item in (itemsByColumn[col.key] || [])" :key="item.id ?? JSON.stringify(item)">
          <slot name="item" :item="item" :column-key="col.key" />
        </template>
      </div>
    </div>
  </div>
</template>
