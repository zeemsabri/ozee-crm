<script setup>
import LeadCard from './LeadCard.vue';
import KanbanBoard from '@/Components/KanbanBoard.vue';

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
