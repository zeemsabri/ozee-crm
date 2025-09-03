<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { computed } from 'vue';

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
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-5">
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
    <div class="flex items-end gap-2">
      <PrimaryButton :disabled="props.loading" @click="emit('apply')">Apply</PrimaryButton>
      <SecondaryButton :disabled="props.loading" @click="emit('reset')">Reset</SecondaryButton>
    </div>
  </div>
</template>
