<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { computed, ref, onMounted } from 'vue';

const props = defineProps({
  filters: { type: Object, required: true },
  sourceOptions: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['apply', 'reset']);

const userOptions = computed(() => (props.users || []).map(u => ({ label: u.name, value: u.id })));
const statusOpts = computed(() => props.statusOptions || []);
const sourceOpts = computed(() => props.sourceOptions || []);

const campaignOptions = ref([]);
const loadingCampaigns = ref(false);

onMounted(async () => {
  try {
    loadingCampaigns.value = true;
    const { data } = await window.axios.get('/api/campaigns', { params: { per_page: 1000 } });
    const items = data?.data || data || [];
    campaignOptions.value = items.map(c => ({ label: c.name, value: c.id }));
  } catch (e) {
    console.error('Failed to load campaigns', e);
    campaignOptions.value = [];
  } finally {
    loadingCampaigns.value = false;
  }
});
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-5">
    <div>
      <InputLabel for="q" value="Search" />
      <TextInput id="q" v-model="props.filters.q" class="mt-1 block w-full" placeholder="Name, email, phone, company" @keyup.enter="() => emit('apply')" />
    </div>
    <div>
      <InputLabel for="status" value="Status" />
      <SelectDropdown
        :options="statusOpts"
        v-model="props.filters.status"
        placeholder="All"
      />
    </div>
    <div>
      <InputLabel for="source" value="Source" />
      <SelectDropdown
        :options="sourceOpts"
        v-model="props.filters.source"
        placeholder="All"
      />
    </div>
    <div>
      <InputLabel for="assigned_to" value="Assigned To" />
      <SelectDropdown
        :options="userOptions"
        v-model="props.filters.assigned_to_id"
        placeholder="Any"
      />
    </div>
    <div>
      <InputLabel for="campaigns" value="Campaigns" />
      <select id="campaigns" class="mt-1 block w-full border-gray-300 rounded-md" multiple v-model="props.filters.campaign_ids">
        <option v-for="opt in campaignOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <p class="text-xs text-gray-500 mt-1" v-if="loadingCampaigns">Loading campaigns…</p>
    </div>
    <div class="flex items-end gap-2">
      <PrimaryButton :disabled="props.loading" @click="emit('apply')">Apply</PrimaryButton>
      <SecondaryButton :disabled="props.loading" @click="emit('reset')">Reset</SecondaryButton>
    </div>
  </div>
</template>
