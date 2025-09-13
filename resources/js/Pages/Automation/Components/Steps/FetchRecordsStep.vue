<script setup>
import { computed } from 'vue';
import { useWorkflowStore } from '../../Store/workflowStore';
import StepCard from './StepCard.vue';
import { PlusIcon, TrashIcon } from 'lucide-vue-next';

const props = defineProps({
  step: { type: Object, required: true },
  allStepsBefore: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:step', 'delete']);
const store = useWorkflowStore();
const automationSchema = computed(() => store.automationSchema || []);

const config = computed({
  get: () => props.step.step_config || { conditions: [] },
  set: (newConfig) => emit('update:step', { ...props.step, step_config: newConfig }),
});

const availableModels = computed(() => automationSchema.value.map(m => m.name));

const columnsForSelectedModel = computed(() => {
  if (!config.value.model) return [];
  const model = automationSchema.value.find(m => m.name === config.value.model);
  return model ? (model.columns || []).map(col => typeof col === 'string' ? col : col.name) : [];
});

function addCondition() {
  const newConditions = [...(config.value.conditions || []), { column: '', operator: 'is', value: '' }];
  config.value = { ...config.value, conditions: newConditions };
}

function removeCondition(index) {
  const newConditions = (config.value.conditions || []).filter((_, i) => i !== index);
  config.value = { ...config.value, conditions: newConditions };
}

function updateCondition(index, key, value) {
  const newConditions = [...(config.value.conditions || [])];
  newConditions[index] = { ...newConditions[index], [key]: value };
  config.value = { ...config.value, conditions: newConditions };
}
</script>

<template>
  <StepCard icon="🔍" title="Fetch Records" :onDelete="() => emit('delete')">
    <div class="space-y-3">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Find all records from</label>
        <select :value="config.model || ''" @change="config = { ...config, model: $event.target.value, conditions: [] }" class="w-full p-2 border rounded-md text-sm">
          <option value="" disabled>Select model...</option>
          <option v-for="model in availableModels" :key="model" :value="model">{{ model }}</option>
        </select>
      </div>

      <div v-if="config.model">
        <div class="flex items-center justify-between mb-2">
          <label class="text-xs font-medium text-gray-600">Where conditions match</label>
          <button @click="addCondition" class="flex items-center gap-1 px-2 py-1 text-xs rounded-md bg-gray-100 hover:bg-gray-200">
            <PlusIcon class="h-3 w-3" /> Add
          </button>
        </div>
        <div class="space-y-2">
          <div v-for="(cond, index) in (config.conditions || [])" :key="index" class="p-2 border rounded-md bg-gray-50/50 grid grid-cols-3 gap-2 items-center">
            <select :value="cond.column" @change="updateCondition(index, 'column', $event.target.value)" class="w-full p-2 border rounded-md text-sm">
              <option value="">Field...</option>
              <option v-for="col in columnsForSelectedModel" :key="col" :value="col">{{ col }}</option>
            </select>
            <select :value="cond.operator" @change="updateCondition(index, 'operator', $event.target.value)" class="w-full p-2 border rounded-md text-sm">
              <option>is</option>
              <option>is not</option>
              <option>contains</option>
            </select>
            <div class="flex items-center">
              <input type="text" :value="cond.value" @input="updateCondition(index, 'value', $event.target.value)" placeholder="Value" class="w-full border rounded px-2 py-2 text-sm" />
              <button @click="removeCondition(index)" class="p-1 text-gray-400 hover:text-red-500" title="Remove Condition">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </StepCard>
</template>
