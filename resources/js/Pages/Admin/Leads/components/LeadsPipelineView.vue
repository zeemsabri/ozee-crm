<script setup>
import { computed, ref, watch } from 'vue';
import RightSidebar from '@/Components/RightSidebar.vue';
import {
  AlertCircle,
  ArrowRight,
  Building2,
  Calendar,
  ExternalLink,
  Globe,
  Mail,
  MessageSquare,
  Phone,
  Target,
  TrendingUp,
  X,
  Zap,
} from 'lucide-vue-next';

const props = defineProps({
  leads: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'delete', 'move']);

const STATUS_MAPPING = {
  discovery: {
    leads: ['new', 'inbound', 'assigned', 'cold', 'hot_incoming', 'hot_outgoing'],
    enquiries: ['pending_quote', 'enquiry_received'],
  },
  engagement: {
    leads: ['contacted', 'meeting_scheduled', 'warm', 'processing', 'outreach_sent'],
    enquiries: ['quoted', 'proposal_sent'],
  },
  proposal: {
    leads: ['qualified', 'negotiation', 'awaiting_feedback'],
    enquiries: ['awaiting_review', 'approved'],
  },
  closing: {
    leads: ['converted', 'won', 'signed', 'lost', 'sequence_completed'],
    enquiries: ['converted_to_service', 'rejected'],
  },
};

const STAGES = [
  { id: 'discovery', name: 'Discovery', color: 'indigo', description: 'New leads and fresh enquiries' },
  { id: 'engagement', name: 'Engagement', color: 'blue', description: 'Active communication and quoting' },
  { id: 'proposal', name: 'Proposal', color: 'violet', description: 'Qualified prospects and approved enquiries' },
  { id: 'closing', name: 'Closing', color: 'emerald', description: 'Converted, rejected, and completed outcomes' },
];

const activeStage = ref(null);
const selectedItem = ref(null);
const selectedTargetStatus = ref('');
const selectedConversionType = ref('task');

const isEnquiry = (item) => ['existing_client_enquiry', 'client_enquiry'].includes(item?.card_type);
const getStatus = (item) => (isEnquiry(item) ? (item.enquiry_status || item.status || '') : (item.status || '')).toLowerCase();
const getCurrencySymbol = (currency) => (currency === 'AUD' ? 'A$' : '$');

const normalizedItems = computed(() => {
  return (props.leads || []).map((item) => {
    const enquiry = isEnquiry(item);
    const status = getStatus(item);
    const lastUpdateRaw = item.updated_at || item.enquiry_updated_at || item.created_at;
    const lastUpdate = lastUpdateRaw ? new Date(String(lastUpdateRaw).replace(' ', 'T')) : new Date();
    const daysStale = Number.isNaN(lastUpdate.getTime())
      ? 0
      : Math.floor((Date.now() - lastUpdate.getTime()) / (1000 * 60 * 60 * 24));
    const followUpRaw = item.next_follow_up_date ? new Date(String(item.next_follow_up_date).replace(' ', 'T')) : null;
    const isOverdue = followUpRaw && !Number.isNaN(followUpRaw.getTime()) ? followUpRaw < new Date() : false;
    const needsAttention = isOverdue || (!enquiry && status === 'new' && daysStale > 2) || (enquiry && status === 'pending_quote' && daysStale > 1);

    return {
      ...item,
      isEnquiry: enquiry,
      normalizedStatus: status,
      daysStale,
      isOverdue,
      needsAttention,
      amountValue: Number(item.estimated_value || item.amount || 0),
    };
  });
});

const pipelineData = computed(() => {
  return STAGES.map((stage) => {
    const mapping = STATUS_MAPPING[stage.id];
    const items = normalizedItems.value.filter((item) => {
      if (item.isEnquiry) {
        return mapping.enquiries.includes(item.normalizedStatus);
      }
      return mapping.leads.includes(item.normalizedStatus);
    });

    return {
      ...stage,
      items,
      totalValue: items.reduce((sum, item) => sum + item.amountValue, 0),
      criticalCount: items.filter((item) => item.needsAttention).length,
    };
  });
});

const allFeedItems = computed(() => {
  const stageItems = activeStage.value
    ? pipelineData.value.find((stage) => stage.id === activeStage.value)?.items || []
    : pipelineData.value.flatMap((stage) => stage.items);

  return [...stageItems].sort((a, b) => {
    if (a.needsAttention !== b.needsAttention) {
      return a.needsAttention ? -1 : 1;
    }

    const aUpdated = new Date(String(a.updated_at || a.enquiry_updated_at || a.created_at || '').replace(' ', 'T')).getTime() || 0;
    const bUpdated = new Date(String(b.updated_at || b.enquiry_updated_at || b.created_at || '').replace(' ', 'T')).getTime() || 0;
    return bUpdated - aUpdated;
  });
});

