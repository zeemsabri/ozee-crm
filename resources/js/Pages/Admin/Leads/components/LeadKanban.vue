<script setup>
import { computed } from 'vue';
import LeadCard from './LeadCard.vue';

const props = defineProps({
  leadsByStatus: { type: Object, required: true },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'delete', 'move']);

const columns = [
  { key: 'new', title: 'New' },
  { key: 'contacted', title: 'Contacted' },
  { key: 'qualified', title: 'Qualified' },
  { key: 'converted', title: 'Converted' },
  { key: 'lost', title: 'Lost' },
];

const onDragOver = (e) => {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
};

const onDropTo = (e, status) => {
  try {
    const payload = JSON.parse(e.dataTransfer.getData('text/plain'));
    if (payload && payload.id) {
      emit('move', { id: payload.id, status });
    }
  } catch (err) {
    console.warn('Invalid drop payload', err);
  }
};

const countIn = (key) => (props.leadsByStatus?.[key]?.length ?? 0);
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
        <h3 class="text-sm font-semibold text-gray-700">{{ col.title }}</h3>
        <span class="text-xs text-gray-500">{{ countIn(col.key) }}</span>
      </div>

      <div v-if="loading" class="text-xs text-gray-500 p-2">Loading...</div>
      <div class="space-y-2">
        <LeadCard
          v-for="lead in (props.leadsByStatus[col.key] || [])"
          :key="lead.id"
          :lead="lead"
          @edit="$emit('edit', lead)"
          @delete="$emit('delete', lead)"
        />
      </div>
    </div>
  </div>
</template>
