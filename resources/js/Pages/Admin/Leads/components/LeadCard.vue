<script setup>
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({
  lead: { type: Object, required: true }
});

const emit = defineEmits(['edit', 'delete', 'open']);

const isExistingClientEnquiry = computed(() => props.lead?.card_type === 'existing_client_enquiry');

const displayName = computed(() => {
  if (isExistingClientEnquiry.value) {
    return props.lead?.service_name || props.lead?.title || '(no service)';
  }

  return `${props.lead?.first_name || ''} ${props.lead?.last_name || ''}`.trim() || '(no name)';
});

const displayNumber = computed(() => {
  if (props.lead?.lead_number) return props.lead.lead_number;
  if (isExistingClientEnquiry.value) {
    return `EC-${String(props.lead?.enquiry_id || props.lead?.id || '').slice(0, 8).toUpperCase()}`;
  }

  return '';
});

const onDragStart = (e) => {
  e.dataTransfer.setData('text/plain', JSON.stringify({ id: props.lead.id }));
  e.dataTransfer.effectAllowed = 'move';
};

const badgeClass = (status) => {
  const s = (status || 'new').toLowerCase();
  switch (s) {
    case 'contacted':
    case 'outreach_sent': return 'bg-blue-100 text-blue-700';
    case 'qualified': return 'bg-amber-100 text-amber-700';
    case 'converted': return 'bg-emerald-100 text-emerald-700';
    case 'lost': return 'bg-rose-100 text-rose-700';
    case 'processing': return 'bg-indigo-100 text-indigo-700';
    case 'hot_incoming': return 'bg-orange-100 text-orange-700';
    case 'hot_outgoing': return 'bg-pink-100 text-pink-700';
    case 'generation_failed': 
    case 'generationfailed': 
    case 'failed': return 'bg-red-100 text-red-700';
    case 'pending_quote': return 'bg-sky-100 text-sky-700';
    case 'quoted': return 'bg-violet-100 text-violet-700';
    case 'approved': return 'bg-emerald-100 text-emerald-700';
    case 'rejected': return 'bg-rose-100 text-rose-700';
    case 'converted_to_service': return 'bg-teal-100 text-teal-700';
    case 'sequence_completed': 
    case 'sequencecompleted': 
    case 'completed': return 'bg-purple-100 text-purple-700';
    case 'new':
    default:
      return 'bg-gray-100 text-gray-700';
  }
};

const humanDateTime = (value) => {
  if (!value) return '—';
  const d = new Date(value);
  if (isNaN(d.getTime())) return '—';
  // Example: Thu, Sep 4 • 10:41 AM
  const datePart = new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' }).format(d);
  const timePart = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(d);
  return `${datePart} • ${timePart}`;
};

const additionalCampaignCount = computed(() => {
  const md = props.lead?.metadata || {};
  const arr = Array.isArray(md.additional_campaign_ids) ? md.additional_campaign_ids : [];
  return arr.length || 0;
});

const handleOpen = () => {
  if (isExistingClientEnquiry.value) {
    const projectId = props.lead?.project_id || props.lead?.metadata?.project_id;
    if (projectId) {
      window.location.href = `/projects/${projectId}/edit`;
    }
    return;
  }

  router.visit(route('leads.show', props.lead.id));
};
</script>

<template>
  <div
    class="p-3 rounded-lg border border-gray-200 bg-white shadow-sm hover:shadow transition cursor-grab"
    draggable="true"
    @dragstart="onDragStart"
    @click="handleOpen"
  >
    <div class="flex items-start justify-between">
      <div class="flex flex-col truncate">
        <div class="font-semibold text-gray-800 truncate">
          {{ displayName }}
        </div>
        <div class="text-[10px] text-gray-400 font-mono" v-if="displayNumber">{{ displayNumber }}</div>
      </div>
      <span class="text-xs px-2 py-0.5 rounded-full whitespace-nowrap" :class="badgeClass(lead.status)">{{ lead.status || 'new' }}</span>
    </div>
    <div class="mt-1 flex items-center gap-2 flex-wrap" v-if="isExistingClientEnquiry">
      <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Existing Client Enquiry</span>
      <span class="text-sm text-gray-600 truncate" v-if="lead.project_name">{{ lead.project_name }}</span>
    </div>
    <div class="mt-1 text-sm text-gray-600 truncate" v-if="lead.company">{{ lead.company }}</div>
    <div class="mt-1 text-xs text-gray-500 truncate" v-if="isExistingClientEnquiry && lead.description">{{ lead.description }}</div>
    
    <div class="mt-2 flex items-center gap-1.5 text-xs text-gray-500" v-if="lead.assigned_to">
      <div class="w-5 h-5 rounded-full bg-gray-100 flex items-center justify-center text-[10px] font-bold text-gray-500 border border-gray-200 uppercase">
        {{ lead.assigned_to.name.charAt(0) }}
      </div>
      <span class="truncate">{{ lead.assigned_to.name }}</span>
    </div>

    <div class="mt-2 space-y-0.5">
      <div class="text-xs text-gray-500 truncate flex items-center gap-1" v-if="lead.email">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
        {{ lead.email }}
      </div>
      <div class="text-xs text-gray-500 truncate flex items-center gap-1" v-if="lead.phone">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
        {{ lead.phone }}
      </div>
    </div>

    <!-- Campaign badges -->
    <div class="mt-2 flex items-center gap-2 flex-wrap" v-if="!isExistingClientEnquiry">
      <span v-if="lead.campaign" class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">{{ lead.campaign.name }}</span>
      <span v-if="additionalCampaignCount" class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">+{{ additionalCampaignCount }} more</span>
    </div>

    <!-- Latest context summary -->
    <div v-if="lead.latest_context?.summary" class="mt-2 text-[11px] text-gray-600 line-clamp-2">
      {{ lead.latest_context.summary }}
    </div>

    <div class="mt-2 grid grid-cols-1 gap-1 text-[11px] text-gray-500">
      <div v-if="lead.contacted_at">
        <span class="font-medium text-gray-600">Contacted:</span>
        <span>{{ humanDateTime(lead.contacted_at) }}</span>
      </div>
      <div v-if="lead.last_communication_at">
        <span class="font-medium text-gray-600">Last comms:</span>
        <span>{{ humanDateTime(lead.last_communication_at) }}</span>
      </div>
    </div>

    <div class="mt-3 flex items-center justify-end gap-2">
      <button class="text-xs text-indigo-600 hover:underline" @click.stop="emit('edit', lead)">Edit</button>
      <button class="text-xs text-rose-600 hover:underline" @click.stop="emit('delete', lead)">Delete</button>
    </div>
  </div>
</template>
