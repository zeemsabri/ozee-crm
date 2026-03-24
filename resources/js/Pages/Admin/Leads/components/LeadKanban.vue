<script setup>
import { computed } from 'vue';
import LeadCard from './LeadCard.vue';
import KanbanBoard from '@/Components/KanbanBoard.vue';

const props = defineProps({
  leadsByStatus: { type: Object, required: true },
  loading: { type: Boolean, default: false },
  filters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['edit', 'delete', 'move']);

const standardColumns = [
  { key: 'new', title: 'New' },
  { key: 'contacted', title: 'Contacted' },
  { key: 'qualified', title: 'Qualified' },
  { key: 'converted', title: 'Converted' },
  { key: 'lost', title: 'Lost' },
];

const columns = computed(() => {
  const cols = [...standardColumns];
  const selectedStatus = props.filters?.status;
  
  if (selectedStatus && !cols.find(c => c.key === selectedStatus)) {
    // Add the selected (hidden) status as a 6th column
    // Format key to a human-readable title (e.g., hot_incoming -> Hot Incoming)
    const title = selectedStatus.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    cols.push({ key: selectedStatus, title: title });
  }
  
  return cols;
});

function handleMove({ id, to }) {
  emit('move', { id, status: to });
}
</script>

<template>
  <KanbanBoard
    :columns="columns"
    :items-by-column="props.leadsByStatus"
    :loading="props.loading"
    @move="handleMove"
  >
    <template #item="{ item }">
      <LeadCard
        :lead="item"
        @edit="$emit('edit', item)"
        @delete="$emit('delete', item)"
      />
    </template>
  </KanbanBoard>
</template>
