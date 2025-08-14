<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { computed } from 'vue';

const props = defineProps({
  year: { type: Number, required: true },
  month: { type: Number, required: true },
  yearOptions: { type: Array, required: true },
  monthOptions: { type: Array, required: true },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:year', 'update:month', 'calculate']);

const yearModel = computed({
  get: () => props.year,
  set: (val) => emit('update:year', val),
});

const monthModel = computed({
  get: () => props.month,
  set: (val) => emit('update:month', val),
});
</script>

<template>
  <div class="flex flex-col md:flex-row items-end justify-between gap-4 p-4 mb-8 bg-gray-100 rounded-lg">
    <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <InputLabel value="Year" />
        <SelectDropdown v-model="yearModel" :options="yearOptions" class="mt-1 w-full" />
      </div>
      <div>
        <InputLabel value="Month" />
        <SelectDropdown v-model="monthModel" :options="monthOptions" class="mt-1 w-full" />
      </div>
    </div>
    <div class="flex-shrink-0 w-full md:w-auto">
      <PrimaryButton @click="$emit('calculate')" :disabled="loading" class="w-full mt-4 md:mt-0">
        <span v-if="loading">Calculating...</span>
        <span v-else>Calculate</span>
      </PrimaryButton>
    </div>
  </div>
</template>