const grandTotal = computed(() => pipelineData.value.reduce((sum, stage) => sum + stage.totalValue, 0));
const urgentCount = computed(() => pipelineData.value.reduce((sum, stage) => sum + stage.criticalCount, 0));

const selectedStageName = computed(() => {
  if (!activeStage.value) return '';
  return STAGES.find((stage) => stage.id === activeStage.value)?.name || '';
});

const statusToStageMap = computed(() => {
  const leadMap = {};
  const enquiryMap = {};

  for (const stage of STAGES) {
    const mapping = STATUS_MAPPING[stage.id];
    for (const status of mapping.leads) {
      leadMap[status] = stage.name;
    }
    for (const status of mapping.enquiries) {
      enquiryMap[status] = stage.name;
    }
  }

  return { leadMap, enquiryMap };
});

const statusOptionsForSelectedItem = computed(() => {
  if (!selectedItem.value) return [];

  const enquiry = selectedItem.value.isEnquiry;
  const statuses = enquiry
    ? ['pending_quote', 'quoted', 'approved', 'rejected', 'converted_to_service']
    : ['new', 'processing', 'contacted', 'outreach_sent', 'qualified', 'sequence_completed', 'generation_failed', 'converted', 'lost'];
  const currentStatus = selectedItem.value.normalizedStatus;

  return statuses
    .filter((status) => status !== currentStatus)
    .map((status) => {
    const stageName = enquiry
      ? statusToStageMap.value.enquiryMap[status] || 'Other'
      : statusToStageMap.value.leadMap[status] || 'Other';
    return {
      value: status,
      label: `${status.replace(/_/g, ' ')} (${stageName})`,
    };
  });
});

const canMoveToSelectedStatus = computed(() => {
  if (!selectedItem.value) return false;
  if (!selectedTargetStatus.value) return false;
  return selectedTargetStatus.value !== selectedItem.value.normalizedStatus;
});

const moveDisabledReason = computed(() => {
  if (!selectedItem.value) return 'Select a lead/enquiry card to move.';
  if (statusOptionsForSelectedItem.value.length === 0) return 'No available status transitions for this item.';
  if (!selectedTargetStatus.value) return 'Choose a target status.';
  if (selectedTargetStatus.value === selectedItem.value.normalizedStatus) return 'Choose a different status from the current one.';
  return '';
});

const targetStatusLabel = computed(() => {
  const target = selectedTargetStatus.value;
  if (!target) return '';
  return target.replace(/_/g, ' ');
});

const currentStageForSelected = computed(() => {
  if (!selectedItem.value) return 'Other';
  const status = selectedItem.value.normalizedStatus;
  if (selectedItem.value.isEnquiry) {
    return statusToStageMap.value.enquiryMap[status] || 'Other';
  }
  return statusToStageMap.value.leadMap[status] || 'Other';
});

const nextStatusForItem = (item) => {
  const stageIndex = STAGES.findIndex((stage) => {
    const mapping = STATUS_MAPPING[stage.id];
    if (item.isEnquiry) {
      return mapping.enquiries.includes(item.normalizedStatus);
    }
    return mapping.leads.includes(item.normalizedStatus);
  });

  if (stageIndex < 0 || stageIndex >= STAGES.length - 1) {
    return null;
  }

  const nextStage = STAGES[stageIndex + 1];
  const nextMapping = STATUS_MAPPING[nextStage.id];
  return item.isEnquiry ? nextMapping.enquiries[0] : nextMapping.leads[0];
};

const openItem = (item) => {
  selectedItem.value = item;
};

const closeSidebar = () => {
  selectedItem.value = null;
};

const moveToSelectedStatus = () => {
  if (!selectedItem.value || !selectedTargetStatus.value) return;
  if (selectedTargetStatus.value === selectedItem.value.normalizedStatus) return;

  const payload = {
    id: selectedItem.value.id,
    status: selectedTargetStatus.value,
  };

  if (selectedItem.value.isEnquiry && selectedTargetStatus.value === 'converted_to_service') {
    payload.conversion_type = selectedConversionType.value || 'task';
  }

  emit('move', payload);
};

watch(selectedItem, (item) => {
  if (!item) {
    selectedTargetStatus.value = '';
    selectedConversionType.value = 'task';
    return;
  }

  selectedTargetStatus.value = nextStatusForItem(item) || statusOptionsForSelectedItem.value[0]?.value || '';
  selectedConversionType.value = 'task';
});

