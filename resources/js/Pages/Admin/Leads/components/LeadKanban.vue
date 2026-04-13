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
  { key: 'new', title: 'New / Hot Incoming' },
  { key: 'processing', title: 'Processing' },
  { key: 'contacted', title: 'Contacted' },
  { key: 'outreach_sent', title: 'Outreach Sent' },
  { key: 'qualified', title: 'Qualified' },
  { key: 'sequence_completed', title: 'Sequence Completed' },
  { key: 'generation_failed', title: 'Generation Failed' },
  { key: 'converted', title: 'Converted' },
  { key: 'lost', title: 'Lost' },
  { key: 'pending_quote', title: 'Pending Quote' },
  { key: 'quoted', title: 'Quoted' },
  { key: 'approved', title: 'Approved' },
  { key: 'rejected', title: 'Rejected' },
  { key: 'converted_to_service', title: 'Converted To Service' },
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
