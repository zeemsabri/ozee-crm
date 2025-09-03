<script setup>
const props = defineProps({
  lead: { type: Object, required: true }
});

const emit = defineEmits(['edit', 'delete', 'open']);

const onDragStart = (e) => {
  e.dataTransfer.setData('text/plain', JSON.stringify({ id: props.lead.id }));
  e.dataTransfer.effectAllowed = 'move';
};

const badgeClass = (status) => {
  const s = (status || 'new').toLowerCase();
  switch (s) {
    case 'contacted': return 'bg-blue-100 text-blue-700';
    case 'qualified': return 'bg-amber-100 text-amber-700';
    case 'converted': return 'bg-emerald-100 text-emerald-700';
    case 'lost': return 'bg-rose-100 text-rose-700';
    case 'new':
    default:
      return 'bg-gray-100 text-gray-700';
  }
};
</script>

<template>
  <div
    class="p-3 rounded-lg border border-gray-200 bg-white shadow-sm hover:shadow transition cursor-grab"
    draggable="true"
    @dragstart="onDragStart"
    @click="$inertia.visit(route('leads.show', props.lead.id))"
  >
    <div class="flex items-start justify-between">
      <div class="font-semibold text-gray-800 truncate">
        {{ (lead.first_name || '') + ' ' + (lead.last_name || '') || '(no name)' }}
      </div>
      <span class="text-xs px-2 py-0.5 rounded-full" :class="badgeClass(lead.status)">{{ lead.status || 'new' }}</span>
    </div>
    <div class="mt-1 text-sm text-gray-600 truncate" v-if="lead.company">{{ lead.company }}</div>
    <div class="mt-1 text-xs text-gray-500 truncate" v-if="lead.email">{{ lead.email }}</div>
    <div class="mt-1 text-xs text-gray-500 truncate" v-if="lead.phone">{{ lead.phone }}</div>

    <div class="mt-3 flex items-center justify-end gap-2">
      <button class="text-xs text-indigo-600 hover:underline" @click.stop="emit('edit', lead)">Edit</button>
      <button class="text-xs text-rose-600 hover:underline" @click.stop="emit('delete', lead)">Delete</button>
    </div>
  </div>
</template>