watch(normalizedItems, (items) => {
  if (!selectedItem.value) return;

  const fresh = items.find((item) => item.id === selectedItem.value.id && item.card_type === selectedItem.value.card_type);
  if (!fresh) {
    closeSidebar();
    return;
  }

  selectedItem.value = fresh;
  if (selectedTargetStatus.value === '' || selectedTargetStatus.value === selectedItem.value.normalizedStatus) {
    selectedTargetStatus.value = nextStatusForItem(fresh) || statusOptionsForSelectedItem.value[0]?.value || '';
  }
}, { deep: true });
</script>

<template>
  <div class="min-h-[680px] bg-slate-50 rounded-2xl border border-slate-200 p-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-slate-900 rounded-xl">
          <Target class="w-5 h-5 text-white" />
        </div>
        <div>
          <h3 class="text-lg font-bold text-slate-900">Pipeline Pulse</h3>
          <p class="text-xs text-slate-500">Unified view for leads and existing-client enquiries</p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 bg-white border border-slate-200 rounded-xl px-4 py-3">
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total Value</p>
          <p class="text-xl font-bold text-slate-900">${{ grandTotal.toLocaleString() }}</p>
        </div>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-400">Urgent</p>
          <p class="text-xl font-bold text-rose-600">{{ urgentCount }}</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
      <button
        v-for="stage in pipelineData"
        :key="stage.id"
        type="button"
        @click="activeStage = activeStage === stage.id ? null : stage.id"
        class="text-left bg-white rounded-2xl border p-4 transition"
        :class="activeStage === stage.id ? 'border-slate-800 ring-4 ring-slate-200' : 'border-slate-200 hover:border-slate-300'"
      >
        <div class="flex items-start justify-between mb-3">
          <span
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg"
            :class="{
              'bg-indigo-100 text-indigo-700': stage.color === 'indigo',
              'bg-blue-100 text-blue-700': stage.color === 'blue',
              'bg-violet-100 text-violet-700': stage.color === 'violet',
              'bg-emerald-100 text-emerald-700': stage.color === 'emerald'
            }"
          >
            <ArrowRight class="w-4 h-4" />
          </span>
          <span v-if="stage.criticalCount" class="w-6 h-6 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center">{{ stage.criticalCount }}</span>
        </div>

        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ stage.name }}</p>
        <div class="mt-1 flex items-end gap-2">
          <p class="text-xl font-bold text-slate-900">${{ (stage.totalValue / 1000).toFixed(1) }}k</p>
          <p class="text-xs text-slate-500">({{ stage.items.length }})</p>
        </div>
        <p class="text-xs text-slate-500 mt-2">{{ stage.description }}</p>
      </button>
    </div>

    <div class="flex items-center justify-between mb-3">
      <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 flex items-center gap-2">
        <Zap class="w-4 h-4 text-amber-500" />
        {{ activeStage ? `Focus: ${selectedStageName}` : 'Priority Feed' }}
      </p>
      <button v-if="activeStage" type="button" class="text-xs font-semibold text-indigo-600 hover:underline" @click="activeStage = null">Clear Filter</button>
    </div>

    <div v-if="loading" class="py-10 text-center text-slate-500">Loading pipeline...</div>
    <div v-else-if="allFeedItems.length === 0" class="py-10 text-center text-slate-500">No leads or enquiries found for this view.</div>
    <div v-else class="space-y-3 max-h-[460px] overflow-y-auto pr-1">
      <article
        v-for="item in allFeedItems"
        :key="`${item.card_type}-${item.id}`"
        class="bg-white border rounded-xl p-4 transition cursor-pointer hover:shadow-sm"
        :class="[
          selectedItem?.id === item.id && selectedItem?.card_type === item.card_type ? 'border-slate-900' : 'border-slate-200',
          item.needsAttention ? 'ring-2 ring-rose-100' : ''
        ]"
        @click="openItem(item)"
      >
        <div class="flex items-start gap-3">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center"
               :class="item.isEnquiry ? 'bg-violet-100 text-violet-700' : 'bg-blue-100 text-blue-700'">
            <Building2 v-if="item.isEnquiry" class="w-5 h-5" />
            <TrendingUp v-else class="w-5 h-5" />
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <p class="font-semibold text-slate-900 truncate">{{ item.isEnquiry ? item.client_name : `${item.first_name || ''} ${item.last_name || ''}`.trim() }}</p>
              <span v-if="item.needsAttention" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-rose-100 text-rose-700 text-[10px] font-semibold uppercase">
                <AlertCircle class="w-3 h-3" /> Urgent
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5 truncate">
              <span class="uppercase font-semibold text-slate-400">{{ item.normalizedStatus.replace(/_/g, ' ') }}</span>
              <span class="mx-1">•</span>
              <span>{{ item.isEnquiry ? item.project_name : (item.company || 'Private Lead') }}</span>
            </p>
          </div>

          <div class="text-right">
            <p class="text-base font-bold text-slate-900">{{ getCurrencySymbol(item.currency) }}{{ item.amountValue.toLocaleString() }}</p>
            <p class="text-[10px] font-semibold uppercase" :class="item.isEnquiry ? 'text-violet-600' : 'text-blue-600'">
              {{ item.isEnquiry ? 'Existing Client' : 'New Acquisition' }}
            </p>
          </div>
        </div>
      </article>
    </div>

    <RightSidebar :show="!!selectedItem" title="Pipeline Details" :initial-width="40" :min-width="30" :max-width="75" @update:show="(value) => { if (!value) closeSidebar(); }" @close="closeSidebar">
      <template #content>
        <div v-if="selectedItem" class="space-y-6">
          <div>
            <h4 class="text-xl font-bold text-slate-900 leading-tight">
              {{ selectedItem.isEnquiry ? selectedItem.client_name : `${selectedItem.first_name || ''} ${selectedItem.last_name || ''}`.trim() }}
            </h4>
            <p class="text-sm text-indigo-600 font-medium flex items-center gap-1.5 mt-1">
              {{ selectedItem.isEnquiry ? selectedItem.project_name : (selectedItem.company || 'Private Lead') }}
              <ExternalLink v-if="!selectedItem.isEnquiry && selectedItem.website" class="w-3.5 h-3.5" />
            </p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <p class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Stage</p>
              <p class="text-sm font-semibold text-slate-800 mt-1">{{ selectedItem.normalizedStatus.replace(/_/g, ' ') }} ({{ currentStageForSelected }})</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
              <p class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Potential</p>
              <p class="text-sm font-semibold text-slate-800 mt-1">{{ getCurrencySymbol(selectedItem.currency) }}{{ selectedItem.amountValue.toLocaleString() }}</p>
            </div>
          </div>

          <div class="space-y-3 rounded-xl border border-slate-200 p-4 bg-slate-50">
            <p class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Move To</p>
            <select
              v-model="selectedTargetStatus"
              class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="" disabled>Select target stage/status</option>
              <option v-for="option in statusOptionsForSelectedItem" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>

            <div v-if="selectedItem.isEnquiry && selectedTargetStatus === 'converted_to_service'" class="space-y-1.5">
              <p class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Convert As</p>
              <select
                v-model="selectedConversionType"
                class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option value="task">Task</option>
                <option value="milestone">Milestone</option>
                <option value="project">Project</option>
              </select>
            </div>
          </div>

          <div v-if="!selectedItem.isEnquiry" class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Contact Info</p>
            <div v-if="selectedItem.email" class="flex items-center gap-3 text-sm text-slate-600">
              <Mail class="w-4 h-4 text-slate-400" />
              <span class="truncate">{{ selectedItem.email }}</span>
            </div>
            <div v-if="selectedItem.phone" class="flex items-center gap-3 text-sm text-slate-600">
              <Phone class="w-4 h-4 text-slate-400" />
              <span>{{ selectedItem.phone }}</span>
            </div>
            <div v-if="selectedItem.website" class="flex items-center gap-3 text-sm text-slate-600">
              <Globe class="w-4 h-4 text-slate-400" />
              <span class="truncate">{{ selectedItem.website }}</span>
            </div>
          </div>

          <div v-if="selectedItem.notes || selectedItem.description" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-[10px] uppercase tracking-wider text-amber-600 font-semibold mb-2 flex items-center gap-1.5">
              <MessageSquare class="w-3.5 h-3.5" /> Notes
            </p>
            <p class="text-sm text-amber-900">{{ selectedItem.notes || selectedItem.description }}</p>
          </div>

          <div class="rounded-xl border border-slate-200 p-3 text-xs text-slate-500 flex items-center gap-2">
            <Calendar class="w-4 h-4" />
            Last activity: {{ selectedItem.updated_at || selectedItem.enquiry_updated_at || selectedItem.created_at || 'N/A' }}
          </div>
        </div>
      </template>

      <template #footer>
        <div v-if="selectedItem" class="space-y-2">
          <button
            type="button"
            class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-white bg-slate-900 hover:bg-slate-800 disabled:opacity-60 disabled:cursor-not-allowed transition flex items-center justify-center gap-2"
            @click="moveToSelectedStatus"
            :disabled="!canMoveToSelectedStatus"
          >
            <Zap class="w-4 h-4 text-amber-300" />
            Move To {{ targetStatusLabel || 'Selected Stage' }}
          </button>
          <p v-if="!canMoveToSelectedStatus" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            {{ moveDisabledReason }}
          </p>

          <button
            type="button"
            class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50 transition flex items-center justify-center gap-2"
            @click="closeSidebar"
          >
            <X class="w-4 h-4" />
            Close
          </button>
        </div>
      </template>
    </RightSidebar>
  </div>
</template>
